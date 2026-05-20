<?php

namespace Modules\Account\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Modules\Account\Models\Rental;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class RentalService
{
    public function listForBusiness(Business $business): Collection
    {
        return Rental::query()
            ->with(['warehouse', 'deductAccount.bank', 'deductAccount.bankType'])
            ->where('business_id', $business->id)
            ->latest()
            ->get();
    }

    public function create(User $user, Business $business, array $data): Rental
    {
        $data['user_id'] = $user->id;
        $data['business_id'] = $business->id;

        return Rental::create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function updateForUser(User $user, Rental $rental, array $data): bool
    {
        $businessIds = $user->businesses()->pluck('id')->all();
        if ((int) $rental->user_id !== (int) $user->id || ! in_array((int) $rental->business_id, $businessIds, true)) {
            return false;
        }

        $rental->update($data);

        return true;
    }

    /** Load rental only if owned by user (within their businesses). */
    public function rentalForUser(User $user, Rental $rental): ?Rental
    {
        $businessIds = $user->businesses()->pluck('id')->all();
        if ((int) $rental->user_id !== (int) $user->id || ! in_array((int) $rental->business_id, $businessIds, true)) {
            return null;
        }

        return Rental::query()
            ->whereKey($rental->getKey())
            ->with([
                'business',
                'warehouse',
                'deductAccount.bank',
                'deductAccount.bankType',
                'landlord',
                'ledgerTransactions.deductAccount.bank',
                'ledgerTransactions.deductAccount.bankType',
                'bills' => fn ($q) => $q->orderBy('name')
                    ->with([
                        'ledgerTransactions',
                        'deductAccount.bank',
                        'deductAccount.bankType',
                        'warehouse',
                    ]),
            ])
            ->first();
    }

    /**
     * Countdown to the next billing date from due / first installment, following recurring cadence.
     *
     * @return array{days_until: int, next_date: Carbon, progress_percent: float}|null
     */
    public function nextPaymentInsight(Rental $rental): ?array
    {
        $anchor = $rental->due_date ?? $rental->first_installment_due_date;
        if (! $anchor instanceof Carbon) {
            return null;
        }
        $base = $anchor->copy();

        $base = $base->copy()->startOfDay();
        $today = Carbon::today()->startOfDay();
        $next = $base->copy();

        $guard = 0;
        while ($next->lt($today) && $guard < 2000) {
            $this->addCadence($next, $rental->recurring_type);
            $guard++;
        }

        $daysUntil = (int) $today->diffInDays($next, false);

        $periodEnd = $next->copy();
        $periodStart = $next->copy();
        $this->subCadence($periodStart, $rental->recurring_type);

        $totalDays = max(1, (int) $periodStart->diffInDays($periodEnd));
        $elapsedDays = max(0, min($totalDays, (int) $periodStart->diffInDays($today)));

        return [
            'days_until' => max(0, $daysUntil),
            'next_date' => $next,
            'progress_percent' => round(min(100.0, max(0.0, ($elapsedDays / $totalDays) * 100.0)), 1),
        ];
    }

    /**
     * True when any scheduled billing day on or before calendar day $asOf has no ledger row on that date
     * (same cadence as next payment insight; billing stops after agreement year end).
     */
    public function rentalHasOverduePayments(Rental $rental, ?Carbon $asOf = null): bool
    {
        $today = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $anchor = $rental->due_date ?? $rental->first_installment_due_date;
        if (! $anchor instanceof Carbon) {
            return false;
        }

        if (! $rental->relationLoaded('ledgerTransactions')) {
            $rental->load('ledgerTransactions');
        }

        $leaseEnd = $this->rentalLeaseEndInclusive($rental);
        $due = $anchor->copy()->startOfDay();
        $guard = 0;

        while ($guard < 5000) {
            if ($leaseEnd instanceof Carbon && $due->gt($leaseEnd)) {
                break;
            }
            if ($due->gt($today)) {
                break;
            }
            if (! $this->rentalHasLedgerOnDate($rental, $due)) {
                return true;
            }
            $this->addCadence($due, $rental->recurring_type);
            $guard++;
        }

        return false;
    }

    /**
     * Scheduled billing dates through agreement end, with ledger match per due date.
     *
     * @return BaseCollection<int, array{period: int, due: Carbon, due_ymd: string, amount: float, amount_formatted: string, paid: bool, past_due_unpaid: bool, status_label: string, ledger: ?LedgerTransaction}>
     */
    public function rentalBillingScheduleWithPaymentStatus(Rental $rental, ?Carbon $asOf = null): BaseCollection
    {
        $today = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $schedule = $this->rentalScheduledBillingDates($rental);

        if (! $rental->relationLoaded('ledgerTransactions')) {
            $rental->load(['ledgerTransactions.deductAccount.bank', 'ledgerTransactions.deductAccount.bankType']);
        }

        $amount = (float) $rental->recurring_cost;
        $amountFormatted = number_format($amount, 2, '.', ',');
        $rows = collect();
        $period = 0;

        foreach ($schedule as $due) {
            $period++;
            $d = $due->copy()->startOfDay();
            $paid = $this->rentalHasLedgerOnDate($rental, $d);
            $pastDueUnpaid = $d->lte($today) && ! $paid;

            if ($paid) {
                $statusLabel = 'Paid';
            } elseif ($pastDueUnpaid) {
                $statusLabel = $d->isSameDay($today)
                    ? 'Due today · unpaid'
                    : 'Past due · unpaid';
            } else {
                $statusLabel = 'Outstanding';
            }

            $ledgerTx = null;
            if ($paid) {
                foreach ($rental->ledgerTransactions as $ledgerRow) {
                    if ($ledgerRow->occurrence_date === null) {
                        continue;
                    }
                    if (Carbon::parse($ledgerRow->occurrence_date)->toDateString() === $d->toDateString()) {
                        $ledgerTx = $ledgerRow;
                        break;
                    }
                }
            }

            $rows->push([
                'period' => $period,
                'due' => $due->copy(),
                'due_ymd' => $d->toDateString(),
                'amount' => $amount,
                'amount_formatted' => $amountFormatted,
                'paid' => $paid,
                'past_due_unpaid' => $pastDueUnpaid,
                'status_label' => $statusLabel,
                'ledger' => $ledgerTx,
            ]);
        }

        return $rows;
    }

    /** @return array<int, bool> */
    public function rentalOverdueMapForBusiness(Business $business): array
    {
        $map = [];
        $rentals = Rental::query()
            ->where('business_id', $business->id)
            ->with('ledgerTransactions')
            ->get();

        foreach ($rentals as $rental) {
            $map[(int) $rental->id] = $this->rentalHasOverduePayments($rental);
        }

        return $map;
    }

    public function businessHasOverdueRentalPayments(Business $business): bool
    {
        $rentals = Rental::query()
            ->where('business_id', $business->id)
            ->with('ledgerTransactions')
            ->get();

        foreach ($rentals as $rental) {
            if ($this->rentalHasOverduePayments($rental)) {
                return true;
            }
        }

        return false;
    }

    public function deleteForUser(User $user, Rental $rental): bool
    {
        $businessIds = $user->businesses()->pluck('id')->all();
        if ((int) $rental->user_id !== (int) $user->id || ! in_array((int) $rental->business_id, $businessIds, true)) {
            return false;
        }

        $rental->delete();

        return true;
    }

    private function addCadence(Carbon $date, string $recurring): void
    {
        match ($recurring) {
            Rental::RECURRING_PER_DAY => $date->addDay(),
            Rental::RECURRING_PER_YEAR => $date->addYear(),
            Rental::RECURRING_PER_MONTH => $date->addMonthNoOverflow(),
            default => $date->addMonthNoOverflow(),
        };
    }

    private function subCadence(Carbon $date, string $recurring): void
    {
        match ($recurring) {
            Rental::RECURRING_PER_DAY => $date->subDay(),
            Rental::RECURRING_PER_YEAR => $date->subYear(),
            Rental::RECURRING_PER_MONTH => $date->subMonthNoOverflow(),
            default => $date->subMonthNoOverflow(),
        };
    }

    /**
     * Billing due dates from anchor (due or first installment) through lease end.
     *
     * @return BaseCollection<int, Carbon>
     */
    public function rentalScheduledBillingDates(Rental $rental): BaseCollection
    {
        $anchor = $rental->due_date ?? $rental->first_installment_due_date;
        if (! $anchor instanceof Carbon) {
            return collect();
        }

        $leaseEnd = $this->rentalLeaseEndInclusive($rental);
        $dates = collect();
        $cursor = $anchor->copy()->startOfDay();
        $guard = 0;
        $maxWithoutLeaseEnd = 120;

        while ($guard < 10000) {
            if ($leaseEnd instanceof Carbon && $cursor->gt($leaseEnd)) {
                break;
            }
            if (! $leaseEnd instanceof Carbon && $dates->count() >= $maxWithoutLeaseEnd) {
                break;
            }

            $dates->push($cursor->copy());
            $this->addCadence($cursor, $rental->recurring_type);
            $guard++;
        }

        return $dates;
    }

    private function rentalLeaseEndInclusive(Rental $rental): ?Carbon
    {
        $y = $rental->agreement_valid_until_year;
        if ($y === null || (int) $y < 1900) {
            return null;
        }

        return Carbon::parse((int) $y.'-12-31')->endOfDay();
    }

    private function rentalHasLedgerOnDate(Rental $rental, Carbon $day): bool
    {
        $needle = $day->toDateString();

        foreach ($rental->ledgerTransactions as $row) {
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
