<?php

namespace Modules\Pos\Services;

use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductSkuGeneratorService;

/**
 * Lightweight product list for the desktop Barcode Generator picker.
 *
 * Returns every sellable product in one response (no pagination) with just
 * what a label needs: name, SKU, stock and the price printed on the label.
 * A few grouped queries for the whole catalog — no per-product card building.
 */
class PosBarcodeProductService
{
    public function __construct(private readonly ProductSkuGeneratorService $skuGenerator)
    {
    }

    /**
     * Assign a generated SKU (= barcode) to each given product that has none.
     * Products that already have a SKU are left untouched and returned as-is,
     * so a retried request never overwrites a barcode that is already printed.
     *
     * @param  list<int>  $productIds
     * @return list<array{id: int, sku: string, generated: bool}>
     */
    public function assignMissingSkus(Business $business, array $productIds): array
    {
        return DB::transaction(function () use ($business, $productIds) {
            $products = Product::query()
                ->where('business_id', $business->id)
                ->whereIn('id', $productIds)
                ->orderBy('name')
                ->lockForUpdate()
                ->get(['id', 'name', 'sku']);

            return $products->map(function (Product $p) use ($business) {
                if ($p->sku !== null && trim((string) $p->sku) !== '') {
                    return ['id' => (int) $p->id, 'sku' => (string) $p->sku, 'generated' => false];
                }
                // Saved one at a time so the generator sees the previous SKU
                // and keeps the sequence unique within this batch.
                $p->sku = $this->skuGenerator->generate($business, $p, $p->name);
                $p->save();

                return ['id' => (int) $p->id, 'sku' => (string) $p->sku, 'generated' => true];
            })->values()->all();
        });
    }

    /**
     * @return list<array{id: int, name: string, sku: ?string, stock_quantity: float, unit_sell_price: ?float}>
     */
    public function all(
        Business $business,
        ?int $branchId,
        bool $branchProductSeparate,
        bool $branchStockSeparate,
        ?string $search = null,
    ): array {
        $products = Product::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->where('is_bundle', false)
            ->when($branchProductSeparate && $branchId !== null, fn ($q) => $q->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->when($search !== null && $search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'stock_quantity', 'unit_price']);

        $ids = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
        [$fifoPrice, $layerStock, $batches] = $this->layerData($business, $ids, $branchId, $branchStockSeparate);

        return $products->map(function (Product $p) use ($fifoPrice, $layerStock, $batches, $branchId, $branchStockSeparate) {
            $id = (int) $p->id;
            // Same rules as PosCatalogService::posMetaForProduct(): next FIFO
            // layer's selling price, else the product's own price.
            $price = array_key_exists($id, $fifoPrice)
                ? $fifoPrice[$id]
                : ($p->unit_price !== null ? (float) $p->unit_price : null);
            $stock = $branchStockSeparate && $branchId !== null
                ? ($layerStock[$id] ?? 0.0)
                : (float) $p->stock_quantity;

            return [
                'id' => $id,
                'name' => (string) $p->name,
                'sku' => $p->sku,
                'stock_quantity' => round($stock, 3),
                'unit_sell_price' => $price !== null ? round($price, 2) : null,
                // In-stock batches, FIFO order — one label run per batch barcode
                'batches' => $batches[$id] ?? [],
            ];
        })->values()->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array{0: array<int, ?float>, 1: array<int, float>, 2: array<int, list<array{batch_sku: ?string, quantity_remaining: float, selling_unit_price: ?float}>>}
     *         [FIFO sell price, remaining qty, in-stock batches] per product
     */
    private function layerData(Business $business, array $ids, ?int $branchId, bool $branchStockSeparate): array
    {
        $price = [];
        $stock = [];
        $batches = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            DB::table('product_stock_layers')
                ->where('business_id', $business->id)
                ->whereIn('product_id', $chunk)
                ->where('quantity_remaining', '>', 0)
                ->when($branchStockSeparate && $branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('received_at')
                ->orderBy('id')
                ->get(['product_id', 'batch_sku', 'selling_unit_price', 'quantity_remaining'])
                ->each(function ($row) use (&$price, &$stock, &$batches) {
                    $pid = (int) $row->product_id;
                    if (! array_key_exists($pid, $price) && $row->selling_unit_price !== null) {
                        $price[$pid] = (float) $row->selling_unit_price;
                    }
                    $stock[$pid] = ($stock[$pid] ?? 0.0) + (float) $row->quantity_remaining;
                    $batches[$pid][] = [
                        'batch_sku' => $row->batch_sku !== null && $row->batch_sku !== '' ? (string) $row->batch_sku : null,
                        'quantity_remaining' => round((float) $row->quantity_remaining, 3),
                        'selling_unit_price' => $row->selling_unit_price !== null ? round((float) $row->selling_unit_price, 2) : null,
                    ];
                });
        }

        return [$price, $stock, $batches];
    }
}
