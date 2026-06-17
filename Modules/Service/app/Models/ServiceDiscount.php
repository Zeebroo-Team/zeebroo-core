<?php

namespace Modules\Service\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class ServiceDiscount extends Model
{
    protected $table = 'service_item_discounts';

    protected $fillable = [
        'business_id',
        'service_item_id',
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

    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(ServiceItem::class);
    }

    public function discountAmount(float $price): float
    {
        if ($this->discount_type === 'percentage') {
            return round($price * (float) $this->discount_value / 100, 2);
        }
        return min((float) $this->discount_value, $price);
    }

    public function finalPrice(float $price): float
    {
        return max(0, round($price - $this->discountAmount($price), 2));
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) return false;
        $today = now()->startOfDay();
        if ($this->starts_at && $this->starts_at->gt($today)) return false;
        if ($this->ends_at   && $this->ends_at->lt($today))   return false;
        return true;
    }
}
