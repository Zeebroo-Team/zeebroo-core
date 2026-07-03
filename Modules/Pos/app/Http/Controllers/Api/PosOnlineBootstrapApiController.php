<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosOnlineApiService;

class PosOnlineBootstrapApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PosOnlineApiService $api,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $search      = (string) $request->query('q', '');
        $categoryId  = $request->query('category');
        $categoryId  = is_numeric($categoryId) ? (int) $categoryId : null;
        $page        = max(1, (int) $request->query('page', 1));
        $perPage     = max(1, min(100, (int) $request->query('per_page', 40)));
        $branchId    = $request->query('branch') ?? $request->header('X-Branch-Id');
        $branchId    = is_numeric($branchId) ? (int) $branchId : null;
        $stockStatus = in_array($request->query('stock_status'), ['in_stock', 'low_stock', 'out_of_stock'], true)
            ? $request->query('stock_status') : null;
        $brandId     = $request->query('brand_id');
        $brandId     = is_numeric($brandId) ? (int) $brandId : null;
        $sort        = in_array($request->query('sort'), ['name_asc', 'name_desc', 'price_asc', 'price_desc', 'stock_asc', 'stock_desc', 'recent_sales'], true)
            ? $request->query('sort') : 'name_asc';
        $recentSales = filter_var($request->query('recent_sales', false), FILTER_VALIDATE_BOOLEAN);

        return response()->json([
            'data' => $this->api->bootstrap(
                $business,
                $request->user(),
                $search !== '' ? $search : null,
                $categoryId,
                $page,
                $perPage,
                $branchId,
                $stockStatus,
                $brandId,
                $sort,
                $recentSales,
            ),
        ]);
    }
}
