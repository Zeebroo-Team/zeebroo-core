<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosBarcodeProductService;

/**
 * Desktop Barcode Generator — product picker data.
 */
class PosBarcodeApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly PosBarcodeProductService $barcodeProducts)
    {
    }

    /** GET online/barcodes/products?q= — every sellable product (name, SKU, stock, label price), unpaginated. */
    public function products(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $branchId = $request->query('branch') ?? $request->header('X-Branch-Id');
        $branchId = is_numeric($branchId) ? (int) $branchId : null;
        $branchPosSeparate = (bool) get_settings('business.branch_pos_separate', false, $business);

        $data = $this->barcodeProducts->all(
            $business,
            $branchPosSeparate ? $branchId : null,
            (bool) get_settings('business.branch_product_separate', false, $business),
            (bool) get_settings('business.branch_stock_separate', false, $business),
            trim((string) $request->query('q', '')) ?: null,
        );

        return response()->json([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    /** POST online/barcodes/generate-skus {product_ids: int[]} — give products without a barcode a generated SKU. */
    public function generateSkus(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_products');

        $validated = $request->validate([
            'product_ids'   => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['integer', 'distinct'],
        ]);

        $data = $this->barcodeProducts->assignMissingSkus(
            $business,
            array_map('intval', $validated['product_ids']),
        );

        return response()->json([
            'data' => $data,
            'meta' => ['generated' => count(array_filter($data, fn ($r) => $r['generated']))],
        ]);
    }
}
