<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class ProductDiscount extends Model
{
    protected $fillable = [
        'business_id',
        'product_id',
        'product_selling_unit_id',
        'name',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'starts_at'      => 'date',
            'ends_at'        => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sellingUnit(): BelongsTo
    {
        return $this->belongsTo(ProductSellingUnit::class, 'product_selling_unit_id');
    }

    /** The original price this discount applies to. */
    public function originalPrice(): float
    {
        if ($this->product_selling_unit_id !== null && $this->sellingUnit) {
            $su    = $this->sellingUnit;
            $base  = (float) ($this->product?->unit_price ?? 0);
            return (float) $su->displaySellingPrice($base);
        }

        return (float) ($this->product?->unit_price ?? 0);
    }

    /** Discount amount in currency units. */
    public function discountAmount(): float
    {
        $original = $this->originalPrice();
        if ($this->discount_type === 'percentage') {
            return round($original * (float) $this->discount_value / 100, 2);
        }
        return min((float) $this->discount_value, $original);
    }

    /** Final price after discount. */
    public function finalPrice(): float
    {
        return max(0, round($this->originalPrice() - $this->discountAmount(), 2));
    }

    /** Whether this discount is currently in its validity window. */
    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $today = now()->startOfDay();
        if ($this->starts_at && $this->starts_at->gt($today)) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->lt($today)) {
            return false;
        }
        return true;
    }
}
