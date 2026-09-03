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
    public function consumeFromLayer(
        Product $product,
        int $layerId,
        float $quantity,
        ?int $branchId = null,
        bool $branchStockSeparate = false,
    ): array {
        if ($quantity <= self::QTY_TOLERANCE) {
            throw ValidationException::withMessages([
                'items' => 'Quantity must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($product, $layerId, $quantity, $branchId, $branchStockSeparate) {
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

            if ($available + self::QTY_TOLERANCE >= $quantity) {
                // Requested layer has enough stock — fast path
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
            }

            // Requested layer is under-stocked — drain it first, then FIFO for the remainder
            $allocations = [];
            $remaining = $quantity;

            if ($available > self::QTY_TOLERANCE) {
                $sellPrice = $this->resolveLayerSellPrice($product, $layer);
                $allocations[] = [
                    'product_stock_layer_id' => (int) $layer->id,
                    'quantity' => round($available, 3),
                    'unit_cost' => round((float) $layer->unit_cost, 2),
                    'unit_sell_price' => $sellPrice,
                ];
                $layer->quantity_remaining = 0;
                $layer->save();
                $remaining = round($remaining - $available, 3);
            }

            if ($remaining > self::QTY_TOLERANCE) {
                $fifoLayers = ProductStockLayer::query()
                    ->where('product_id', $product->id)
                    ->where('business_id', $product->business_id)
                    ->where('quantity_remaining', '>', 0)
                    ->where('id', '!=', $layerId)
                    ->when(
                        $branchStockSeparate && $branchId !== null,
                        fn ($q) => $q->where('branch_id', $branchId),
                    )
                    ->orderBy('received_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($fifoLayers as $fl) {
                    if ($remaining <= self::QTY_TOLERANCE) {
                        break;
                    }
                    $avail = (float) $fl->quantity_remaining;
                    if ($avail <= self::QTY_TOLERANCE) {
                        continue;
                    }
                    $take = min($remaining, $avail);
                    $sp = $this->resolveLayerSellPrice($product, $fl);
                    $allocations[] = [
                        'product_stock_layer_id' => (int) $fl->id,
                        'quantity' => round($take, 3),
                        'unit_cost' => round((float) $fl->unit_cost, 2),
                        'unit_sell_price' => $sp,
                    ];
                    $fl->quantity_remaining = round($avail - $take, 3);
                    $fl->save();
                    $remaining = round($remaining - $take, 3);
                }
            }

            if ($remaining > self::QTY_TOLERANCE) {
                throw ValidationException::withMessages([
                    'items' => 'Insufficient stock for '.$product->name
                        .'. Available: '.number_format((float) $product->stock_quantity, 3, '.', '').'.',
                ]);
            }

            $product->stock_quantity = max(0.0, round((float) $product->stock_quantity - $quantity, 3));
            $product->save();

            return $allocations;
        });
    }

    public function consumeFifo(
        Product $product,
        float $quantity,
        ?int $branchId = null,
        bool $branchStockSeparate = false,
    ): array {
        if ($quantity <= self::QTY_TOLERANCE) {
            throw ValidationException::withMessages([
                'items' => 'Quantity must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($product, $quantity, $branchId, $branchStockSeparate) {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $product->loadMissing('business');

            $layers = ProductStockLayer::query()
                ->where('product_id', $product->id)
                ->where('business_id', $product->business_id)
                ->where('quantity_remaining', '>', 0)
                ->when(
                    $branchStockSeparate && $branchId !== null,
                    fn ($q) => $q->where('branch_id', $branchId),
                )
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($layers->isEmpty()) {
                // If branch-scoped stock is empty but the product does have layers
                // elsewhere (a different branch), don't fall back to the cross-branch
                // stock_quantity counter — that would sell/price stock that isn't
                // actually assigned to this branch.
                if ($branchStockSeparate && $branchId !== null) {
                    $hasLayersElsewhere = ProductStockLayer::query()
                        ->where('product_id', $product->id)
                        ->where('business_id', $product->business_id)
                        ->where('quantity_remaining', '>', 0)
                        ->exists();

                    if ($hasLayersElsewhere) {
                        throw ValidationException::withMessages([
                            'items' => 'Insufficient stock for '.$product->name.' in this branch.',
                        ]);
                    }
                }

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
                $branchSuffix = $branchStockSeparate && $branchId !== null ? ' in this branch' : '';
                throw ValidationException::withMessages([
                    'items' => 'Insufficient stock for '.$product->name.$branchSuffix
                        .'. Available: '.number_format((float) $product->stock_quantity, 3, '.', '').'.',
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
