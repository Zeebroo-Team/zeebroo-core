<?php

namespace Modules\Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductStockLayerService;

class SaleStockConsumptionService
{
    private const QTY_TOLERANCE = 0.0001;

    public function __construct(
        private readonly ProductStockLayerService $stockLayers,
    ) {
    }

    /**
     * @return list<array{
     *     product_stock_layer_id: ?int,
     *     quantity: float,
     *     unit_cost: ?float,
     *     unit_sell_price: float,
     * }>
     */
    public function consumeFromLayer(Product $product, int $layerId, float $quantity): array
    {
        if ($quantity <= self::QTY_TOLERANCE) {
            throw ValidationException::withMessages([
                'items' => 'Quantity must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($product, $layerId, $quantity) {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $product->loadMissing('business');

            $layer = ProductStockLayer::query()
                ->whereKey($layerId)
                ->where('product_id', $product->id)
                ->where('business_id', $product->business_id)
                ->lockForUpdate()
                ->first();

            if ($layer === null) {
                throw ValidationException::withMessages([
                    'items' => 'The selected stock batch is not available for '.$product->name.'.',
                ]);
            }

            $available = (float) $layer->quantity_remaining;
            if ($available + self::QTY_TOLERANCE < $quantity) {
                throw ValidationException::withMessages([
                    'items' => 'Not enough stock in the selected batch for '.$product->name
                        .'. Available in batch: '.number_format($available, 3, '.', '').'.',
                ]);
            }

            $sellPrice = $this->resolveLayerSellPrice($product, $layer);
            $layer->quantity_remaining = round(max(0, $available - $quantity), 3);
            $layer->save();

            $product->stock_quantity = max(0.0, round((float) $product->stock_quantity - $quantity, 3));
            $product->save();

            return [[
                'product_stock_layer_id' => (int) $layer->id,
                'quantity' => round($quantity, 3),
                'unit_cost' => round((float) $layer->unit_cost, 2),
                'unit_sell_price' => $sellPrice,
            ]];
        });
    }

    public function consumeFifo(Product $product, float $quantity): array
    {
        if ($quantity <= self::QTY_TOLERANCE) {
            throw ValidationException::withMessages([
                'items' => 'Quantity must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($product, $quantity) {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $product->loadMissing('business');

            $layers = ProductStockLayer::query()
                ->where('product_id', $product->id)
                ->where('business_id', $product->business_id)
                ->where('quantity_remaining', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($layers->isEmpty()) {
                return $this->consumeWithoutLayers($product, $quantity);
            }

            $remaining = $quantity;
            $allocations = [];

            foreach ($layers as $layer) {
                if ($remaining <= self::QTY_TOLERANCE) {
                    break;
                }

                $available = (float) $layer->quantity_remaining;
                if ($available <= self::QTY_TOLERANCE) {
                    continue;
                }

                $take = min($remaining, $available);
                $sellPrice = $this->resolveLayerSellPrice($product, $layer);

                $allocations[] = [
                    'product_stock_layer_id' => (int) $layer->id,
                    'quantity' => round($take, 3),
                    'unit_cost' => round((float) $layer->unit_cost, 2),
                    'unit_sell_price' => $sellPrice,
                ];

                $layer->quantity_remaining = round($available - $take, 3);
                $layer->save();
                $remaining = round($remaining - $take, 3);
            }

            if ($remaining > self::QTY_TOLERANCE) {
                throw ValidationException::withMessages([
                    'items' => 'Insufficient stock for '.$product->name.'. Available: '.number_format((float) $product->stock_quantity, 3, '.', '').'.',
                ]);
            }

            $product->stock_quantity = max(0.0, round((float) $product->stock_quantity - $quantity, 3));
            $product->save();

            return $allocations;
        });
    }

    /**
     * @return list<array{
     *     product_stock_layer_id: ?int,
     *     quantity: float,
     *     unit_cost: ?float,
     *     unit_sell_price: float,
     * }>
     */
    private function consumeWithoutLayers(Product $product, float $quantity): array
    {
        if ((float) $product->stock_quantity + self::QTY_TOLERANCE < $quantity) {
            throw ValidationException::withMessages([
                'items' => 'Insufficient stock for '.$product->name.'.',
            ]);
        }

        $sellPrice = $product->unit_price !== null
            ? round((float) $product->unit_price, 2)
            : 0.0;

        $product->stock_quantity = max(0.0, round((float) $product->stock_quantity - $quantity, 3));
        $product->save();

        return [[
            'product_stock_layer_id' => null,
            'quantity' => round($quantity, 3),
            'unit_cost' => null,
            'unit_sell_price' => $sellPrice,
        ]];
    }

    public function restoreSaleItem(
        ?int $layerId,
        float $quantity,
        Product $product,
    ): void {
        if ($quantity <= self::QTY_TOLERANCE) {
            return;
        }

        $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

        if ($layerId !== null) {
            $layer = ProductStockLayer::query()
                ->whereKey($layerId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($layer !== null) {
                $layer->quantity_remaining = round((float) $layer->quantity_remaining + $quantity, 3);
                $layer->save();
            }
        }

        $product->stock_quantity = round((float) $product->stock_quantity + $quantity, 3);
        $product->save();
    }

    private function resolveLayerSellPrice(Product $product, ProductStockLayer $layer): float
    {
        if ($layer->selling_unit_price !== null) {
            return round((float) $layer->selling_unit_price, 2);
        }

        $product->loadMissing('business');

        $resolved = $this->stockLayers->defaultSellingUnitPrice(
            $product->business,
            $product,
            (float) $layer->unit_cost,
        );

        return $resolved ?? round((float) $layer->unit_cost, 2);
    }
}
