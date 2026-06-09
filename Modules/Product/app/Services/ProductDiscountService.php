<?php

namespace Modules\Product\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Product\Models\ProductDiscount;

class ProductDiscountService
{
    /**
     * All currently active discounts for the given product IDs (single query).
     * Includes base-price discounts (product_selling_unit_id IS NULL) and
     * selling-unit discounts. Load with sellingUnit relation pre-loaded.
     */
    public function activeForProducts(Business $business, array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $today = now()->startOfDay();

        return ProductDiscount::where('business_id', $business->id)
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with('sellingUnit')
            ->orderByDesc('discount_value')  // highest discount first so first() wins
            ->get();
    }

    public function create(Business $business, array $data): ProductDiscount
    {
        return $business->productDiscounts()->create($data);
    }

    public function update(ProductDiscount $discount, array $data): void
    {
        $discount->update($data);
    }

    public function delete(ProductDiscount $discount): void
    {
        $discount->delete();
    }

    public function discountForBusiness(Business $business, ProductDiscount $discount): ?ProductDiscount
    {
        return $business->productDiscounts()->find($discount->id);
    }
}
