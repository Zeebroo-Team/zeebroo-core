<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Business\Models\Business;

class StockTransaction extends Model
{
    protected $table = 'restaurant_stock_transactions';

    protected $fillable = [
        'ingredient_id',
        'business_id',
        'type',
        'quantity_change',
        'quantity_after',
        'notes',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'quantity_change' => 'decimal:3',
        'quantity_after'  => 'decimal:3',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'purchase'   => 'Purchase',
            'deduction'  => 'Used in Order',
            'adjustment' => 'Manual Adjustment',
            'waste'      => 'Waste',
            default      => $this->type,
        };
    }

    public function typeColor(): string
    {
        return match($this->type) {
            'purchase'   => '#16a34a',
            'deduction'  => '#dc2626',
            'adjustment' => '#2563eb',
            'waste'      => '#d97706',
            default      => '#6b7280',
        };
    }
}
