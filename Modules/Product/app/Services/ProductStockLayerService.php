<?php

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\GoodsReceiveNoteItem;

class ProductStockLayerService
{
    public function defaultSellingUnitPrice(Business $business, Product $product, float $unitCost): ?float
    {
        if ($unitCost <= 0) {
            return $product->unit_price !== null ? round((float) $product->unit_price, 2) : null;
        }

        $markup = (float) get_settings('product.stock_selling_markup_percent', 25, $business);
        if ($markup > 0) {
            return round($unitCost * (1 + ($markup / 100)), 2);
        }

        if ($product->unit_price !== null && (float) $product->unit_price > $unitCost) {
            return round((float) $product->unit_price, 2);
        }

        return round($unitCost, 2);
    }

    public function resolveSellingUnitPrice(
        Business $business,
        Product $product,
        float $unitCost,
        mixed $provided,
    ): ?float {
        if ($provided !== null && $provided !== '') {
            return round(max(0, (float) $provided), 2);
        }

        return $this->defaultSellingUnitPrice($business, $product, $unitCost);
    }

    public function applyFromGrn(GoodsReceiveNote $grn): void
    {
        if ($grn->stock_applied) {
            return;
        }

        $grn->load(['items.product', 'business']);

        foreach ($grn->items as $item) {
            $product = $item->product;
            if (!$product instanceof Product) {
                continue;
            }

            $this->createLayerFromGrnItem($item, $grn, $item->selling_unit_price !== null ? (float) $item->selling_unit_price : null);

            $product->stock_quantity = round((float) $product->stock_quantity + (float) $item->quantity_received, 3);
            $product->save();
        }

        $grn->stock_applied = true;
        $grn->save();
    }

    public function createLayerFromGrnItem(
        GoodsReceiveNoteItem $item,
        GoodsReceiveNote $grn,
        ?float $sellingUnitPrice = null,
    ): ProductStockLayer {
        $product = $item->product;
        if (!$product instanceof Product) {
            throw ValidationException::withMessages([
                'items' => 'Cannot create stock layer without a product.',
            ]);
        }

        $business = $grn->business ?? $product->business;
        $resolvedSell = $this->resolveSellingUnitPrice(
            $business,
            $product,
            (float) $item->unit_cost,
            $sellingUnitPrice ?? $item->selling_unit_price,
        );

        if ($item->selling_unit_price === null && $resolvedSell !== null) {
            $item->selling_unit_price = $resolvedSell;
            $item->save();
        }

        return ProductStockLayer::query()->updateOrCreate(
            ['goods_receive_note_item_id' => $item->id],
            [
                'business_id' => (int) $grn->business_id,
                'branch_id' => $grn->branch_id ?? null,
                'product_id' => (int) $item->product_id,
                'quantity_received' => (float) $item->quantity_received,
                'quantity_remaining' => (float) $item->quantity_received,
                'unit_cost' => (float) $item->unit_cost,
                'selling_unit_price' => $resolvedSell,
                'received_at' => $grn->received_date,
            ],
        );
    }

    public function reverseForGrn(GoodsReceiveNote $grn): void
    {
        if (!$grn->stock_applied) {
            return;
        }

        $grn->load('items.product');

        foreach ($grn->items as $item) {
            ProductStockLayer::query()
                ->where('goods_receive_note_item_id', $item->id)
                ->delete();

            $product = $item->product;
            if (!$product instanceof Product) {
                continue;
            }

            $product->stock_quantity = max(
                0.0,
                round((float) $product->stock_quantity - (float) $item->quantity_received, 3),
            );
            $product->save();
        }

        $grn->stock_applied = false;
        $grn->save();
    }

    /**
     * @return EloquentCollection<int, ProductStockLayer>
     */
    public function listForProduct(Product $product): EloquentCollection
    {
        $this->backfillFromAppliedGrns($product);

        return ProductStockLayer::query()
            ->where('product_id', $product->id)
            ->where('business_id', $product->business_id)
            ->with(['goodsReceiveNoteItem.goodsReceiveNote.purchase', 'branch'])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get();
    }

    public function backfillFromAppliedGrns(Product $product): void
    {
        $existingItemIds = ProductStockLayer::query()
            ->where('product_id', $product->id)
            ->whereNotNull('goods_receive_note_item_id')
            ->pluck('goods_receive_note_item_id');

        GoodsReceiveNoteItem::query()
            ->where('product_id', $product->id)
            ->whereNotIn('id', $existingItemIds)
            ->whereHas('goodsReceiveNote', fn ($query) => $query
                ->where('business_id', $product->business_id)
                ->where('stock_applied', true))
            ->with(['goodsReceiveNote', 'product'])
            ->orderBy('id')
            ->each(function (GoodsReceiveNoteItem $item): void {
                $grn = $item->goodsReceiveNote;
                if ($grn === null) {
                    return;
                }

                $this->createLayerFromGrnItem($item, $grn, $item->selling_unit_price !== null ? (float) $item->selling_unit_price : null);
            });
    }

    public function createManualLayer(
        Business $business,
        Product $product,
        float $quantity,
        float $unitCost,
        ?float $sellingUnitPrice = null,
    ): ProductStockLayer {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'stock_quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        $unitCost = round(max(0, $unitCost), 2);
        $sell = $this->resolveSellingUnitPrice($business, $product, $unitCost, $sellingUnitPrice);

        $layer = ProductStockLayer::query()->create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'goods_receive_note_item_id' => null,
            'quantity_received' => round($quantity, 3),
            'quantity_remaining' => round($quantity, 3),
            'unit_cost' => $unitCost,
            'selling_unit_price' => $sell,
            'received_at' => now()->toDateString(),
        ]);

        $product->stock_quantity = round((float) $product->stock_quantity + $quantity, 3);
        $product->save();

        return $layer;
    }

    public function updateSellingPrice(ProductStockLayer $layer, float $sellingUnitPrice): ProductStockLayer
    {
        $layer->selling_unit_price = round(max(0, $sellingUnitPrice), 2);
        $layer->save();

        if ($layer->goods_receive_note_item_id) {
            GoodsReceiveNoteItem::query()
                ->whereKey($layer->goods_receive_note_item_id)
                ->update(['selling_unit_price' => $layer->selling_unit_price]);
        }

        return $layer->refresh();
    }

    /**
     * @return array{layers_count: int, layers_remaining: float, layers_value_cost: float, layers_value_sell: float}
     */
    public function summarizeForProduct(Product $product): array
    {
        $layers = $this->listForProduct($product);

        $remaining = round((float) $layers->sum(fn (ProductStockLayer $layer) => (float) $layer->quantity_remaining), 3);
        $costValue = round((float) $layers->sum(fn (ProductStockLayer $layer) => (float) $layer->quantity_remaining * (float) $layer->unit_cost), 2);
        $sellValue = round((float) $layers->sum(function (ProductStockLayer $layer) {
            if ($layer->selling_unit_price === null) {
                return 0.0;
            }

            return (float) $layer->quantity_remaining * (float) $layer->selling_unit_price;
        }), 2);

        return [
            'layers_count' => $layers->count(),
            'layers_remaining' => $remaining,
            'layers_value_cost' => $costValue,
            'layers_value_sell' => $sellValue,
        ];
    }
}
