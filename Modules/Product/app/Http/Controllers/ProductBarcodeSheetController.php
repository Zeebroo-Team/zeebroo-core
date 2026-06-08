<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Product\Http\Controllers\Concerns\ResolvesProductBusiness;
use Modules\Product\Models\ProductBarcodeSheet;
use Modules\Product\Services\ProductBarcodeSheetService;

class ProductBarcodeSheetController extends Controller
{
    use ResolvesProductBusiness;

    private const ENCODE_TYPES = ['CODE128', 'CODE39', 'EAN13', 'EAN8', 'UPC', 'QR'];
    private const PAGE_SIZES    = ['A4', 'A5', 'Letter', 'Legal'];
    private const ORIENTATIONS  = ['portrait', 'landscape'];
    private const LABEL_TYPES   = ['barcode_only', 'with_name', 'with_name_price', 'with_sku'];

    public function __construct(private readonly ProductBarcodeSheetService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $sheets   = $business->productBarcodeSheets()->with('product')->get();
        $products = $business->products()->where('is_active', true)->select('id', 'name', 'sku')->orderBy('name')->get();
        $viewErrors = $request->session()->get('errors');
        $modalOpen  = $sheets->isNotEmpty() && $viewErrors !== null && $viewErrors->any() && ! $viewErrors->has('sheet');

        return view('product::barcodes.index', [
            'business'   => $business,
            'sheets'     => $sheets,
            'products'   => $products,
            'modalOpen'  => $modalOpen,
            'encodeTypes' => self::ENCODE_TYPES,
            'pageSizes'   => self::PAGE_SIZES,
            'orientations' => self::ORIENTATIONS,
            'labelTypes'  => self::LABEL_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'product_id'    => ['required', 'integer', 'exists:products,id'],
            'encode_type'   => ['required', 'string', 'in:' . implode(',', self::ENCODE_TYPES)],
            'page_size'     => ['required', 'string', 'in:' . implode(',', self::PAGE_SIZES)],
            'page_orientation' => ['required', 'string', 'in:' . implode(',', self::ORIENTATIONS)],
            'label_type'    => ['required', 'string', 'in:' . implode(',', self::LABEL_TYPES)],
            'labels_per_page' => ['required', 'integer', 'min:1', 'max:100'],
            'total_quantity'  => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        // Ensure product belongs to this business
        abort_unless(
            $business->products()->whereKey($data['product_id'])->exists(),
            403,
        );

        $sheet = $this->service->create($business, $data);

        return redirect()->route('product.barcodes.show', $sheet)->with('status', 'Barcode sheet created.');
    }

    public function show(Request $request, ProductBarcodeSheet $sheet): View|RedirectResponse
    {
        $business = $this->requireSheet($request, $sheet);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $sheet->loadMissing('product');

        return view('product::barcodes.show', [
            'business' => $business,
            'sheet'    => $sheet,
        ]);
    }

    public function destroy(Request $request, ProductBarcodeSheet $sheet): RedirectResponse
    {
        $business = $this->requireSheet($request, $sheet);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->service->delete($sheet);

        return redirect()->route('product.barcodes.index')->with('status', 'Barcode sheet deleted.');
    }

    private function requireSheet(Request $request, ProductBarcodeSheet $sheet): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->service->sheetForBusiness($business, $sheet) instanceof ProductBarcodeSheet, 404);

        return $business;
    }
}
