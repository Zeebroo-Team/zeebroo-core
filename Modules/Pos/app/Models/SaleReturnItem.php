<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;

class SaleReturnItem extends Model
{
    protected $table = 'pos_sale_return_items';

    protected $fillable = [
        'pos_sale_return_id',
        'pos_sale_item_id',
        'product_id',
        'product_stock_layer_id',
        'product_name',
        'sku',
        'quantity',
        'unit_sell_price',
        'line_total',
    ];

    protected $casts = [
        'quantity'       => 'float',
        'unit_sell_price'=> 'float',
        'line_total'     => 'float',
    ];

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'pos_sale_return_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'pos_sale_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
