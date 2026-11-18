<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;
use Modules\Service\Models\ServiceItem;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id',
        'product_id',
        'service_item_id',
        'description',
        'notes',
        'quantity',
        'unit_price',
        'list_price',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity'        => 'decimal:3',
        'unit_price'      => 'decimal:2',
        'list_price'      => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate'        => 'decimal:3',
        'tax_amount'      => 'decimal:2',
        'line_total'      => 'decimal:2',
    ];

    /** The quoted price differs from the product's standard price saved with the line. */
    public function isPriceChanged(): bool
    {
        return $this->list_price !== null && abs((float) $this->unit_price - (float) $this->list_price) >= 0.005;
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
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
