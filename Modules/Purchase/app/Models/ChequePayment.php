<?php

namespace Modules\Purchase\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;
use Modules\Purchase\Services\ChequePaymentService;
use Modules\Transaction\Models\LedgerTransaction;

class ChequePayment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'business_id',
        'user_id',
        'goods_receive_note_id',
        'ledger_transaction_id',
        'deduct_account_id',
        'cheque_number',
        'due_date',
        'amount',
        'status',
        'cleared_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'cleared_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function goodsReceiveNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiveNote::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    public function deductAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deduct_account_id');
    }

    public function isCleared(): bool
    {
        return $this->status === self::STATUS_CLEARED;
    }

    public function displayStatus(): string
    {
        if ($this->isCleared()) {
            return ChequePaymentService::STATUS_CLEARED;
        }

        if ($this->due_date === null) {
            return ChequePaymentService::STATUS_PENDING;
        }

        $due = $this->due_date instanceof Carbon ? $this->due_date : Carbon::parse($this->due_date);

        if ($due->isPast() && ! $due->isToday()) {
            return ChequePaymentService::STATUS_OVERDUE;
        }

        return ChequePaymentService::STATUS_DUE;
    }

    public function displayStatusLabel(): string
    {
        return ChequePaymentService::statusLabels()[$this->displayStatus()] ?? '—';
    }

    public function paidAt(): ?Carbon
    {
        if ($this->cleared_at) {
            return $this->cleared_at;
        }

        return $this->ledgerTransaction?->occurrence_date
            ?? $this->ledgerTransaction?->created_at;
    }
}
