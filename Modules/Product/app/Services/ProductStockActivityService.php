<?php

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductStockLayerService;
use Modules\Purchase\Models\GoodsReceiveNoteItem;
use Modules\Purchase\Models\PurchaseItem;

class ProductStockActivityService
{
    public function __construct(
        private readonly ProductStockLayerService $stockLayers,
    ) {
    }

    /**
     * @return array{
     *     summary: array<string, float|int>,
     *     purchaseItems: EloquentCollection<int, PurchaseItem>,
     *     grnItems: EloquentCollection<int, GoodsReceiveNoteItem>,
     *     stockLayers: EloquentCollection<int, ProductStockLayer>,
     * }
     */
    public function forProduct(Product $product): array
    {
        $businessId = (int) $product->business_id;

        $purchaseItems = PurchaseItem::query()
            ->where('product_id', $product->id)
            ->whereHas('purchase', fn ($query) => $query->where('business_id', $businessId))
            ->with(['purchase.supplier', 'goodsReceiveNoteItems'])
            ->get()
            ->sortByDesc(fn (PurchaseItem $item) => $item->purchase?->purchase_date?->timestamp ?? 0)
            ->values();

        $grnItems = GoodsReceiveNoteItem::query()
            ->where('product_id', $product->id)
            ->whereHas('goodsReceiveNote', fn ($query) => $query->where('business_id', $businessId))
            ->with(['goodsReceiveNote.purchase', 'purchaseItem'])
            ->get()
            ->sortByDesc(fn (GoodsReceiveNoteItem $item) => $item->goodsReceiveNote?->received_date?->timestamp ?? 0)
            ->values();

        $totalOrdered = round((float) $purchaseItems->sum(fn (PurchaseItem $item) => (float) $item->quantity), 3);
        $totalReceived = round((float) $grnItems->sum(fn (GoodsReceiveNoteItem $item) => (float) $item->quantity_received), 3);
        $stockAppliedTotal = round((float) $grnItems
            ->filter(fn (GoodsReceiveNoteItem $item) => (bool) $item->goodsReceiveNote?->stock_applied)
            ->sum(fn (GoodsReceiveNoteItem $item) => (float) $item->quantity_received), 3);

        $stockLayerSummary = $this->stockLayers->summarizeForProduct($product);
        $stockLayers = $this->stockLayers->listForProduct($product);

        return [
            'summary' => [
                'current_stock' => round((float) $product->stock_quantity, 3),
                'total_ordered' => $totalOrdered,
                'total_received' => $totalReceived,
                'stock_applied_total' => $stockAppliedTotal,
                'pending_receive' => max(0.0, round($totalOrdered - $totalReceived, 3)),
                'purchase_lines_count' => $purchaseItems->count(),
                'grn_lines_count' => $grnItems->count(),
                'stock_layers_count' => $stockLayerSummary['layers_count'],
                'stock_layers_remaining' => $stockLayerSummary['layers_remaining'],
                'stock_layers_value_cost' => $stockLayerSummary['layers_value_cost'],
                'stock_layers_value_sell' => $stockLayerSummary['layers_value_sell'],
            ],
            'purchaseItems' => $purchaseItems,
            'grnItems' => $grnItems,
            'stockLayers' => $stockLayers,
        ];
    }
}
