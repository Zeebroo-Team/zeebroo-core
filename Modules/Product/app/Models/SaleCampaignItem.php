<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleCampaignItem extends Model
{
    protected $fillable = [
        'sale_campaign_id',
        'product_id',
        'product_selling_unit_id',
        'discount_type',
        'discount_value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'sort_order'     => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SaleCampaign::class, 'sale_campaign_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sellingUnit(): BelongsTo
    {
        return $this->belongsTo(ProductSellingUnit::class, 'product_selling_unit_id');
    }
}
