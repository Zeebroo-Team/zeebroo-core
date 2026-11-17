<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;

/**
 * What is in stock for the products on a quotation: the sum of quantity_remaining over the
 * product's stock layers — of one branch when the business runs in branch mode and the
 * quotation names a branch, otherwise of the whole business.
 */
class QuotationStockService
{
    /**
     * @param  int[]  $productIds
     * @return array<int, float> product id => quantity in stock (0 for products without stock)
     */
    public function available(Business $business, array $productIds, ?int $branchId = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($ids === []) {
            return [];
        }

        $query = DB::table('product_stock_layers')
            ->where('business_id', $business->id)
            ->whereIn('product_id', $ids);
        if ($branchId !== null && $business->multiWarehouseBranchEnabled()) {
            $query->where('branch_id', $branchId);
        }

        $stock = $query->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_remaining) as qty')
            ->pluck('qty', 'product_id');

        return collect($ids)->mapWithKeys(fn (int $id) => [$id => round((float) ($stock[$id] ?? 0), 3)])->all();
    }

    /**
     * Stock per product line. A product on several lines is checked against its total quantity.
     *
     * @param  array<int, array{item_type?: ?string, product_id?: mixed, quantity?: mixed}>  $items
     * @return list<array{index: int, product_id: int, requested: float, available: float, short: bool}>
     */
    public function check(Business $business, array $items, ?int $branchId = null): array
    {
        $lines = [];
        foreach (array_values($items) as $index => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId > 0 && ($item['item_type'] ?? 'product') === 'product') {
                $lines[$index] = [$productId, max(0, (float) ($item['quantity'] ?? 0))];
            }
        }

        $available = $this->available($business, array_column($lines, 0), $branchId);
        $requested = [];
        foreach ($lines as [$productId, $qty]) {
            $requested[$productId] = ($requested[$productId] ?? 0) + $qty;
        }

        $result = [];
        foreach ($lines as $index => [$productId]) {
            $result[] = [
                'index'      => $index,
                'product_id' => $productId,
                'requested'  => round($requested[$productId], 3),
                'available'  => $available[$productId] ?? 0.0,
                'short'      => $requested[$productId] > ($available[$productId] ?? 0) + 0.0005,
            ];
        }

        return $result;
    }
}
