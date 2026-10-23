<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    /**
     * Supported online gateways. `public` credential fields may be shown back to the
     * desktop client; `secret` fields are write-only (never returned by the API).
     */
    public const CATALOG = [
        'stripe' => [
            'name'        => 'Stripe',
            'description' => 'Visa, Mastercard, Amex, Apple Pay & Google Pay — worldwide.',
            'icon'        => 'fa-stripe',
            'public'      => ['publishable_key'],
            'secret'      => ['secret_key'],
            'labels'      => ['publishable_key' => 'Publishable key (pk_…)', 'secret_key' => 'Secret key (sk_…)'],
        ],
        'payhere' => [
            'name'        => 'PayHere',
            'description' => 'Sri Lankan cards, eZ Cash, mCash, Genie & bank transfers (LKR / USD).',
            'icon'        => 'fa-credit-card',
            'public'      => ['merchant_id'],
            'secret'      => ['merchant_secret'],
            'labels'      => ['merchant_id' => 'Merchant ID', 'merchant_secret' => 'Merchant secret'],
        ],
    ];

    protected $table = 'ecom_payment_gateways';

    protected $fillable = ['business_id', 'gateway', 'is_enabled', 'is_sandbox', 'credentials'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'is_enabled'  => 'boolean',
            'is_sandbox'  => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    public function credential(string $key): ?string
    {
        $v = ($this->credentials ?? [])[$key] ?? null;

        return $v === null || $v === '' ? null : (string) $v;
    }

    /** All required credential fields are filled in. */
    public function isConfigured(): bool
    {
        $meta = self::CATALOG[$this->gateway] ?? null;
        if (! $meta) {
            return false;
        }

        foreach (array_merge($meta['public'], $meta['secret']) as $field) {
            if ($this->credential($field) === null) {
                return false;
            }
        }

        return true;
    }

    public function isUsable(): bool
    {
        return $this->is_enabled && $this->isConfigured();
    }
}
