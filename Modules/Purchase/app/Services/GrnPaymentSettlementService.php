<?php

namespace Modules\Purchase\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Account;
use Modules\Account\Services\AccountService;
use Modules\Budget\Services\BudgetLimitGuard;
use Modules\Business\Models\Business;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\GrnExpense;
use Modules\Purchase\Models\Purchase;
use Modules\Transaction\Models\LedgerTransaction;

class GrnPaymentSettlementService
{
    private const MONEY_TOLERANCE = 0.005;

    /** @var string `account` | `expense` — Settings > Accounts "Default Pay From" for GRN payments */
    public const SETTING_GRN_PAYMENT_SOURCE = 'pos.grn_payment_source';

    public function __construct(
        private readonly AccountService $accountService,
        private readonly ChequePaymentService $chequePayments,
    ) {
    }

    public function requiresImmediatePayment(GoodsReceiveNote $grn): bool
    {
        return in_array($grn->payment_method, [
            Purchase::PAYMENT_CASH,
            Purchase::PAYMENT_CHEQUE,
        ], true);
    }

    public function grnTotal(GoodsReceiveNote $grn): float
    {
        return round((float) $grn->total, 2);
    }

    public function amountPaid(GoodsReceiveNote $grn): float
    {
        return round((float) $grn->ledgerTransactions()->sum('amount'), 2);
    }

    public function amountOutstanding(GoodsReceiveNote $grn): float
    {
        return max(0.0, round($this->grnTotal($grn) - $this->amountPaid($grn), 2));
    }

    public function hasPayment(GoodsReceiveNote $grn): bool
    {
        return $this->amountPaid($grn) > self::MONEY_TOLERANCE;
    }

    public function isFullyPaid(GoodsReceiveNote $grn): bool
    {
        return $this->amountOutstanding($grn) <= self::MONEY_TOLERANCE;
    }

    /** @deprecated Use isFullyPaid() or hasPayment() */
    public function isPaid(GoodsReceiveNote $grn): bool
    {
        return $this->isFullyPaid($grn);
    }

    public const STATUS_PAID_FULL = 'paid_full';

    public const STATUS_PAID_PARTIAL = 'paid_partial';

    public const STATUS_PENDING = 'pending';

    public const STATUS_NO_AMOUNT = 'no_amount';

    public function paymentStatus(GoodsReceiveNote $grn): string
    {
        $total = $this->grnTotal($grn);
        if ($total <= self::MONEY_TOLERANCE) {
            return self::STATUS_NO_AMOUNT;
        }

        if ($this->isFullyPaid($grn)) {
            return self::STATUS_PAID_FULL;
        }

        if ($this->hasPayment($grn)) {
            return self::STATUS_PAID_PARTIAL;
        }

        return self::STATUS_PENDING;
    }

    public function paymentStatusLabel(GoodsReceiveNote $grn): string
    {
        return self::paymentStatusLabels()[$this->paymentStatus($grn)] ?? '—';
    }

    /** @return array<string, string> */
    public static function paymentStatusLabels(): array
    {
        return [
            self::STATUS_PAID_FULL => 'Paid in full',
            self::STATUS_PAID_PARTIAL => 'Partially paid',
            self::STATUS_PENDING => 'Payment pending',
            self::STATUS_NO_AMOUNT => 'No amount',
        ];
    }

    /**
     * @return array{status: string, status_label: string, total: float, amount_paid: float, amount_outstanding: float}
     */
    public function paymentSummary(GoodsReceiveNote $grn): array
    {
        return [
            'status' => $this->paymentStatus($grn),
            'status_label' => $this->paymentStatusLabel($grn),
            'total' => $this->grnTotal($grn),
            'amount_paid' => $this->amountPaid($grn),
            'amount_outstanding' => $this->amountOutstanding($grn),
        ];
    }

