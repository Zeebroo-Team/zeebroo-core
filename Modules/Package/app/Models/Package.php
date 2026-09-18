<?php

namespace Modules\Package\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'is_mobile_only',
        'sort_order',
    ];

    protected $casts = [
        'price'             => 'decimal:2',
        'discounted_price'  => 'decimal:2',
        'is_free'           => 'boolean',
        'features'          => 'array',
        'is_active'         => 'boolean',
        'is_mobile_only'    => 'boolean',
        'sort_order'        => 'integer',
    ];

    /**
     * Mobile-only packages are sold exclusively through the mobile app — hide
     * them from every desktop/web-facing package listing (POS desktop
     * registration, web onboarding, business creation) unless the caller is
     * the mobile app itself.
     */
    public function scopeVisibleForPlatform(Builder $query, string $platform): Builder
    {
        return $platform === 'mobile' ? $query : $query->where('is_mobile_only', false);
    }

    public function featureLabels(): array
    {
        $catalog = config('features.list', []);

        return collect($this->features ?? [])
            ->map(fn ($key) => $catalog[$key] ?? $key)
            ->values()
            ->all();
    }
}
