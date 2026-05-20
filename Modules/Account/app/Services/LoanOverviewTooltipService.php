<?php

namespace Modules\Account\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Account\Models\Loan;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class LoanOverviewTooltipService
{
    public function forUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $business = Business::currentForNavbar($user);
        if (! $business) {
            return null;
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        $loans = Loan::query()
            ->with('bank')
            ->where('business_id', $business->id)
            ->latest()
            ->get();

        $recurringLabels = Loan::recurringTypes();
        $rateTypeLabels = Loan::interestRateTypes();

        if ($loans->isEmpty()) {
            return [
                'hasLoans' => false,
                'businessName' => $business->name,
                'loanCount' => 0,
                'loans' => [],
                'totalApproxMonthly' => 0,
                'formattedTotalMonthly' => '0.00',
                'currency' => $currency,
            ];
        }

        $rows = [];
        $totalMonthly = 0.0;

        foreach ($loans as $loan) {
            $n = $this->resolvePeriodCount($loan);
            $periodsSource = $this->periodSourceLabel($loan, $n);
            $payment = $this->paymentPerPeriod($loan, $n);
            $monthlyApprox = $this->approxMonthlyEquivalent($payment, $loan->recurring_type);
            $totalMonthly += $monthlyApprox;

            $ratePct = number_format((float) $loan->interest_rate, 4, '.', '');
            $ratePct = rtrim(rtrim($ratePct, '0'), '.');

            $rows[] = [
                'name' => $loan->name,
                'bankName' => $loan->bank?->name ?? '—',
                'principalFormatted' => number_format((float) $loan->borrowed_amount, 2, '.', ''),
                'rateTypeLabel' => $rateTypeLabels[$loan->interest_rate_type] ?? $loan->interest_rate_type,
                'rateDisplay' => $ratePct.($loan->interest_rate_type === Loan::INTEREST_RATE_PERCENTAGE ? '%' : ''),
                'cadenceLabel' => $recurringLabels[$loan->recurring_type] ?? $loan->recurring_type,
                'periods' => $n,
                'periodsSource' => $periodsSource,
                'installmentFormatted' => number_format($payment, 2, '.', ''),
                'approxMonthlyFormatted' => number_format($monthlyApprox, 2, '.', ''),
                'firstDue' => $loan->first_installment_due_date?->format('M j, Y'),
                'ending' => $loan->loan_ending_date?->format('M j, Y'),
            ];
        }

        return [
            'hasLoans' => true,
            'businessName' => $business->name,
            'loanCount' => count($rows),
            'loans' => $rows,
            'totalApproxMonthly' => round($totalMonthly, 2),
            'formattedTotalMonthly' => number_format($totalMonthly, 2, '.', ''),
            'locale' => app()->getLocale(),
            'currency' => $currency,
        ];
    }

    /**
     * Compact figures for Loan Management list cards (same amortization rules as preview).
     *
     * @return array{period_count:int, period_source:string, payment_per_period:float, payment_formatted:string, approx_monthly:float, approx_monthly_formatted:string, cadence_label:string}
     */
    public function summarizeLoan(Loan $loan): array
    {
        $n = $this->resolvePeriodCount($loan);
        $payment = $this->paymentPerPeriod($loan, $n);
        $monthlyApprox = $this->approxMonthlyEquivalent($payment, $loan->recurring_type);
        $cadenceLabels = Loan::recurringTypes();

        return [
            'period_count' => $n,
            'period_source' => $this->periodSourceLabel($loan, $n),
            'payment_per_period' => $payment,
            'payment_formatted' => number_format($payment, 2, '.', ','),
            'approx_monthly' => $monthlyApprox,
            'approx_monthly_formatted' => number_format($monthlyApprox, 2, '.', ','),
            'cadence_label' => $cadenceLabels[$loan->recurring_type] ?? $loan->recurring_type,
        ];
    }

    /** Payment per scheduled period using same nominal-APR amortization vs flat totals as loan management preview (PHP side). */
    public function paymentPerPeriod(Loan $loan, int $n): float
    {
        $principal = (float) $loan->borrowed_amount;
        if ($n <= 0) {
            return 0.0;
        }

        if ($loan->interest_rate_type === Loan::INTEREST_RATE_PERCENTAGE) {
            $i = $this->periodicInterestDecimal((float) $loan->interest_rate, $loan->recurring_type);

            return $this->amortPayment($principal, $i, $n);
        }

        $flatTotalInterest = $principal * ((float) $loan->interest_rate / 100);
        $total = $principal + $flatTotalInterest;

        return $total / $n;
    }

    private function amortPayment(float $principal, float $i, int $n): float
    {
        if ($n <= 0) {
            return 0.0;
        }

        if ($i <= 0.0) {
            return $principal / $n;
        }

        $pow = pow(1 + $i, $n);

        return ($principal * $i * $pow) / ($pow - 1);
    }

