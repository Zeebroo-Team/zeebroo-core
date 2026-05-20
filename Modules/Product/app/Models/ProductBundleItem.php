<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBundleItem extends Model
{
    protected $fillable = [
        'bundle_product_id',
        'item_product_id',
        'quantity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    public function bundleProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    public function itemProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'item_product_id');
    }
}
