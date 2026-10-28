<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;

/**
 * A cheque a customer handed over to pay a POS bill. It stays pending until it is
 * claimed (deposited — the money goes into a deposit account) or marked bounced.
 */
class SaleCheque extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_BOUNCED = 'bounced';

    /** The bill was reverted/voided before the cheque was claimed. */
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING   => 'Pending',
        self::STATUS_CLEARED   => 'Claimed',
        self::STATUS_BOUNCED   => 'Bounced',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $table = 'pos_sale_cheques';

    protected $fillable = [
        'business_id',
        'pos_sale_id',
        'pos_customer_id',
        'user_id',
        'cheque_number',
        'bank_name',
        'cheque_date',
        'amount',
        'status',
        'deposit_account_id',
        'claimed_by',
        'claimed_at',
        'bounced_at',
        'bounce_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cheque_date' => 'date',
            'amount'      => 'decimal:2',
            'claimed_at'  => 'datetime',
            'bounced_at'  => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'pos_sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'pos_customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function depositAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deposit_account_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Pending or bounced cheques can still be (re)presented to the bank. */
    public function isClaimable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_BOUNCED], true);
    }

    /** Pending and the cheque date has arrived — it can be deposited today. */
    public function isDue(): bool
    {
        return $this->isPending() && $this->cheque_date !== null && ! $this->cheque_date->isFuture();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
