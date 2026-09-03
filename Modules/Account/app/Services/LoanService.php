<?php

namespace Modules\Account\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Account\Models\Loan;
use Modules\Business\Models\Business;

class LoanService
{
    public function listForBusiness(?Business $business): Collection
    {
        if (! $business) {
            return new Collection([]);
        }

        return Loan::with([
            'bank',
            'deductAccount.bank',
            'deductAccount.bankType',
            'ledgerTransactions.deductAccount.bank',
            'ledgerTransactions.deductAccount.bankType',
        ])
            ->where('business_id', $business->id)
            ->latest()
            ->get();
    }

    public function create(User $user, Business $business, array $data): Loan
    {
        $data['user_id'] = $user->id;
        $data['business_id'] = $business->id;

        $loan = Loan::create($data);

        try {
            app(\Modules\AutomationEditor\Services\AutomationRunnerService::class)->dispatch('loan.created', $business, [
                'event' => 'loan.created',
                'loan'  => [
                    'id'               => $loan->id,
                    'name'             => $loan->name,
                    'borrowed_amount'  => (float) $loan->borrowed_amount,
                    'interest_rate'    => (float) $loan->interest_rate,
                    'recurring_type'   => $loan->recurring_type,
                    'created_at'       => $loan->created_at?->toIso8601String(),
                ],
            ]);
        } catch (\Throwable) {}

        return $loan;
    }

    /** Load loan with relations only if owned by user (scoped to businesses they belong to). */
    public function loanForUser(User $user, Loan $loan): ?Loan
    {
        $businessIds = $user->businesses()->pluck('id')->all();
        if ($loan->user_id !== $user->id || ! in_array($loan->business_id, $businessIds, true)) {
            return null;
        }

        return Loan::query()
            ->whereKey($loan->getKey())
            ->with([
                'bank',
                'deductAccount.bank',
                'deductAccount.bankType',
                'ledgerTransactions.deductAccount.bank',
                'ledgerTransactions.deductAccount.bankType',
            ])
            ->first();
    }

    public function deleteForUser(User $user, Loan $loan): bool
    {
        $businessIds = $user->businesses()->pluck('id')->all();
        if ($loan->user_id !== $user->id || ! in_array($loan->business_id, $businessIds, true)) {
            return false;
        }

        $loan->delete();

        return true;
    }

    /**
     * True when any scheduled installment on or before $asOf has no ledger row on that date
     * (same cadence walk as Bill/Rental overdue checks; installments stop at loan_ending_date).
     */
    public function loanHasOverduePayments(Loan $loan, ?Carbon $asOf = null): bool
    {
        $today = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $anchor = $loan->first_installment_due_date;
        if (! $anchor instanceof Carbon) {
            return false;
        }

        if (! $loan->relationLoaded('ledgerTransactions')) {
            $loan->load('ledgerTransactions');
        }

        $scheduleEnd = $loan->loan_ending_date instanceof Carbon
            ? $loan->loan_ending_date->copy()->endOfDay()
            : null;
        $due = $anchor->copy()->startOfDay();
        $guard = 0;

        while ($guard < 5000) {
            if ($scheduleEnd instanceof Carbon && $due->gt($scheduleEnd)) {
                break;
            }
            if ($due->gt($today)) {
                break;
            }
            if (! $this->loanHasLedgerOnDate($loan, $due)) {
                return true;
            }
            $this->addCadence($due, $loan->recurring_type);
            $guard++;
        }

        return false;
    }

    /** @return array<int, bool> */
    public function loanOverdueMapForBusiness(Business $business): array
    {
        $map = [];
        $loans = Loan::query()
            ->where('business_id', $business->id)
            ->with('ledgerTransactions')
            ->get();

        foreach ($loans as $loan) {
            $map[(int) $loan->id] = $this->loanHasOverduePayments($loan);
        }

        return $map;
    }

    public function businessHasOverdueLoanPayments(Business $business): bool
    {
        return in_array(true, $this->loanOverdueMapForBusiness($business), true);
    }

    private function addCadence(Carbon $date, string $recurring): void
    {
        match ($recurring) {
            Loan::RECURRING_PER_DAY => $date->addDay(),
            Loan::RECURRING_PER_YEAR => $date->addYear(),
            Loan::RECURRING_PER_MONTH => $date->addMonthNoOverflow(),
            default => $date->addMonthNoOverflow(),
        };
    }

    private function loanHasLedgerOnDate(Loan $loan, Carbon $day): bool
    {
        $needle = $day->toDateString();

        foreach ($loan->ledgerTransactions as $row) {
            if ($row->occurrence_date === null) {
                continue;
            }
            if (Carbon::parse($row->occurrence_date)->toDateString() === $needle) {
                return true;
            }
        }

        return false;
    }
}
