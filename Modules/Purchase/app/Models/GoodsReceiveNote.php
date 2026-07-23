<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;
use Modules\Purchase\Services\GrnPaymentSettlementService;
use Modules\Transaction\Models\LedgerTransaction;

class GoodsReceiveNote extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'purchase_id',
        'grn_number',
        'received_date',
        'reference',
        'notes',
        'subtotal',
        'total',
        'payment_method',
        'payment_reference',
        'cheque_due_date',
        'stock_applied',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'cheque_due_date' => 'date',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'stock_applied' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiveNoteItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'transactionable')
            ->orderByDesc('occurrence_date')
            ->orderByDesc('id');
    }

    public function chequePayments(): HasMany
    {
        return $this->hasMany(ChequePayment::class)->orderByDesc('due_date')->orderByDesc('id');
    }

    public function requiresImmediatePayment(): bool
    {
        return in_array($this->payment_method, [
            Purchase::PAYMENT_CASH,
            Purchase::PAYMENT_CHEQUE,
        ], true);
    }

    public function paymentMethodLabel(): ?string
    {
        if (!filled($this->payment_method)) {
            return null;
        }

        return Purchase::paymentMethods()[$this->payment_method] ?? ucfirst((string) $this->payment_method);
    }

    public function ledgerPaidTotal(): float
    {
        if (array_key_exists('ledger_paid_total', $this->attributes)) {
            return round((float) $this->attributes['ledger_paid_total'], 2);
        }

        if ($this->relationLoaded('ledgerTransactions')) {
            return round((float) $this->ledgerTransactions->sum('amount'), 2);
        }

        return round((float) $this->ledgerTransactions()->sum('amount'), 2);
    }

    public function amountOutstanding(): float
    {
        return max(0.0, round((float) $this->total - $this->ledgerPaidTotal(), 2));
    }

    public function paymentStatus(): string
    {
        $total = round((float) $this->total, 2);
        if ($total <= 0.005) {
            return GrnPaymentSettlementService::STATUS_NO_AMOUNT;
        }

        if ($this->amountOutstanding() <= 0.005) {
            return GrnPaymentSettlementService::STATUS_PAID_FULL;
        }

        if ($this->ledgerPaidTotal() > 0.005) {
            return GrnPaymentSettlementService::STATUS_PAID_PARTIAL;
        }

        return GrnPaymentSettlementService::STATUS_PENDING;
    }

    public function paymentStatusLabel(): string
    {
        return GrnPaymentSettlementService::paymentStatusLabels()[$this->paymentStatus()] ?? '—';
    }
}
