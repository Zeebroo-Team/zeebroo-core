<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Product\Http\Controllers\Concerns\ResolvesProductBusiness;
use Modules\Product\Models\ProductDiscount;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductDiscountService;

class ProductDiscountController extends Controller
{
    use ResolvesProductBusiness;

    public function __construct(private readonly ProductDiscountService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $discounts = $business->productDiscounts()
            ->with(['product', 'sellingUnit'])
            ->orderByDesc('created_at')
            ->get();

        // Products with selling units for the create form
        $products = $business->products()
            ->where('is_active', true)
            ->with(['sellingUnits'])
            ->select('id', 'name', 'sku', 'unit_price')
            ->orderBy('name')
            ->get();

        // Latest unit_cost per product from stock layers
        $latestCosts = ProductStockLayer::where('business_id', $business->id)
            ->whereIn('product_id', $products->pluck('id'))
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        // Build JS-safe product map: id → {name, sku, unit_price, cost, selling_units[]}
        $productsMap = $products->mapWithKeys(function ($p) use ($latestCosts) {
            $cost = $latestCosts->has($p->id)
                ? (float) $latestCosts[$p->id]->unit_cost
                : null;

            return [$p->id => [
                'id'         => $p->id,
                'name'       => $p->name,
                'sku'        => $p->sku,
                'unit_price' => (float) $p->unit_price,
                'cost'       => $cost,
                'selling_units' => $p->sellingUnits->map(fn ($su) => [
                    'id'            => $su->id,
                    'label'         => $su->label,
                    'selling_price' => (float) $su->displaySellingPrice((float) $p->unit_price),
                ])->values(),
            ]];
        });

        $viewErrors = $request->session()->get('errors');
        $modalOpen  = $discounts->isNotEmpty()
            && $viewErrors !== null
            && $viewErrors->any()
            && ! $viewErrors->has('discount');

        return view('product::discounts.index', [
            'business'    => $business,
            'discounts'   => $discounts,
            'products'    => $products,
            'productsMap' => $productsMap,
            'modalOpen'   => $modalOpen,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'product_id'              => ['required', 'integer', 'exists:products,id'],
            'product_selling_unit_id' => ['nullable', 'integer', 'exists:product_selling_units,id'],
            'discount_type'           => ['required', 'string', 'in:flat,percentage'],
            'discount_value'          => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'starts_at'               => ['nullable', 'date'],
            'ends_at'                 => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        abort_unless($business->products()->whereKey($data['product_id'])->exists(), 403);

        if (! empty($data['product_selling_unit_id'])) {
            abort_unless(
                $business->products()
                    ->whereKey($data['product_id'])
                    ->whereHas('sellingUnits', fn ($q) => $q->whereKey($data['product_selling_unit_id']))
                    ->exists(),
                403,
            );
        } else {
            $data['product_selling_unit_id'] = null;
        }

        $data['is_active'] = $request->boolean('is_active', true);

        $this->service->create($business, $data);

        return redirect()->route('product.discounts.index')->with('status', 'Discount saved.');
    }

    public function destroy(Request $request, ProductDiscount $discount): RedirectResponse
    {
        $business = $this->requireDiscount($request, $discount);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->service->delete($discount);

        return redirect()->route('product.discounts.index')->with('status', 'Discount deleted.');
    }

    private function requireDiscount(Request $request, ProductDiscount $discount): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->service->discountForBusiness($business, $discount) instanceof ProductDiscount, 404);

        return $business;
    }
}
