<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosCatalogService;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductStockLayerService;

class PosCatalogApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PosCatalogService $catalog,
        private readonly PosOnlineApiService $api,
        private readonly ProductStockLayerService $stockLayerService,
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

        $data = $paginated['data'];
        $this->enrichWithReservedStock($data, (int) $business->id);

        return response()->json([
            'data' => $data,
            'meta' => $paginated['meta'],
        ]);
    }

    private function enrichWithReservedStock(array &$products, int $businessId): void
    {
        $ids = array_column($products, 'id');
        if (empty($ids)) return;

        // Sum quantities on open invoices (draft / sent)
        $invReserved = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.business_id', $businessId)
            ->whereIn('invoices.status', ['draft', 'sent'])
            ->whereIn('invoice_items.product_id', $ids)
            ->whereNotNull('invoice_items.product_id')
            ->groupBy('invoice_items.product_id')
            ->selectRaw('invoice_items.product_id, SUM(invoice_items.quantity) as qty')
            ->pluck('qty', 'product_id');

        // Sum quantities on active quotations (draft / sent / accepted)
        $qtReserved = DB::table('quotation_items')
            ->join('quotations', 'quotation_items.quotation_id', '=', 'quotations.id')
            ->where('quotations.business_id', $businessId)
            ->whereIn('quotations.status', ['draft', 'sent', 'accepted'])
            ->whereIn('quotation_items.product_id', $ids)
            ->whereNotNull('quotation_items.product_id')
            ->groupBy('quotation_items.product_id')
            ->selectRaw('quotation_items.product_id, SUM(quotation_items.quantity) as qty')
            ->pluck('qty', 'product_id');

        foreach ($products as &$p) {
            $id       = $p['id'];
            $reserved = (float)($invReserved[$id] ?? 0) + (float)($qtReserved[$id] ?? 0);
            $stock    = (float)($p['stock_quantity'] ?? 0);
            $p['reserved_qty']    = round($reserved, 3);
            $p['available_stock'] = round(max(0.0, $stock - $reserved), 3);
        }
        unset($p);
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
                'delivery_methods'   => $product->delivery_methods ?? [],
                'loyalty_redeemable' => (bool) $product->loyalty_redeemable,
                'unit_price'   => (float) $product->unit_price,
                'cost_price'   => $product->cost_price !== null
                    ? (float) $product->cost_price
                    : ($product->stockLayers->isNotEmpty() ? (float) $product->stockLayers->first()->unit_cost : null),
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
                    'batch_sku'          => $l->batch_sku,
                    'quantity_remaining' => (float) $l->quantity_remaining,
                    'unit_cost'          => (float) $l->unit_cost,
                    'received_at'        => $l->received_at?->toDateString(),
                ]),
                'created_at'   => $product->created_at?->toDateTimeString(),
                'updated_at'   => $product->updated_at?->toDateTimeString(),
            ]),
        ]);
    }

    public function stockHistory(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $product = $business->products()->where('id', $id)->first();
        if ($product === null) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $layers = $product->stockLayers()
            ->with([
                'goodsReceiveNoteItem.goodsReceiveNote.purchase.supplier',
            ])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get();

        $data = $layers->map(function ($layer) {
            $grnItem = $layer->goodsReceiveNoteItem;
            $grn     = $grnItem?->goodsReceiveNote;
            $po      = $grn?->purchase;

            if ($grnItem === null) {
                $sourceType = 'opening';
            } elseif ($po !== null) {
                $sourceType = 'po';
            } else {
                $sourceType = 'grn';
            }

            return [
                'id'                 => $layer->id,
                'batch_sku'          => $layer->batch_sku,
                'received_at'        => $layer->received_at?->toDateString(),
                'quantity_received'  => (float) $layer->quantity_received,
                'quantity_remaining' => (float) $layer->quantity_remaining,
                'unit_cost'          => (float) $layer->unit_cost,
                'selling_unit_price'   => $layer->selling_unit_price !== null ? (float) $layer->selling_unit_price : null,
                'wholesale_unit_price' => $layer->wholesale_unit_price !== null ? (float) $layer->wholesale_unit_price : null,
                'source_type'        => $sourceType,
                'grn_number'         => $grn?->grn_number,
                'grn_reference'      => $grn?->reference,
                'grn_id'             => $grn?->id,
                'po_number'          => $po?->po_number,
                'po_id'              => $po?->id,
                'supplier_name'      => $po?->supplier?->name,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function productBySku(Request $request, string $sku): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        // 1. Try exact product-level SKU
        $product = $this->catalog->findSellableProductBySku($business, $sku);
        if ($product !== null) {
            $product->loadMissing(['productUnit', 'imageFile', 'categories', 'business']);
            return response()->json([
                'data' => $this->catalog->productCardForProduct($product),
            ]);
        }

        // 2. Try batch-level SKU (e.g. PRD-IIW3ZFGK-02)
        $match = $this->catalog->findProductByBatchSku($business, $sku);
        if ($match !== null) {
            /** @var \Modules\Product\Models\Product $product */
            $product = $match['product'];
            $layer   = $match['layer'];
            $product->loadMissing(['productUnit', 'imageFile', 'categories', 'business']);
            $card = $this->catalog->productCardForProduct($product);
            $card['matched_layer_id']    = (int) $layer->id;
            $card['matched_batch_sku']   = $layer->batch_sku;
            $card['layer_qty_remaining'] = (float) $layer->quantity_remaining;
            return response()->json(['data' => $card]);
        }

        return response()->json([
            'message' => 'No product found for SKU: '.$sku,
        ], 404);
    }

    public function backfillBatchSkus(Request $request, int $productId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_products');

        $product = $business->products()->where('id', $productId)->first();
        abort_if($product === null, 404, 'Product not found.');

        $this->stockLayerService->backfillBatchSkus($product);

        $layers = ProductStockLayer::query()
            ->where('product_id', $product->id)
            ->where('business_id', $business->id)
            ->orderBy('id')
            ->get(['id', 'batch_sku', 'quantity_remaining', 'received_at']);

        return response()->json([
            'message' => 'Batch SKUs assigned.',
            'data'    => $layers->map(fn ($l) => [
                'id'        => $l->id,
                'batch_sku' => $l->batch_sku,
            ]),
        ]);
    }

    public function updateLayerSellingPrice(Request $request, int $productId, int $layerId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_products');

        $product = $business->products()->where('id', $productId)->first();
        abort_if($product === null, 404, 'Product not found.');

        $layer = ProductStockLayer::where('id', $layerId)
            ->where('product_id', $product->id)
            ->where('business_id', $business->id)
            ->first();
        abort_if($layer === null, 404, 'Stock layer not found.');

        $validated = $request->validate([
            'selling_unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $layer->selling_unit_price = isset($validated['selling_unit_price'])
            ? round((float) $validated['selling_unit_price'], 2)
            : null;
        $layer->save();

        return response()->json([
            'message'            => 'Selling price updated.',
            'selling_unit_price' => $layer->selling_unit_price !== null ? (float) $layer->selling_unit_price : null,
        ]);
    }

    public function updateLayerWholesalePrice(Request $request, int $productId, int $layerId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_products');

        $product = $business->products()->where('id', $productId)->first();
        abort_if($product === null, 404, 'Product not found.');

        $layer = ProductStockLayer::where('id', $layerId)
            ->where('product_id', $product->id)
            ->where('business_id', $business->id)
            ->first();
        abort_if($layer === null, 404, 'Stock layer not found.');

        $validated = $request->validate([
            'wholesale_unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $layer->wholesale_unit_price = isset($validated['wholesale_unit_price'])
            ? round((float) $validated['wholesale_unit_price'], 2)
            : null;
        $layer->save();

        return response()->json([
            'message'              => 'Wholesale price updated.',
            'wholesale_unit_price' => $layer->wholesale_unit_price !== null ? (float) $layer->wholesale_unit_price : null,
        ]);
    }

    public function updateLayerCostPrice(Request $request, int $productId, int $layerId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_products');

        $product = $business->products()->where('id', $productId)->first();
        abort_if($product === null, 404, 'Product not found.');

        $layer = ProductStockLayer::where('id', $layerId)
            ->where('product_id', $product->id)
            ->where('business_id', $business->id)
            ->first();
        abort_if($layer === null, 404, 'Stock layer not found.');

        $validated = $request->validate([
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $layer->unit_cost = round((float) $validated['unit_cost'], 2);
        $layer->save();

        return response()->json([
            'message'   => 'Cost price updated.',
            'unit_cost' => (float) $layer->unit_cost,
        ]);
    }
}
