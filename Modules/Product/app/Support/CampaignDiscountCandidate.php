<?php

namespace Modules\Product\Support;

/**
 * A synthesized, non-persisted stand-in for a ProductDiscount row representing
 * one product's discount under an active SaleCampaign (storewide or individual).
 * Exposes the same properties PosCatalogService/SaleService read off a
 * ProductDiscount, so both can be merged into one candidate collection.
 */
final class CampaignDiscountCandidate
{
    public function __construct(
        public readonly int $product_id,
        public readonly ?int $product_selling_unit_id,
        public readonly string $name,
        public readonly string $discount_type,
        public readonly float $discount_value,
    ) {
    }

    /** No-op — keeps this a drop-in alongside Eloquent ProductDiscount rows. */
    public function setRelation(string $relation, mixed $value): static
    {
        return $this;
    }
}