    /** Nominal APR (%) mapped to periodic rate for cadence */
    private function periodicInterestDecimal(float $annualPercent, string $recurringType): float
    {
        $periodsPerYear = $this->periodsPerYear($recurringType);
        if ($periodsPerYear <= 0) {
            return 0.0;
        }

        return ($annualPercent / 100.0) / $periodsPerYear;
    }

    private function periodsPerYear(string $recurring): int
    {
        return match ($recurring) {
            Loan::RECURRING_PER_MONTH => 12,
            Loan::RECURRING_PER_DAY => 365,
            Loan::RECURRING_PER_YEAR => 1,
            default => 12,
        };
    }

    /** Rough monthly budgeting equivalent (daily×30, yearly÷12). */
    private function approxMonthlyEquivalent(float $paymentPerPeriod, string $recurring): float
    {
        return match ($recurring) {
            Loan::RECURRING_PER_MONTH => $paymentPerPeriod,
            Loan::RECURRING_PER_DAY => $paymentPerPeriod * 30.0,
            Loan::RECURRING_PER_YEAR => $paymentPerPeriod / 12.0,
            default => $paymentPerPeriod,
        };
    }

    private function resolvePeriodCount(Loan $loan): int
    {
        $first = $loan->first_installment_due_date;
        $last = $loan->loan_ending_date;

        if ($first instanceof Carbon && $last instanceof Carbon) {
            $count = $this->inclusivePeriodCount($first, $last, $loan->recurring_type);
            if ($count > 0) {
                return $count;
            }
        }

        return $this->assumedPeriodCount($loan->recurring_type);
    }

    private function periodSourceLabel(Loan $loan, int $resolvedN): string
    {
        $first = $loan->first_installment_due_date;
        $last = $loan->loan_ending_date;
        if ($first && $last) {
            $cal = $this->inclusivePeriodCount($first, $last, $loan->recurring_type);
            if ($cal > 0) {
                return 'from first & end dates';
            }
        }

        return 'default assumed term ('.$resolvedN.' periods)';
    }

    private function assumedPeriodCount(string $recur): int
    {
        return match ($recur) {
            Loan::RECURRING_PER_MONTH => 12,
            Loan::RECURRING_PER_DAY => 30,
            Loan::RECURRING_PER_YEAR => 5,
            default => 12,
        };
    }

    /**
     * Due dates for each installment (same cadence / bounds as period count used for payment math).
     *
     * @return Collection<int, Carbon>
     */
    public function installmentScheduleDates(Loan $loan): Collection
    {
        $first = $loan->first_installment_due_date;
        if (! $first instanceof Carbon) {
            return collect();
        }

        $start = $first->copy()->startOfDay();
        $dates = collect();
        $cursor = $start->copy();
        $last = $loan->loan_ending_date?->copy()->startOfDay();

        if ($last instanceof Carbon && $last->lt($start)) {
            return collect();
        }

        $maxIterations = 10000;

        if ($last instanceof Carbon) {
            while ($cursor->lte($last) && $dates->count() < $maxIterations) {
                $dates->push($cursor->copy());
                $this->advanceLoanCadence($cursor, $loan->recurring_type);
            }

            return $dates;
        }

        $n = $this->assumedPeriodCount($loan->recurring_type);

        for ($i = 0; $i < $n && $i < $maxIterations; $i++) {
            $dates->push($cursor->copy());
            $this->advanceLoanCadence($cursor, $loan->recurring_type);
        }

        return $dates;
    }

    /**
     * One row per scheduled installment: expected amount, ledger / external-paid status, and past-due-unpaid flag.
     *
     * @return Collection<int, array{period: int, due: Carbon, due_ymd: string, amount: float, amount_formatted: string, paid: bool, paid_via_ledger: bool, paid_outside_ledger_only: bool, past_due_unpaid: bool, status_label: string, ledger: ?LedgerTransaction}>
     */
    public function installmentScheduleWithPaymentStatus(Loan $loan, ?Carbon $asOf = null): Collection
    {
        $today = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $schedule = $this->installmentScheduleDates($loan);

        if (! $loan->relationLoaded('ledgerTransactions')) {
            $loan->load(['ledgerTransactions.deductAccount.bank', 'ledgerTransactions.deductAccount.bankType']);
        }
        if (! $loan->relationLoaded('externalInstallmentMarks')) {
            $loan->load('externalInstallmentMarks');
        }

        $summary = $this->summarizeLoan($loan);
        $amount = (float) $summary['payment_per_period'];
        $amountFormatted = $summary['payment_formatted'];

        $rows = collect();
        $period = 0;

        foreach ($schedule as $due) {
            $period++;
            $d = $due->copy()->startOfDay();
            $paidViaLedger = $this->loanHasLedgerOnDate($loan, $d);
            $paidOutsideLedgerOnly = ! $paidViaLedger && $this->loanHasExternalPaidMarkOnDate($loan, $d);
            $paid = $paidViaLedger || $paidOutsideLedgerOnly;
            $pastDueUnpaid = $d->lt($today) && ! $paid;

            if ($paidViaLedger) {
                $statusLabel = 'Paid';
            } elseif ($paidOutsideLedgerOnly) {
                $statusLabel = 'Already paid (outside ledger)';
            } elseif ($pastDueUnpaid) {
                $statusLabel = 'Past due · unpaid';
            } else {
                $statusLabel = 'Outstanding';
            }

            $ledgerTx = null;
            if ($paidViaLedger) {
                foreach ($loan->ledgerTransactions as $ledgerRow) {
                    if ($ledgerRow->occurrence_date === null) {
                        continue;
                    }
                    if (Carbon::parse($ledgerRow->occurrence_date)->toDateString() === $due->copy()->startOfDay()->toDateString()) {
                        $ledgerTx = $ledgerRow;
                        break;
                    }
                }
            }

            $rows->push([
                'period' => $period,
                'due' => $due->copy(),
                'due_ymd' => $due->copy()->startOfDay()->toDateString(),
                'amount' => $amount,
                'amount_formatted' => $amountFormatted,
                'paid' => $paid,
                'paid_via_ledger' => $paidViaLedger,
                'paid_outside_ledger_only' => $paidOutsideLedgerOnly,
                'past_due_unpaid' => $pastDueUnpaid,
                'status_label' => $statusLabel,
                'ledger' => $ledgerTx,
            ]);
        }

        return $rows;
    }

