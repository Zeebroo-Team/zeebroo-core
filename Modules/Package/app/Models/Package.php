<?php

namespace Modules\Package\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'price',
        'discounted_price',
        'is_free',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price'             => 'decimal:2',
        'discounted_price'  => 'decimal:2',
        'is_free'           => 'boolean',
        'features'          => 'array',
        'is_active'         => 'boolean',
        'sort_order'        => 'integer',
    ];

    public function featureLabels(): array
    {
        $catalog = config('features.list', []);

        return collect($this->features ?? [])
            ->map(fn ($key) => $catalog[$key] ?? $key)
            ->values()
            ->all();
    }
}
