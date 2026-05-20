<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Business\Models\Business;

class ProductBrand extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'description',
        'website',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_product_brand',
            'product_brand_id',
            'product_id',
        )->withTimestamps();
    }
}
