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
}
