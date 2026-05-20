<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Product\Models\Product;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'unit_cost',
        'line_total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function goodsReceiveNoteItems(): HasMany
    {
        return $this->hasMany(GoodsReceiveNoteItem::class);
    }

    public function quantityReceived(): float
    {
        return (float) $this->goodsReceiveNoteItems()->sum('quantity_received');
    }

    public function quantityRemaining(): float
    {
        return max(0, round((float) $this->quantity - $this->quantityReceived(), 3));
    }
}
