<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class GiftCard extends Model
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_USED      = 'used';
    public const STATUS_DISABLED  = 'disabled';

    protected $table = 'pos_gift_cards';

    protected $fillable = [
        'business_id',
        'pos_gift_card_group_id',
        'pos_customer_id',
        'name',
        'code',
        'initial_value',
        'balance',
        'valid_from',
        'expires_at',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'initial_value' => 'decimal:2',
            'balance'       => 'decimal:2',
            'valid_from'    => 'date',
            'expires_at'    => 'date',
            'is_active'     => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GiftCardGroup::class, 'pos_gift_card_group_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'pos_customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class, 'pos_gift_card_id')->orderByDesc('created_at')->orderByDesc('id');
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
            ! $this->is_active                 => self::STATUS_DISABLED,
            $this->isExpired()                 => self::STATUS_EXPIRED,
            $this->isScheduled()               => self::STATUS_SCHEDULED,
            (float) $this->balance <= 0.005    => self::STATUS_USED,
            default                            => self::STATUS_ACTIVE,
        };
    }

    public function isRedeemable(): bool
    {
        return $this->status() === self::STATUS_ACTIVE;
    }
}
