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
        'currency',
        'max_users',
        'extra_user_price',
        'is_free',
        'features',
        'is_active',
        'is_mobile_only',
        'supports_pos_lite',
        'sort_order',
    ];

    protected $casts = [
        'price'             => 'decimal:2',
        'discounted_price'  => 'decimal:2',
        'max_users'         => 'integer',
        'extra_user_price'  => 'decimal:2',
        'is_free'          => 'boolean',
        'features'          => 'array',
        'is_active'         => 'boolean',
        'is_mobile_only'    => 'boolean',
        'supports_pos_lite' => 'boolean',
        'sort_order'        => 'integer',
    ];

    /**
     * Mobile-only packages are sold exclusively through the mobile app — hide
     * them from every desktop/web-facing package listing (POS desktop
     * registration, web onboarding, business creation) unless the caller is
     * the mobile app itself. A package also needs `supports_pos_lite` set to
     * appear for the POS Lite Electron app; everything else (desktop/web)
     * stays visible regardless of that flag.
     */
    public function scopeVisibleForPlatform(Builder $query, string $platform): Builder
    {
        return match ($platform) {
            'mobile'   => $query,
            'pos_lite' => $query->where('is_mobile_only', false)->where('supports_pos_lite', true),
            default    => $query->where('is_mobile_only', false),
        };
    }

    public function featureLabels(): array
    {
        $catalog = config('features.list', []);

        return collect($this->features ?? [])
            ->map(fn ($key) => $catalog[$key] ?? $key)
            ->values()
            ->all();
    }

    /** e.g. "Up to 4 users" or "Unlimited users". */
    public function userLimitLabel(): string
    {
        if ($this->max_users === null) {
            return 'Unlimited users';
        }

        return 'Up to ' . $this->max_users . ' ' . ($this->max_users === 1 ? 'user' : 'users');
    }

    /** e.g. "+Rs.500.00 per extra user", or null when extra users are not charged. */
    public function extraUserPriceLabel(): ?string
    {
        if ($this->max_users === null || $this->extra_user_price === null) {
            return null;
        }

        return '+' . $this->currencySymbol() . number_format((float) $this->extra_user_price, 2) . ' per extra user';
    }

    public function currencySymbol(): string
    {
        return match ($this->currency) {
            'USD'   => '$',
            'LKR'   => 'Rs.',
            default => $this->currency . ' ',
        };
    }
}
