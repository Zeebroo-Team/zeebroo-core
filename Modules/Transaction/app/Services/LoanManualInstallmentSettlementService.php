<?php

namespace Modules\Transaction\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Account;
use Modules\Account\Models\Loan;
use Modules\Account\Services\AccountService;
use Modules\Account\Services\LoanOverviewTooltipService;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class LoanManualInstallmentSettlementService
{
    public function __construct(
        private readonly LoanOverviewTooltipService $loanSchedule,
        private readonly AccountService $accountService,
    ) {}

    /**
     * Create a ledger row for one scheduled installment, deduct balance from chosen account (same uniqueness rules as nightly job).
     */
    public function settle(
        Loan $loan,
        Business $business,
        User $user,
        string $occurrenceDateYmd,
        int $deductAccountId,
    ): LedgerTransaction {
        if ($loan->user_id !== $user->id || (int) $loan->business_id !== (int) $business->id) {
            abort(403);
        }

        $loan->loadMissing(['business']);

        try {
            $occurrence = Carbon::parse($occurrenceDateYmd)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Invalid installment date.',
            ]);
        }

        $schedule = $this->loanSchedule->installmentScheduleDates($loan);
        $periodNumber = 0;
        $onSchedule = false;
        foreach ($schedule as $dueDate) {
            $periodNumber++;
            if ($dueDate->copy()->startOfDay()->toDateString() === $occurrence->toDateString()) {
                $onSchedule = true;
                break;
            }
        }
        if (! $onSchedule || $schedule->isEmpty()) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'That date is not on this loan’s installment schedule.',
            ]);
        }

        $summary = $this->loanSchedule->summarizeLoan($loan);
        $amount = (float) $summary['payment_per_period'];
        if ($amount <= 0.0) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Computed installment amount is zero; cannot settle.',
            ]);
        }

        return DB::transaction(function () use ($loan, $user, $business, $occurrence, $deductAccountId, $amount, $periodNumber, $summary): LedgerTransaction {
            $ledgerExists = LedgerTransaction::query()
                ->where('transactionable_type', Loan::class)
                ->where('transactionable_id', $loan->getKey())
                ->whereDate('occurrence_date', $occurrence->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($ledgerExists) {
                throw ValidationException::withMessages([
                    'occurrence_date' => 'This installment is already recorded.',
                ]);
            }

            $externalMarkExists = $loan->externalInstallmentMarks()
                ->whereDate('due_date', $occurrence->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($externalMarkExists) {
                throw ValidationException::withMessages([
                    'occurrence_date' => 'Remove the “already paid” mark before recording a ledger payment.',
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
                    'deduct_account_id' => 'Choose an account belonging to your business.',
                ]);
            }

            $this->accountService->applyBalanceDeduction($account, $amount);

            $currency = (string) (get_settings('business.currency', '', $loan->business) ?: '');

            return $loan->ledgerTransactions()->create([
                'business_id' => $loan->business_id,
                'user_id' => $loan->user_id,
                'deduct_account_id' => $account->id,
                'occurrence_date' => $occurrence->toDateString(),
                'period_number' => $periodNumber,
                'amount' => $amount,
                'currency' => $currency !== '' ? $currency : null,
                'cadence_snapshot' => $loan->recurring_type,
                'periods_total_snapshot' => $summary['period_count'],
                'meta' => [
                    'borrowed_principal_snapshot' => (float) $loan->borrowed_amount,
                    'settlement_source' => 'manual',
                ],
            ]);
        });
    }
}
