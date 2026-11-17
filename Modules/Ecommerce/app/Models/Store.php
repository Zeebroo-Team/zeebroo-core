<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class Store extends Model
{
    /** Storefront designs — rendered by storefront.blade.php via a `tpl-{key}` body class. */
    public const TEMPLATES = [
        'classic' => ['name' => 'Classic',  'description' => 'Clean cards on a soft background with a sticky header — fits any shop.'],
        'modern'  => ['name' => 'Modern',   'description' => 'Rounded, airy layout with gradient accents and larger product photos.'],
        'minimal' => ['name' => 'Minimal',  'description' => 'White space, serif headings and borderless products — ideal for boutiques.'],
        'bold'    => ['name' => 'Bold',     'description' => 'Dark header, big type and high-contrast cards — great for fashion & tech.'],
    ];

    protected $table = 'ecom_stores';

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'tagline',
        'about',
        'logo_url',
        'banner_url',
        'theme_color',
        'template',
        'contact_email',
        'contact_phone',
        'address',
        'currency',
        'is_published',
        'cod_enabled',
        'bank_transfer_enabled',
        'bank_transfer_details',
        'shipping_flat_rate',
        'free_shipping_over',
        'min_order_amount',
        'announcement',
        'social_links',
        'seo_title',
        'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'is_published'          => 'boolean',
            'cod_enabled'           => 'boolean',
            'bank_transfer_enabled' => 'boolean',
            'shipping_flat_rate'    => 'decimal:2',
            'free_shipping_over'    => 'decimal:2',
            'min_order_amount'      => 'decimal:2',
            'social_links'          => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function publicUrl(): string
    {
        return route('ecommerce.storefront', ['slug' => $this->slug]);
    }

    /** Shipping fee for a cart subtotal, honouring the free-shipping threshold. */
    public function shippingFor(float $subtotal): float
    {
        $threshold = $this->free_shipping_over !== null ? (float) $this->free_shipping_over : null;
        if ($threshold !== null && $threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }

        return (float) $this->shipping_flat_rate;
    }
}
