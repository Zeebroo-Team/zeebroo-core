<?php

namespace Modules\Account\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Loan;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class LoanExternalInstallmentMarkService
{
    public function __construct(
        private readonly LoanOverviewTooltipService $loanSchedule,
    ) {}

    /**
     * Record that this schedule due date was paid outside SociBiz (no ledger row, no account debit).
     */
    public function mark(Loan $loan, Business $business, User $user, string $occurrenceDateYmd): void
    {
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
        $onSchedule = false;
        foreach ($schedule as $dueDate) {
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

        DB::transaction(function () use ($loan, $occurrence): void {
            $ledgerExists = LedgerTransaction::query()
                ->where('transactionable_type', Loan::class)
                ->where('transactionable_id', $loan->getKey())
                ->whereDate('occurrence_date', $occurrence->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($ledgerExists) {
                throw ValidationException::withMessages([
                    'occurrence_date' => 'This installment already has a ledger payment.',
                ]);
            }

            $loan->externalInstallmentMarks()->firstOrCreate(
                ['due_date' => $occurrence->toDateString()],
                [],
            );
        });
    }

    public function unmark(Loan $loan, Business $business, User $user, string $occurrenceDateYmd): void
    {
        if ($loan->user_id !== $user->id || (int) $loan->business_id !== (int) $business->id) {
            abort(403);
        }

        try {
            $occurrence = Carbon::parse($occurrenceDateYmd)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Invalid installment date.',
            ]);
        }

        $loan->externalInstallmentMarks()
            ->whereDate('due_date', $occurrence->toDateString())
            ->delete();
    }
}
