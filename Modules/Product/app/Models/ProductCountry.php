<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

/**
 * A country a business uses on its products, as Country of Origin and/or
 * Country of Manufacture. One list per business serves both fields.
 */
class ProductCountry extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'code',
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

    /** Products that use this country as either origin or manufacture country. */
    public function productsUsing(): Builder
    {
        return Product::query()
            ->where('origin_country_id', $this->id)
            ->orWhere('manufacture_country_id', $this->id);
    }
}
