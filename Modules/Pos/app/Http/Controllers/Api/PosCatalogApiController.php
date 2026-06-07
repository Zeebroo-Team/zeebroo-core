<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosCatalogService;
use Modules\Pos\Services\PosOnlineApiService;

class PosCatalogApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PosCatalogService $catalog,
        private readonly PosOnlineApiService $api,
    ) {
    }

    public function categories(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        return response()->json([
            'data' => $this->api->formatCategories($this->catalog->posCategories($business)),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $search = (string) $request->query('q', '');
        $categoryId = $request->query('category');
        $categoryId = is_numeric($categoryId) ? (int) $categoryId : null;

        $page    = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 40)));

        $paginated = $this->catalog->productCardsForPos(
            $business,
            $search !== '' ? $search : null,
            $categoryId,
            $page,
            $perPage,
        );

        return response()->json([
            'data' => $paginated['data'],
            'meta' => $paginated['meta'],
        ]);
    }

    public function productBySku(Request $request, string $sku): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $product = $this->catalog->findSellableProductBySku($business, $sku);
        if ($product === null) {
            return response()->json([
                'message' => 'No product found for SKU: '.$sku,
            ], 404);
        }

        $product->loadMissing(['productUnit', 'imageFile', 'categories', 'business']);

        return response()->json([
            'data' => $this->catalog->productCardForProduct($product),
        ]);
    }
}
