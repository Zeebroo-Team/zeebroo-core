<?php

namespace Modules\Pos\Services;

use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;

/**
 * Lightweight product list for the desktop Barcode Generator picker.
 *
 * Returns every sellable product in one response (no pagination) with just
 * what a label needs: name, SKU, stock and the price printed on the label.
 * A few grouped queries for the whole catalog — no per-product card building.
 */
class PosBarcodeProductService
{
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
        [$fifoPrice, $layerStock] = $this->layerData($business, $ids, $branchId, $branchStockSeparate);

        return $products->map(function (Product $p) use ($fifoPrice, $layerStock, $branchId, $branchStockSeparate) {
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
            ];
        })->values()->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array{0: array<int, ?float>, 1: array<int, float>}  [FIFO sell price, remaining qty] per product
     */
    private function layerData(Business $business, array $ids, ?int $branchId, bool $branchStockSeparate): array
    {
        $price = [];
        $stock = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            DB::table('product_stock_layers')
                ->where('business_id', $business->id)
                ->whereIn('product_id', $chunk)
                ->where('quantity_remaining', '>', 0)
                ->when($branchStockSeparate && $branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('received_at')
                ->orderBy('id')
                ->get(['product_id', 'selling_unit_price', 'quantity_remaining'])
                ->each(function ($row) use (&$price, &$stock) {
                    $pid = (int) $row->product_id;
                    if (! array_key_exists($pid, $price) && $row->selling_unit_price !== null) {
                        $price[$pid] = (float) $row->selling_unit_price;
                    }
                    $stock[$pid] = ($stock[$pid] ?? 0.0) + (float) $row->quantity_remaining;
                });
        }

        return [$price, $stock];
    }
}
