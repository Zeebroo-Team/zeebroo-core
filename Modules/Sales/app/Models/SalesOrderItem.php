<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_type',
        'discount_value',
        'tax_pct',
        'tax_type',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity'       => 'decimal:3',
        'unit_price'     => 'decimal:2',
        'discount_value' => 'decimal:2',
        'tax_pct'        => 'decimal:2',
        'line_total'     => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
