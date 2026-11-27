<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request by an affiliate to be paid out (part of) their commission. */
class AffiliateWithdrawal extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** Requests that hold part of the balance (not yet rejected or cancelled). */
    public const HOLDING_STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_PAID];

    /** Requests still waiting on the admin. */
    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'In review',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PAID => 'Paid',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'user_id', 'amount', 'currency', 'status', 'note',
        'bank_name', 'bank_branch', 'account_holder_name', 'account_number',
        'admin_note', 'payment_reference', 'reviewed_by',
        'approved_at', 'rejected_at', 'paid_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'account_number' => 'encrypted',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** CSS modifier for the status pill (aff-status--*). */
    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warn',
            self::STATUS_APPROVED => 'info',
            self::STATUS_PAID => 'on',
            self::STATUS_REJECTED => 'danger',
            default => 'off',
        };
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function formattedAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->amount, 2);
    }

    public function maskedAccountNumber(): ?string
    {
        $number = preg_replace('/\s+/', '', (string) $this->account_number);

        return $number === '' ? null : str_repeat('*', max(0, strlen($number) - 4)).substr($number, -4);
    }
}
