<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;

class CustomerSubscription extends Model
{
    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_id',
        'pos_customer_id',
        'product_id',
        'pos_sale_id',
        'pos_sale_item_id',
        'recurring_period',
        'free_trial',
        'price',
        'quantity',
        'status',
        'started_at',
        'next_billing_at',
        'last_renewed_at',
        'cancelled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'free_trial'       => 'boolean',
            'price'            => 'decimal:2',
            'quantity'         => 'decimal:3',
            'started_at'       => 'date',
            'next_billing_at'  => 'date',
            'last_renewed_at'  => 'date',
            'cancelled_at'     => 'datetime',
        ];
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_TRIAL     => 'Free Trial',
            self::STATUS_ACTIVE    => 'Active',
            self::STATUS_PAUSED    => 'Paused',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'pos_customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'pos_sale_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'pos_sale_item_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
