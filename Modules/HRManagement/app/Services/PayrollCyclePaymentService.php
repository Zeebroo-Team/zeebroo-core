<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Account;
use Modules\Account\Services\AccountService;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\Transaction\Models\LedgerTransaction;

final class PayrollCyclePaymentService
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    /**
     * Records one payout against {@see PayrollCycle}: deducts total net pay from the chosen account and writes a ledger row.
     *
     * @throws ValidationException
     */
    public function recordPayment(User $user, Business $business, PayrollCycle $cycle, int $deductAccountId): LedgerTransaction
    {
        if (! $cycle->isFinalized()) {
            throw ValidationException::withMessages([
                'deduct_account_id' => __('Only finalized payroll cycles can be marked as paid from an account.'),
            ]);
        }

        return DB::transaction(function () use ($user, $business, $cycle, $deductAccountId): LedgerTransaction {
            $cycle = PayrollCycle::query()
                ->where('business_id', $business->id)
                ->whereKey($cycle->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $cycle->isFinalized()) {
                throw ValidationException::withMessages([
                    'deduct_account_id' => __('Only finalized payroll cycles can be marked as paid from an account.'),
                ]);
            }

            $morphClass = $cycle->getMorphClass();

            $dup = LedgerTransaction::query()
                ->where('transactionable_type', $morphClass)
                ->where('transactionable_id', $cycle->getKey())
                ->lockForUpdate()
                ->exists();

            if ($dup) {
                throw ValidationException::withMessages([
                    'deduct_account_id' => __('A payment has already been recorded for this payroll cycle.'),
                ]);
            }

            $amount = round((float) $cycle->items()->sum('net_pay'), 2);

            if ($amount <= 0.0) {
                throw ValidationException::withMessages([
                    'deduct_account_id' => __('Total net pay for this cycle is zero; nothing to pay.'),
                ]);
            }

            $account = Account::query()
                ->whereKey($deductAccountId)
                ->where('user_id', $user->id)
                ->where('business_id', $business->id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                throw ValidationException::withMessages([
                    'deduct_account_id' => __('Choose an account belonging to this business.'),
                ]);
            }

            $balance = round((float) (string) $account->current_balance, 2);

            if ($balance + 1e-6 < $amount) {
                throw ValidationException::withMessages([
                    'deduct_account_id' => __('Insufficient balance on this account (:have available; :need required).', [
                        'have' => number_format($balance, 2),
                        'need' => number_format($amount, 2),
                    ]),
                ]);
            }

            $this->accountService->applyBalanceDeduction($account, $amount);

            $cycle->loadMissing('ruleSet');
            $currency = trim((string) (get_settings('business.currency', '', $business) ?: ($cycle->ruleSet?->currency ?? '')));

            $ledger = new LedgerTransaction([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'deduct_account_id' => $account->id,
                'occurrence_date' => now()->toDateString(),
                'period_number' => 1,
                'amount' => $amount,
                'currency' => $currency !== '' ? $currency : null,
                'cadence_snapshot' => 'payroll',
                'periods_total_snapshot' => 1,
                'meta' => [
                    'payroll_payment' => true,
                    'cycle_name' => $cycle->name,
                    'year' => $cycle->year,
                    'month' => $cycle->month,
                    'employee_count' => $cycle->items()->count(),
                ],
            ]);
            $ledger->transactionable()->associate($cycle);
            $ledger->save();

            return $ledger;
        });
    }
}
