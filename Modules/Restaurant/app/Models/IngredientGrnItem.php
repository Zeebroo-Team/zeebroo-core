<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientGrnItem extends Model
{
    protected $table = 'restaurant_ingredient_grn_items';

    protected $fillable = [
        'grn_id',
        'purchase_order_item_id',
        'ingredient_id',
        'quantity_received',
        'unit_cost',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:3',
        'unit_cost'         => 'decimal:4',
        'line_total'        => 'decimal:2',
        'sort_order'        => 'integer',
    ];

    public function grn(): BelongsTo
    {
        return $this->belongsTo(IngredientGrn::class, 'grn_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(IngredientPurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
