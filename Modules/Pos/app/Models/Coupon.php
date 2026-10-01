<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FLAT    = 'flat';

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_USED      = 'used';
    public const STATUS_DISABLED  = 'disabled';

    protected $table = 'pos_coupons';

    protected $fillable = [
        'business_id',
        'name',
        'code',
        'discount_type',
        'discount_value',
        'quantity',
        'used_count',
        'valid_from',
        'expires_at',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'quantity'       => 'integer',
            'used_count'     => 'integer',
            'valid_from'     => 'date',
            'expires_at'     => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'pos_coupon_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function remaining(): int
    {
        return max(0, (int) $this->quantity - (int) $this->used_count);
    }

    public function isExpired(): bool
    {
        return (bool) ($this->expires_at && $this->expires_at->lt(now()->startOfDay()));
    }

    public function isScheduled(): bool
    {
        return (bool) ($this->valid_from && $this->valid_from->gt(now()->startOfDay()));
    }

    public function status(): string
    {
        return match (true) {
            ! $this->is_active         => self::STATUS_DISABLED,
            $this->isExpired()         => self::STATUS_EXPIRED,
            $this->isScheduled()       => self::STATUS_SCHEDULED,
            $this->remaining() <= 0    => self::STATUS_USED,
            default                    => self::STATUS_ACTIVE,
        };
    }

    public function isRedeemable(): bool
    {
        return $this->status() === self::STATUS_ACTIVE;
    }

    /** Discount this coupon gives on $amount — never more than the amount itself. */
    public function discountFor(float $amount): float
    {
        $amount = max(0, $amount);
        $value  = (float) $this->discount_value;

        $discount = $this->discount_type === self::TYPE_PERCENT
            ? $amount * min(100, max(0, $value)) / 100
            : $value;

        return round(min($amount, max(0, $discount)), 2);
    }
}
