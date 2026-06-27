<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngredientPurchaseOrderItem extends Model
{
    protected $table = 'restaurant_ingredient_purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'ingredient_id',
        'quantity',
        'unit_cost',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'unit_cost'  => 'decimal:4',
        'line_total' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(IngredientPurchaseOrder::class, 'purchase_order_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function grnItems(): HasMany
    {
        return $this->hasMany(IngredientGrnItem::class, 'purchase_order_item_id');
    }

    public function quantityReceived(): float
    {
        return round((float) $this->grnItems()->sum('quantity_received'), 3);
    }

    public function quantityRemaining(): float
    {
        return max(0.0, round((float) $this->quantity - $this->quantityReceived(), 3));
    }
}