    /**
     * True when at least one scheduled installment strictly before calendar day "$asOf" has no ledger row.
     *
     * @param  Carbon|null  $asOf  Compare using this day as “today”, start of day (defaults to application today).
     */
    public function loanHasOverdueInstallments(Loan $loan, ?Carbon $asOf = null): bool
    {
        $today = ($asOf ?? Carbon::today())->copy()->startOfDay();

        if (! $loan->first_installment_due_date instanceof Carbon) {
            return false;
        }

        $schedule = $this->installmentScheduleDates($loan);
        if ($schedule->isEmpty()) {
            return false;
        }

        if (! $loan->relationLoaded('ledgerTransactions')) {
            $loan->load('ledgerTransactions');
        }
        if (! $loan->relationLoaded('externalInstallmentMarks')) {
            $loan->load('externalInstallmentMarks');
        }

        foreach ($schedule as $due) {
            $d = $due->copy()->startOfDay();
            if ($d->gte($today)) {
                break;
            }
            if (! $this->loanInstallmentSatisfied($loan, $d)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when a ledger row exists for the due date, or the installment was marked paid outside SociBiz.
     */
    public function loanInstallmentSatisfied(Loan $loan, Carbon $day): bool
    {
        if (! $loan->relationLoaded('ledgerTransactions')) {
            $loan->load('ledgerTransactions');
        }
        if (! $loan->relationLoaded('externalInstallmentMarks')) {
            $loan->load('externalInstallmentMarks');
        }

        return $this->loanHasLedgerOnDate($loan, $day) || $this->loanHasExternalPaidMarkOnDate($loan, $day);
    }

    /** True if any loan under the business has a past-due installment that is not satisfied (ledger or external mark). */
    public function businessHasOverdueLoanInstallments(Business $business): bool
    {
        $loans = Loan::query()
            ->where('business_id', $business->id)
            ->with(['ledgerTransactions', 'externalInstallmentMarks'])
            ->get();

        foreach ($loans as $loan) {
            if ($this->loanHasOverdueInstallments($loan)) {
                return true;
            }
        }

        return false;
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

    private function loanHasExternalPaidMarkOnDate(Loan $loan, Carbon $day): bool
    {
        $needle = $day->toDateString();

        foreach ($loan->externalInstallmentMarks as $mark) {
            if ($mark->due_date === null) {
                continue;
            }

            if (Carbon::parse($mark->due_date)->toDateString() === $needle) {
                return true;
            }
        }

        return false;
    }

    private function advanceLoanCadence(Carbon $cursor, string $recur): void
    {
        match ($recur) {
            Loan::RECURRING_PER_DAY => $cursor->addDay(),
            Loan::RECURRING_PER_YEAR => $cursor->addYear(),
            Loan::RECURRING_PER_MONTH => $cursor->addMonth(),
            default => $cursor->addMonth(),
        };
    }

    private function inclusivePeriodCount(Carbon $first, Carbon $end, string $recur): int
    {
        $start = $first->copy()->startOfDay();
        $boundary = $end->copy()->startOfDay();
        if ($boundary->lt($start)) {
            return 0;
        }

        $n = 0;
        $d = $start->copy();

        while ($d->lte($boundary)) {
            $n++;
            match ($recur) {
                Loan::RECURRING_PER_DAY => $d->addDay(),
                Loan::RECURRING_PER_MONTH => $d->addMonth(),
                Loan::RECURRING_PER_YEAR => $d->addYear(),
                default => $d->addMonth(),
            };
        }

        return $n;
    }
}
