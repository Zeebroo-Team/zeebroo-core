<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;
use Modules\Purchase\Models\GoodsReceiveNoteItem;

class ProductStockLayer extends Model
{
    protected $fillable = [
        'business_id',
        'product_id',
        'goods_receive_note_item_id',
        'quantity_received',
        'quantity_remaining',
        'unit_cost',
        'selling_unit_price',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:3',
            'quantity_remaining' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'selling_unit_price' => 'decimal:2',
            'received_at' => 'date',
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

    public function goodsReceiveNoteItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiveNoteItem::class);
    }

    public function marginAmount(): ?float
    {
        if ($this->selling_unit_price === null) {
            return null;
        }

        return round((float) $this->selling_unit_price - (float) $this->unit_cost, 2);
    }
}
