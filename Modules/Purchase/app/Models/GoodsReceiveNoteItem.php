<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;

class GoodsReceiveNoteItem extends Model
{
    protected $fillable = [
        'goods_receive_note_id',
        'purchase_item_id',
        'product_id',
        'quantity_received',
        'unit_cost',
        'selling_unit_price',
        'line_total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'selling_unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function goodsReceiveNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiveNote::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockLayer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductStockLayer::class, 'goods_receive_note_item_id');
    }
}
