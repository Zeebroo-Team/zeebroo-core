<?php

namespace Modules\Payment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;
use Modules\Package\Models\Package;

class Payment extends Model
{
    public const TYPE_SUBSCRIPTION = 'subscription';
    public const TYPE_FREE = 'free';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_REFUNDED = 'refunded';

    // Days a pending/failed subscription payment may go unsettled before access is locked.
    public const GRACE_PERIOD_DAYS = 2;

    protected $fillable = [
        'business_id',
        'user_id',
        'package_id',
        'payment_type',
        'payment_status',
        'billing_cycle',
        'gateway',
        'amount',
        'currency',
        'stripe_customer_id',
        'stripe_checkout_session_id',
        'stripe_subscription_id',
        'stripe_payment_intent_id',
        'stripe_invoice_id',
        'stripe_subscription_status',
        'current_period_end',
        'cancel_at_period_end',
        'paid_at',
        'failure_reason',
        'due_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'due_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function isSucceeded(): bool
    {
        return $this->payment_status === self::STATUS_SUCCEEDED;
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    public function currencySymbol(): string
    {
        return match (strtoupper($this->currency ?? 'USD')) {
            'USD'   => '$',
            'LKR'   => 'Rs.',
            default => strtoupper($this->currency ?? 'USD') . ' ',
        };
    }
}
