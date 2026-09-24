<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;
use Modules\Service\Models\ServiceItem;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'service_item_id',
        'description',
        'quantity',
        'unit_price',
        'discount_type',
        'discount_value',
        'tax_pct',
        'tax_type',
        'line_total',
        'sort_order',
        'rental_daily_rate',
        'rental_return_date',
        'rental_late_fee_multiplier',
        'warranty_type',
        'warranty_date',
        'is_subscription',
        'subscription_period',
    ];

    protected $casts = [
        'quantity'                    => 'decimal:3',
        'unit_price'                  => 'decimal:2',
        'discount_value'              => 'decimal:2',
        'tax_pct'                     => 'decimal:2',
        'line_total'                  => 'decimal:2',
        'rental_daily_rate'           => 'decimal:2',
        'rental_return_date'          => 'date',
        'rental_late_fee_multiplier'  => 'decimal:2',
        'warranty_date'               => 'date',
        'is_subscription'             => 'boolean',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(ServiceItem::class);
    }
}
