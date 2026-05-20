<?php

namespace Modules\Transaction\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Account\Models\Account;
use Modules\Account\Models\Loan;
use Modules\Account\Services\AccountService;
use Modules\Account\Services\LoanOverviewTooltipService;

class LoanRecurringDeductionService
{
    public function __construct(
        private readonly LoanOverviewTooltipService $loanSchedule,
        private readonly AccountService $accountService,
    ) {}

    /** @return array{processed_loans:int, entries_created:int} */
    public function run(?Carbon $asOf = null): array
    {
        $day = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $created = 0;
        $loansTouched = 0;

        $query = Loan::query()
            ->whereNotNull('deduct_account_id')
            ->whereNotNull('first_installment_due_date');

        foreach ($query->cursor() as $loan) {
            $loanCreated = $this->processLoan($loan, $day);
            $created += $loanCreated;
            if ($loanCreated > 0) {
                $loansTouched++;
            }
        }

        return ['processed_loans' => $loansTouched, 'entries_created' => $created];
    }

    /** Create missing ledger rows for due installments on or before $asOf. */
    public function processLoan(Loan $loan, Carbon $asOf): int
    {
        if ($loan->deduct_account_id === null) {
            return 0;
        }

        $loan->loadMissing('business');

        $summary = $this->loanSchedule->summarizeLoan($loan);
        $schedule = $this->loanSchedule->installmentScheduleDates($loan);

        if ($schedule->isEmpty()) {
            return 0;
        }

        $amount = $summary['payment_per_period'];
        if ($amount <= 0) {
            return 0;
        }

        $currency = (string) (get_settings('business.currency', '', $loan->business) ?: '');

        $created = 0;
        $periodIndex = 0;

        foreach ($schedule as $due) {
            $periodIndex++;
            /** @var Carbon $due */
            if ($due->gt($asOf)) {
                break;
            }

            $created += $this->ensureSingleDeduction(
                loan: $loan,
                due: $due,
                periodIndex: $periodIndex,
                amount: $amount,
                currency: $currency,
                cadenceSnapshot: $loan->recurring_type,
                periodsTotal: $summary['period_count']
            );
        }

        return $created;
    }

    private function ensureSingleDeduction(
        Loan $loan,
        Carbon $due,
        int $periodIndex,
        float $amount,
        string $currency,
        string $cadenceSnapshot,
        int $periodsTotal,
    ): int {
        return (int) DB::transaction(function () use (
            $loan,
            $due,
            $periodIndex,
            $amount,
            $currency,
            $cadenceSnapshot,
            $periodsTotal
        ): int {
            $account = Account::query()
                ->whereKey((int) $loan->deduct_account_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($loan->ledgerTransactions()
                ->whereDate('occurrence_date', $due->toDateString())
                ->exists()) {
                return 0;
            }

            $this->accountService->applyBalanceDeduction($account, $amount);

            $loan->ledgerTransactions()->create([
                'business_id' => $loan->business_id,
                'user_id' => $loan->user_id,
                'deduct_account_id' => $loan->deduct_account_id,
                'occurrence_date' => $due->toDateString(),
                'period_number' => $periodIndex,
                'amount' => $amount,
                'currency' => $currency !== '' ? $currency : null,
                'cadence_snapshot' => $cadenceSnapshot,
                'periods_total_snapshot' => $periodsTotal,
                'meta' => [
                    'borrowed_principal_snapshot' => (float) $loan->borrowed_amount,
                ],
            ]);

            return 1;
        });
    }
}
