<?php

namespace Modules\Account\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Modules\Account\Models\Bill;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class BillService
{
    private const MONEY_TOLERANCE = 0.005;

    public function listForBusiness(Business $business): Collection
    {
        return Bill::query()
            ->with(['warehouse', 'rental', 'property', 'employee', 'modification', 'department', 'deductAccount.bank', 'deductAccount.bankType'])
            ->where('business_id', $business->id)
            ->latest()
            ->get();
    }

    public function create(User $user, Business $business, array $data): Bill
    {
        $data['user_id'] = $user->id;
        $data['business_id'] = $business->id;

        return Bill::create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function updateForUser(User $user, Bill $bill, array $data): bool
    {
        $businessIds = $user->businesses()->pluck('id')->all();
        if ((int) $bill->user_id !== (int) $user->id || ! in_array((int) $bill->business_id, $businessIds, true)) {
            return false;
        }

        $bill->update($data);

        return true;
    }

    /** Load bill only if owned by user (within their businesses). */
    public function billForUser(User $user, Bill $bill): ?Bill
    {
        $businessIds = $user->businesses()->pluck('id')->all();
        if ((int) $bill->user_id !== (int) $user->id || ! in_array((int) $bill->business_id, $businessIds, true)) {
            return null;
        }

        return Bill::query()
            ->whereKey($bill->getKey())
            ->with([
                'business',
                'warehouse',
                'rental',
                'property',
                'employee',
                'modification',
                'department',
                'deductAccount.bank',
                'deductAccount.bankType',
                'ledgerTransactions.deductAccount.bank',
                'ledgerTransactions.deductAccount.bankType',
            ])
            ->first();
    }

    /**
     * @return array{days_until: int, next_date: Carbon, progress_percent: float}|null
     */
    public function nextPaymentInsight(Bill $bill): ?array
    {
        if ($bill->isOneTime()) {
            if (! $bill->relationLoaded('ledgerTransactions')) {
                $bill->load('ledgerTransactions');
            }
            $schedule = $this->billScheduledBillingDates($bill);
            if ($schedule->isEmpty()) {
                return null;
            }
            $only = $schedule->first()->copy()->startOfDay();
            if ($this->billPeriodFullyPaidAtDate($bill, $only)) {
                return null;
            }
            $today = Carbon::today()->startOfDay();
            $daysUntil = $today->lte($only) ? (int) $today->diffInDays($only, false) : 0;
            $windowStart = $only->copy()->subDays(14);
            $totalDays = 14;
            $elapsed = (int) $windowStart->diffInDays($today, false);
            $elapsed = max(0, min($totalDays, $elapsed));
            $progress = $only->lte($today) ? 100.0 : round(min(100.0, ($elapsed / $totalDays) * 100.0), 1);

            return [
                'days_until' => max(0, $daysUntil),
                'next_date' => $only,
                'progress_percent' => $progress,
            ];
        }

        $anchor = $bill->due_date ?? $bill->first_installment_due_date;
        if (! $anchor instanceof Carbon) {
            return null;
        }
        $base = $anchor->copy()->startOfDay();
        $today = Carbon::today()->startOfDay();
        $next = $base->copy();

        $guard = 0;
        while ($next->lt($today) && $guard < 2000) {
            $this->addCadence($next, $bill->recurring_type);
            $guard++;
        }

        $daysUntil = (int) $today->diffInDays($next, false);

        $periodEnd = $next->copy();
        $periodStart = $next->copy();
        $this->subCadence($periodStart, $bill->recurring_type);

        $totalDays = max(1, (int) $periodStart->diffInDays($periodEnd));
        $elapsedDays = max(0, min($totalDays, (int) $periodStart->diffInDays($today)));

        return [
            'days_until' => max(0, $daysUntil),
            'next_date' => $next,
            'progress_percent' => round(min(100.0, max(0.0, ($elapsedDays / $totalDays) * 100.0)), 1),
        ];
    }

    public function billHasOverduePayments(Bill $bill, ?Carbon $asOf = null): bool
    {
        $today = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $anchor = $bill->due_date ?? $bill->first_installment_due_date;
        if (! $anchor instanceof Carbon) {
            return false;
        }

        if (! $bill->relationLoaded('ledgerTransactions')) {
            $bill->load('ledgerTransactions');
        }

        $scheduleEnd = $this->billScheduleEndInclusive($bill);
        $due = $anchor->copy()->startOfDay();
        $guard = 0;

        while ($guard < 5000) {
            if ($scheduleEnd instanceof Carbon && $due->gt($scheduleEnd)) {
                break;
            }
            if ($due->gt($today)) {
                break;
            }
            if (! $this->billPeriodFullyPaidAtDate($bill, $due)) {
                return true;
            }
            $this->addCadence($due, $bill->recurring_type);
            $guard++;
        }

        return false;
    }

    /** Sum posted toward a scheduled occurrence (multiple partial/split ledger rows supported). */
    public function billAmountPaidTowardScheduledDate(Bill $bill, Carbon $day): float
    {
        if (! $bill->relationLoaded('ledgerTransactions')) {
            $bill->load('ledgerTransactions');
        }
        $needle = $day->copy()->startOfDay()->toDateString();
        $sum = 0.0;

        foreach ($bill->ledgerTransactions as $ledgerRow) {
            if ($ledgerRow->occurrence_date === null) {
                continue;
            }
            if (Carbon::parse($ledgerRow->occurrence_date)->toDateString() === $needle) {
                $sum += (float) $ledgerRow->amount;
            }
        }

        return round($sum, 2);
    }

    /**
     * Fixed bills: always the saved recurring amount.
     * Usage-based bills: total charge for that period once recorded (from any ledger row’s meta).
     */
    public function billPeriodChargeDeclaredTotal(Bill $bill, Carbon $day): ?float
    {
        foreach ($this->billLedgerTransactionsForScheduledDate($bill, $day) as $row) {
            $raw = $row->meta['period_charge_total'] ?? null;
            if ($raw !== null && is_numeric($raw) && (float) $raw > self::MONEY_TOLERANCE) {
                return round((float) $raw, 2);
            }
        }

        return null;
    }

    /**
     * Ceiling for what can be paid toward this occurrence: recurring_cost (fixed) or declared invoice (usage).
     * Null means usage-based and the period total is not set yet (user supplies it when posting the first payment).
     */
    public function billResolvedPeriodChargeCap(Bill $bill, Carbon $day): ?float
    {
        if (! $bill->amount_varies_by_usage) {
            return round((float) $bill->recurring_cost, 2);
        }

        return $this->billPeriodChargeDeclaredTotal($bill, $day);
    }

    public function billNeedsPeriodChargeDeclaration(Bill $bill, Carbon $day): bool
    {
        return $bill->amount_varies_by_usage && $this->billPeriodChargeDeclaredTotal($bill, $day) === null;
    }

    /** Remaining unpaid toward the period cap, or null when usage-based and cap not declared yet. */
    public function billScheduledPeriodOutstandingAmount(Bill $bill, Carbon $day): ?float
    {
        $cap = $this->billResolvedPeriodChargeCap($bill, $day);
        if ($cap === null) {
            return null;
        }
        $paid = $this->billAmountPaidTowardScheduledDate($bill, $day);

        return max(0.0, round($cap - $paid, 2));
    }

    /** True when postings cover the full amount for this occurrence. */
    public function billPeriodFullyPaidAtDate(Bill $bill, Carbon $day): bool
    {
        $cap = $this->billResolvedPeriodChargeCap($bill, $day);
        if ($cap === null) {
            return false;
        }

        $remaining = $this->billScheduledPeriodOutstandingAmount($bill, $day);

        return $remaining !== null && $remaining <= self::MONEY_TOLERANCE;
    }

    /**
     * Ledger rows tied to one scheduled occurrence, oldest first (for summaries / receipts).
     *
     * @return BaseCollection<int, LedgerTransaction>
     */
    public function billLedgerTransactionsForScheduledDate(Bill $bill, Carbon $day): BaseCollection
    {
        if (! $bill->relationLoaded('ledgerTransactions')) {
            $bill->load(['ledgerTransactions.deductAccount.bank', 'ledgerTransactions.deductAccount.bankType']);
        }
        $needle = $day->copy()->startOfDay()->toDateString();

        return $bill->ledgerTransactions->filter(function (LedgerTransaction $ledgerRow) use ($needle): bool {
            if ($ledgerRow->occurrence_date === null) {
                return false;
            }

            return Carbon::parse($ledgerRow->occurrence_date)->toDateString() === $needle;
        })->sortBy('id')->values();
    }

    /**
     * @return BaseCollection<int, array<string, mixed>>
     */
    public function billBillingScheduleWithPaymentStatus(Bill $bill, ?Carbon $asOf = null): BaseCollection
    {
        $today = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $schedule = $this->billScheduledBillingDates($bill);

        if (! $bill->relationLoaded('ledgerTransactions')) {
            $bill->load(['ledgerTransactions.deductAccount.bank', 'ledgerTransactions.deductAccount.bankType']);
        }

        $estimate = round((float) $bill->recurring_cost, 2);
        $rows = collect();
        $period = 0;

        foreach ($schedule as $due) {
            $period++;
            $d = $due->copy()->startOfDay();
            $paidTotal = $this->billAmountPaidTowardScheduledDate($bill, $d);
            $declared = $bill->amount_varies_by_usage ? $this->billPeriodChargeDeclaredTotal($bill, $d) : null;
            $cap = $this->billResolvedPeriodChargeCap($bill, $d);

            if ($bill->amount_varies_by_usage) {
                if ($declared !== null) {
                    $amountFormatted = number_format($declared, 2, '.', ',');
                    $amount = $declared;
                } elseif ($estimate > self::MONEY_TOLERANCE) {
                    $amountFormatted = '~'.number_format($estimate, 2, '.', ',').' (typical)';
                    $amount = $estimate;
                } else {
                    $amountFormatted = 'Varies by usage';
                    $amount = 0.0;
                }
            } else {
                $amount = $estimate;
                $amountFormatted = number_format($amount, 2, '.', ',');
            }

            $outstanding = $cap === null ? null : max(0.0, round($cap - $paidTotal, 2));
            $fullyPaid = $cap !== null && $outstanding !== null && $outstanding <= self::MONEY_TOLERANCE;
            $hasSomePayment = ! $fullyPaid && $paidTotal >= self::MONEY_TOLERANCE;

            $pastDueUnpaid = $d->lte($today) && ! $fullyPaid;

            $ledgerMatches = $this->billLedgerTransactionsForScheduledDate($bill, $d);
            $ledgerTx = $ledgerMatches->isNotEmpty() ? $ledgerMatches->last() : null;

            if ($fullyPaid) {
                $statusLabel = $ledgerMatches->count() > 1 ? 'Paid (split/partials)' : 'Paid';
            } elseif ($hasSomePayment && $pastDueUnpaid) {
                $statusLabel = $d->isSameDay($today)
                    ? 'Due today · partial'
                    : 'Past due · partial';
            } elseif ($hasSomePayment) {
                $statusLabel = 'Partially paid';
            } elseif ($bill->amount_varies_by_usage && $cap === null && $paidTotal <= self::MONEY_TOLERANCE) {
                $statusLabel = $pastDueUnpaid ? ($d->isSameDay($today) ? 'Due · set invoice total when paying' : 'Past due · set invoice total') : 'Awaiting usage / invoice';
            } elseif ($pastDueUnpaid) {
                $statusLabel = $d->isSameDay($today)
                    ? 'Due today · unpaid'
                    : 'Past due · unpaid';
            } else {
                $statusLabel = 'Outstanding';
            }

            $outstandingFormatted = $outstanding === null ? '—' : number_format($outstanding, 2, '.', ',');

            $rows->push([
                'period' => $period,
                'due' => $due->copy(),
                'due_ymd' => $d->toDateString(),
                'amount' => $amount,
                'amount_formatted' => $amountFormatted,
                'paid' => $fullyPaid,
                'partially_paid' => $hasSomePayment,
                'paid_total' => $paidTotal,
                'paid_total_formatted' => number_format($paidTotal, 2, '.', ','),
                'outstanding' => $outstanding ?? 0.0,
                'outstanding_raw' => $outstanding,
                'outstanding_formatted' => $outstandingFormatted,
                'needs_period_charge_declaration' => $this->billNeedsPeriodChargeDeclaration($bill, $d),
                'past_due_unpaid' => $pastDueUnpaid,
                'status_label' => $statusLabel,
                'ledger' => $ledgerTx,
                'ledger_rows' => $ledgerMatches,
                'period_charge_declared' => $declared,
            ]);
        }

        return $rows;
    }

    /** @return array<int, bool> */
    public function billOverdueMapForBusiness(Business $business): array
    {
        $map = [];
        $bills = Bill::query()
            ->where('business_id', $business->id)
            ->with('ledgerTransactions')
            ->get();

        foreach ($bills as $bill) {
            $map[(int) $bill->id] = $this->billHasOverduePayments($bill);
        }

        return $map;
    }

    public function businessHasOverdueBillPayments(Business $business): bool
    {
        $bills = Bill::query()
            ->where('business_id', $business->id)
            ->with('ledgerTransactions')
            ->get();

        foreach ($bills as $bill) {
            if ($this->billHasOverduePayments($bill)) {
                return true;
            }
        }

        return false;
    }

    public function deleteForUser(User $user, Bill $bill): bool
    {
        $businessIds = $user->businesses()->pluck('id')->all();
        if ((int) $bill->user_id !== (int) $user->id || ! in_array((int) $bill->business_id, $businessIds, true)) {
            return false;
        }

        $bill->delete();

        return true;
    }

    private function addCadence(Carbon $date, string $recurring): void
    {
        match ($recurring) {
            Bill::RECURRING_PER_DAY => $date->addDay(),
            Bill::RECURRING_PER_YEAR => $date->addYear(),
            Bill::RECURRING_PER_MONTH => $date->addMonthNoOverflow(),
            default => $date->addMonthNoOverflow(),
        };
    }

    private function subCadence(Carbon $date, string $recurring): void
    {
        match ($recurring) {
            Bill::RECURRING_PER_DAY => $date->subDay(),
            Bill::RECURRING_PER_YEAR => $date->subYear(),
            Bill::RECURRING_PER_MONTH => $date->subMonthNoOverflow(),
            default => $date->subMonthNoOverflow(),
        };
    }

    /**
     * Scheduled billing dates through agreement end.
     *
     * @return BaseCollection<int, Carbon>
     */
    public function billScheduledBillingDates(Bill $bill): BaseCollection
    {
        $anchor = $bill->due_date ?? $bill->first_installment_due_date;
        if (! $anchor instanceof Carbon) {
            return collect();
        }

        if ($bill->isOneTime()) {
            return collect([$anchor->copy()->startOfDay()]);
        }

        $scheduleEnd = $this->billScheduleEndInclusive($bill);
        $dates = collect();
        $cursor = $anchor->copy()->startOfDay();
        $guard = 0;
        $maxWithoutScheduleEnd = 120;

        while ($guard < 10000) {
            if ($scheduleEnd instanceof Carbon && $cursor->gt($scheduleEnd)) {
                break;
            }
            if (! $scheduleEnd instanceof Carbon && $dates->count() >= $maxWithoutScheduleEnd) {
                break;
            }

            $dates->push($cursor->copy());
            $this->addCadence($cursor, $bill->recurring_type);
            $guard++;
        }

        return $dates;
    }

    private function billScheduleEndInclusive(Bill $bill): ?Carbon
    {
        $y = $bill->agreement_valid_until_year;
        if ($y === null || (int) $y < 1900) {
            return null;
        }

        return Carbon::parse((int) $y.'-12-31')->endOfDay();
    }
}
