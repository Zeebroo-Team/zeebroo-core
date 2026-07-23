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

        $branchId   = $request->query('branch');
        $branchId   = is_numeric($branchId) ? (int) $branchId : null;
        $branchPosSeparate     = (bool) get_settings('business.branch_pos_separate', false, $business);
        $branchProductSeparate = (bool) get_settings('business.branch_product_separate', false, $business);
        $effectiveBranchId = $branchPosSeparate ? $branchId : null;

        return response()->json([
            'data' => $this->api->formatCategories(
                $this->catalog->posCategories($business, $effectiveBranchId, $branchProductSeparate)
            ),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $search      = (string) $request->query('q', '');
        $categoryId  = $request->query('category');
        $categoryId  = is_numeric($categoryId) ? (int) $categoryId : null;
        $branchId    = $request->query('branch') ?? $request->header('X-Branch-Id');
        $branchId    = is_numeric($branchId) ? (int) $branchId : null;
        $page        = max(1, (int) $request->query('page', 1));
        $perPage     = max(1, min(100, (int) $request->query('per_page', 40)));
        $stockStatus = in_array($request->query('stock_status'), ['in_stock', 'low_stock', 'out_of_stock'], true)
            ? $request->query('stock_status') : null;
        $brandId     = $request->query('brand_id');
        $brandId     = is_numeric($brandId) ? (int) $brandId : null;
        $sort        = in_array($request->query('sort'), ['name_asc', 'name_desc', 'price_asc', 'price_desc', 'stock_asc', 'stock_desc'], true)
            ? $request->query('sort') : 'name_asc';

        $branchPosSeparate     = (bool) get_settings('business.branch_pos_separate', false, $business);
        $branchProductSeparate = (bool) get_settings('business.branch_product_separate', false, $business);
        $branchStockSeparate   = (bool) get_settings('business.branch_stock_separate', false, $business);
        $effectiveBranchId = $branchPosSeparate ? $branchId : null;

        $paginated = $this->catalog->productCardsForPos(
            $business,
            $search !== '' ? $search : null,
            $categoryId,
            $page,
            $perPage,
            $effectiveBranchId,
            $branchProductSeparate,
            $branchStockSeparate,
            $stockStatus,
            $brandId,
            $sort,
        );

        return response()->json([
            'data' => $paginated['data'],
            'meta' => $paginated['meta'],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $branchId = $request->query('branch') ?? $request->header('X-Branch-Id');
        $branchId = is_numeric($branchId) ? (int) $branchId : null;
        $branchStockSeparate = (bool) get_settings('business.branch_stock_separate', false, $business);

        $product = $business->products()->where('id', $id)->first();

        if ($product === null) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $product->loadMissing([
            'productUnit', 'imageFile', 'categories', 'brands',
            'productImages.file', 'sellingUnits', 'stockLayers',
            'bundleItems.itemProduct',
        ]);

        $card = $this->catalog->productCardForProduct($product, $branchId, $branchStockSeparate);
        $images = $product->productImages->map(fn ($img) => [
            'id'  => $img->id,
            'url' => $img->file?->publicUrl() ?? $img->imageUrl ?? null,
        ])->filter(fn ($i) => $i['url'] !== null)->values();

        // Fall back to the primary image if no product images
        if ($images->isEmpty() && $product->imageUrl()) {
            $images = collect([['id' => null, 'url' => $product->imageUrl()]]);
        }

        $totalStock = $product->stockLayers->sum('quantity_remaining');

        return response()->json([
            'data' => array_merge($card, [
                'description'        => $product->description,
                'is_active'          => $product->is_active,
                'is_bundle'          => $product->is_bundle,
                'has_warranty'       => (bool) $product->has_warranty,
                'track_expiry'       => (bool) $product->track_expiry,
                'courier_delivery'   => (bool) $product->courier_delivery,
                'loyalty_redeemable' => (bool) $product->loyalty_redeemable,
                'unit_price'   => (float) $product->unit_price,
                'cost_price'   => $product->stockLayers->isNotEmpty()
                    ? (float) $product->stockLayers->first()->unit_cost
                    : null,
                'total_stock'  => (float) $totalStock,
                'category'      => $product->categories->first()
                    ? ['id' => $product->categories->first()->id, 'name' => $product->categories->first()->name]
                    : null,
                'brand'         => $product->brands->first()
                    ? ['id' => $product->brands->first()->id, 'name' => $product->brands->first()->name]
                    : null,
                'category_ids'        => $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'brand_ids'           => $product->brands->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'product_unit_id'     => $product->product_unit_id,
                'file_manager_file_id'=> $product->file_manager_file_id,
                'bundle_items'        => $product->bundleItems->map(fn ($bi) => [
                    'product_id' => $bi->item_product_id,
                    'name'       => $bi->itemProduct?->name ?? '',
                    'quantity'   => (float) $bi->quantity,
                ])->values()->all(),
                'unit_name'    => $product->productUnit?->name ?? $product->unit,
                'images'       => $images,
                'selling_units' => $product->sellingUnits->map(fn ($su) => [
                    'id'         => $su->id,
                    'label'      => $su->label,
                    'multiplier' => (float) $su->multiplier,
                    'sell_price' => (float) $su->sell_price,
                    'sku'        => $su->sku,
                    'is_active'  => $su->is_active,
                ]),
                'stock_layers' => $product->stockLayers->map(fn ($l) => [
                    'id'                 => $l->id,
                    'quantity_remaining' => (float) $l->quantity_remaining,
                    'unit_cost'          => (float) $l->unit_cost,
                    'received_at'        => $l->received_at?->toDateString(),
                ]),
                'created_at'   => $product->created_at?->toDateTimeString(),
                'updated_at'   => $product->updated_at?->toDateTimeString(),
            ]),
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
