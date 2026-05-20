<?php

namespace Modules\Purchase\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Account;
use Modules\Account\Services\AccountService;
use Modules\Business\Models\Business;
use Modules\Purchase\Models\ChequePayment;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\Purchase;
use Modules\Transaction\Models\LedgerTransaction;

class ChequePaymentService
{
    private const MONEY_TOLERANCE = 0.005;

    public function __construct(
        private readonly AccountService $accountService,
    ) {
    }

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_PENDING = 'pending';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_DUE = 'due';

    public function businessHasCheques(Business $business): bool
    {
        return ChequePayment::query()
            ->where('business_id', $business->id)
            ->exists();
    }

    public function chequeForBusiness(Business $business, ChequePayment $cheque): ?ChequePayment
    {
        if ((int) $cheque->business_id !== (int) $business->id) {
            return null;
        }

        return $cheque;
    }

    public function latestOpenChequeForGrn(GoodsReceiveNote $grn): ?ChequePayment
    {
        return ChequePayment::query()
            ->where('goods_receive_note_id', $grn->id)
            ->where('status', ChequePayment::STATUS_PENDING)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return EloquentCollection<int, ChequePayment>
     */
    public function listForBusiness(Business $business, ?string $filter = null): EloquentCollection
    {
        $cheques = ChequePayment::query()
            ->where('business_id', $business->id)
            ->with([
                'goodsReceiveNote.purchase.supplier',
                'deductAccount.bankType',
                'ledgerTransaction',
            ])
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get();

        if ($filter === null || $filter === 'all') {
            return $cheques;
        }

        return $cheques
            ->filter(fn (ChequePayment $cheque) => $cheque->displayStatus() === $filter)
            ->values();
    }

    /** @return array{pending: int, overdue: int, cleared: int, pending_amount: float} */
    public function summaryForBusiness(Business $business): array
    {
        $cheques = ChequePayment::query()
            ->where('business_id', $business->id)
            ->get();

        $open = $cheques->filter(fn (ChequePayment $cheque) => ! $cheque->isCleared());

        return [
            'pending' => $open->filter(fn (ChequePayment $c) => in_array($c->displayStatus(), [self::STATUS_PENDING, self::STATUS_DUE], true))->count(),
            'overdue' => $open->filter(fn (ChequePayment $c) => $c->displayStatus() === self::STATUS_OVERDUE)->count(),
            'cleared' => $cheques->filter(fn (ChequePayment $c) => $c->isCleared())->count(),
            'pending_amount' => round((float) $open->sum('amount'), 2),
        ];
    }

    public function recordPendingFromSettlement(
        GoodsReceiveNote $grn,
        User $user,
        int $deductAccountId,
        float $amount,
        string $chequeNumber,
        string $dueDate,
    ): ChequePayment {
        $chequeNumber = trim($chequeNumber);
        if ($chequeNumber === '') {
            throw ValidationException::withMessages([
                'payment_reference' => 'Enter the cheque number.',
            ]);
        }

        if (! filled($dueDate)) {
            throw ValidationException::withMessages([
                'cheque_due_date' => 'Enter the cheque due date.',
            ]);
        }

        return ChequePayment::query()->create([
            'business_id' => $grn->business_id,
            'user_id' => $user->id,
            'goods_receive_note_id' => $grn->id,
            'ledger_transaction_id' => null,
            'deduct_account_id' => $deductAccountId,
            'cheque_number' => $chequeNumber,
            'due_date' => $dueDate,
            'amount' => round($amount, 2),
            'status' => ChequePayment::STATUS_PENDING,
            'cleared_at' => null,
        ]);
    }

    public function deductFromAccount(
        ChequePayment $cheque,
        Business $business,
        User $user,
        ?int $deductAccountId = null,
    ): ChequePayment {
        if ((int) $cheque->business_id !== (int) $business->id) {
            abort(403);
        }

        return DB::transaction(function () use ($cheque, $business, $user, $deductAccountId) {
            $cheque = ChequePayment::query()->whereKey($cheque->id)->lockForUpdate()->firstOrFail();

            if ($cheque->isCleared()) {
                throw ValidationException::withMessages([
                    'cheque' => 'This cheque has already been deducted from the account.',
                ]);
            }

            $accountId = $deductAccountId ?? $cheque->deduct_account_id;
            if ($accountId === null) {
                throw ValidationException::withMessages([
                    'deduct_account_id' => 'Select the account to deduct from.',
                ]);
            }

            $amount = round((float) $cheque->amount, 2);
            if ($amount <= self::MONEY_TOLERANCE) {
                throw ValidationException::withMessages([
                    'cheque' => 'Cheque amount must be greater than zero.',
                ]);
            }

            $account = Account::query()
                ->with('bankType')
                ->whereKey($accountId)
                ->where('user_id', $user->id)
                ->where('business_id', $business->id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                throw ValidationException::withMessages([
                    'deduct_account_id' => 'Choose an account belonging to your business.',
                ]);
            }

            if ($account->bankType?->slug !== 'current-account') {
                throw ValidationException::withMessages([
                    'deduct_account_id' => 'Cheque payments must use a current account.',
                ]);
            }

            if ((float) $account->current_balance + self::MONEY_TOLERANCE < $amount) {
                throw ValidationException::withMessages([
                    'deduct_account_id' => 'Insufficient balance on the selected account.',
                ]);
            }

            $grn = $cheque->goodsReceiveNote;
            if ($grn instanceof GoodsReceiveNote) {
                $grn = GoodsReceiveNote::query()->whereKey($grn->id)->lockForUpdate()->first();
                $paid = round((float) $grn->ledgerTransactions()->sum('amount'), 2);
                $outstanding = max(0.0, round((float) $grn->total - $paid, 2));
                if ($amount > $outstanding + self::MONEY_TOLERANCE) {
                    throw ValidationException::withMessages([
                        'cheque' => 'Cheque amount exceeds the outstanding balance on this goods receipt ('.number_format($outstanding, 2).').',
                    ]);
                }
            }

            $this->accountService->applyBalanceDeduction($account, $amount);

            $ledger = $cheque->ledgerTransaction;
            if (! $ledger instanceof LedgerTransaction && $grn instanceof GoodsReceiveNote) {
                $currency = (string) (get_settings('business.currency', '', $business) ?: '');
                $grn->loadMissing('purchase');

                $ledger = $grn->ledgerTransactions()->create([
                    'business_id' => $business->id,
                    'user_id' => $user->id,
                    'deduct_account_id' => $account->id,
                    'occurrence_date' => now()->toDateString(),
                    'period_number' => null,
                    'amount' => $amount,
                    'currency' => $currency !== '' ? $currency : null,
                    'cadence_snapshot' => null,
                    'periods_total_snapshot' => null,
                    'meta' => [
                        'payment_method' => Purchase::PAYMENT_CHEQUE,
                        'payment_reference' => $cheque->cheque_number,
                        'cheque_due_date' => $cheque->due_date?->toDateString(),
                        'grn_number' => $grn->grn_number,
                        'po_number' => $grn->purchase?->po_number,
                        'settlement_source' => 'cheque_clearance',
                        'cheque_payment_id' => $cheque->id,
                    ],
                ]);
            }

            $cheque->update([
                'status' => ChequePayment::STATUS_CLEARED,
                'cleared_at' => now(),
                'deduct_account_id' => $account->id,
                'ledger_transaction_id' => $ledger?->id,
            ]);

            return $cheque->refresh();
        });
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_CLEARED => 'Cleared',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_DUE => 'Due',
        ];
    }
}