    public function settle(
        GoodsReceiveNote $grn,
        Business $business,
        User $user,
        int $deductAccountId,
        ?float $amount = null,
        ?string $paymentMethod = null,
        ?string $paymentReference = null,
        ?string $chequeDueDate = null,
    ): ?LedgerTransaction {
        if ((int) $grn->business_id !== (int) $business->id) {
            abort(403);
        }

        $grnTotal = $this->grnTotal($grn);
        if ($grnTotal <= self::MONEY_TOLERANCE) {
            throw ValidationException::withMessages([
                'payment' => 'GRN total must be greater than zero before recording payment.',
            ]);
        }

        $settlementMethod = $paymentMethod ?? $grn->payment_method;
        $settlementReference = $paymentReference ?? $grn->payment_reference;
        $settlementChequeDueDate = $chequeDueDate ?? ($grn->cheque_due_date?->toDateString());

        return DB::transaction(function () use ($grn, $business, $user, $deductAccountId, $amount, $grnTotal, $settlementMethod, $settlementReference, $settlementChequeDueDate) {
            $grn->ledgerTransactions()->lockForUpdate()->get();
            $grn->refresh();

            $payAmount = $this->validateOutstandingAndAmount($grn, $business, $amount);

            $account = Account::query()
                ->with('bankType')
                ->whereKey($deductAccountId)
                ->where('user_id', $user->id)
                ->where('business_id', $business->id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                throw ValidationException::withMessages([
                    'deduct_account_id' => 'Choose an account belonging to your business.',
                ]);
            }

            $this->assertAccountAllowedForGrn($grn, $account, $settlementMethod);

            if ($settlementMethod === Purchase::PAYMENT_CHEQUE) {
                $this->chequePayments->recordPendingFromSettlement(
                    $grn,
                    $user,
                    (int) $account->id,
                    $payAmount,
                    (string) $settlementReference,
                    (string) $settlementChequeDueDate,
                );

                return null;
            }

            if ((float) $account->current_balance + self::MONEY_TOLERANCE < $payAmount) {
                throw ValidationException::withMessages([
                    'deduct_account_id' => 'Insufficient balance on the selected account.',
                ]);
            }

            $this->accountService->applyBalanceDeduction($account, $payAmount);

            $grn->loadMissing('purchase');
            $currency = (string) (get_settings('business.currency', '', $business) ?: '');

            $ledger = $grn->ledgerTransactions()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'deduct_account_id' => $account->id,
                'occurrence_date' => $grn->received_date->toDateString(),
                'period_number' => null,
                'amount' => $payAmount,
                'currency' => $currency !== '' ? $currency : null,
                'cadence_snapshot' => null,
                'periods_total_snapshot' => null,
                'meta' => [
                    'payment_method' => $settlementMethod,
                    'payment_reference' => $settlementReference,
                    'cheque_due_date' => null,
                    'grn_number' => $grn->grn_number,
                    'po_number' => $grn->purchase?->po_number,
                    'settlement_source' => 'goods_receive_note',
                    'partial' => $payAmount + self::MONEY_TOLERANCE < $grnTotal,
                ],
            ]);

            return $ledger;
        });
    }

    /**
     * The business's configured default for cash GRN payments: pay from a
     * real account, or log as a plain business expense. Falls back to
     * `expense` for a business with no accounts set up yet (e.g. pos-lite,
     * which has no account-management UI), and `account` otherwise so
     * existing businesses keep their current behavior.
     */
    public function defaultPaymentSource(Business $business): string
    {
        $saved = $business->getSetting(self::SETTING_GRN_PAYMENT_SOURCE);
        if (in_array($saved, ['account', 'expense'], true)) {
            return $saved;
        }

        return Account::where('business_id', $business->id)->exists() ? 'account' : 'expense';
    }

    public function assertAccountAllowedForGrn(GoodsReceiveNote $grn, Account $account, ?string $paymentMethod = null): void
    {
        $method = $paymentMethod ?? $grn->payment_method;
        if ($method === Purchase::PAYMENT_CHEQUE
            && $account->bankType?->slug !== 'current-account') {
            throw ValidationException::withMessages([
                'deduct_account_id' => 'Cheque payments must use a current account.',
            ]);
        }
    }

    /**
     * Record a GRN payment with no bank/cash account — logged purely as a
     * business expense. Used where account management isn't available
     * (e.g. pos-lite) so a cash purchase can still be settled. Cheques
     * always need a real account, so this only accepts cash-style payment.
     *
     * Caller must hold the same lock/outstanding contract as settle().
     */
    public function settleAsExpense(
        GoodsReceiveNote $grn,
        Business $business,
        User $user,
        ?float $amount = null,
        ?string $paymentMethod = null,
        ?string $paymentReference = null,
    ): LedgerTransaction {
        if ((int) $grn->business_id !== (int) $business->id) {
            abort(403);
        }

        $grnTotal = $this->grnTotal($grn);
        if ($grnTotal <= self::MONEY_TOLERANCE) {
            throw ValidationException::withMessages([
                'payment' => 'GRN total must be greater than zero before recording payment.',
            ]);
        }

        $settlementMethod = $paymentMethod ?? $grn->payment_method;
        if ($settlementMethod === Purchase::PAYMENT_CHEQUE) {
            throw ValidationException::withMessages([
                'payment_source' => 'Cheque payments must be paid from an account.',
            ]);
        }

        $settlementReference = $paymentReference ?? $grn->payment_reference;

        return DB::transaction(function () use ($grn, $business, $user, $amount, $grnTotal, $settlementMethod, $settlementReference) {
            $grn->ledgerTransactions()->lockForUpdate()->get();
            $grn->refresh();

            $payAmount = $this->validateOutstandingAndAmount($grn, $business, $amount);

            $grn->loadMissing('purchase');
            $currency = (string) (get_settings('business.currency', '', $business) ?: '');

            $ledger = $grn->ledgerTransactions()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'deduct_account_id' => null,
                'occurrence_date' => $grn->received_date->toDateString(),
                'period_number' => null,
                'amount' => $payAmount,
                'currency' => $currency !== '' ? $currency : null,
                'cadence_snapshot' => null,
                'periods_total_snapshot' => null,
                'meta' => [
                    'payment_method' => $settlementMethod,
                    'payment_reference' => $settlementReference,
                    'cheque_due_date' => null,
                    'grn_number' => $grn->grn_number,
                    'po_number' => $grn->purchase?->po_number,
                    'settlement_source' => 'goods_receive_note',
                    'is_expense' => true,
                    'partial' => $payAmount + self::MONEY_TOLERANCE < $grnTotal,
                ],
            ]);

            GrnExpense::create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'goods_receive_note_id' => $grn->id,
                'ledger_transaction_id' => $ledger->id,
                'amount' => $payAmount,
                'payment_method' => $settlementMethod,
                'payment_reference' => $settlementReference,
                'paid_at' => now(),
            ]);

            return $ledger;
        });
    }

    /**
     * Shared by settle() and settleAsExpense(): validates the requested pay
     * amount against the GRN's outstanding balance and the purchasing
     * budget. Must be called after locking the GRN's ledger transactions.
     */
    private function validateOutstandingAndAmount(GoodsReceiveNote $grn, Business $business, ?float $amount): float
    {
        $outstanding = $this->amountOutstanding($grn);
        if ($outstanding <= self::MONEY_TOLERANCE) {
            throw ValidationException::withMessages([
                'payment' => 'This goods receive note is already fully paid.',
            ]);
        }

        $payAmount = $amount !== null
            ? round((float) $amount, 2)
            : $outstanding;

        if ($payAmount <= self::MONEY_TOLERANCE) {
            throw ValidationException::withMessages([
                'pay_amount' => 'Enter a payment amount greater than zero.',
            ]);
        }

        if ($payAmount > $outstanding + self::MONEY_TOLERANCE) {
            throw ValidationException::withMessages([
                'pay_amount' => 'Payment cannot exceed the outstanding amount ('.number_format($outstanding, 2, '.', ',').').',
            ]);
        }

        $budgetCheck = BudgetLimitGuard::evaluate(
            $business->id,
            'purchasing',
            $payAmount,
            $grn->received_date
        );
        if (! $budgetCheck['allowed']) {
            throw ValidationException::withMessages(['budget' => $budgetCheck['message']]);
        }

        return $payAmount;
    }
}
