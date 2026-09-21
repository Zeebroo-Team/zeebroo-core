<?php

namespace Modules\Account\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Account;
use Modules\Account\Models\Investment;
use Modules\Business\Models\Business;
use Modules\Pos\Services\PosCashDrawerService;
use Modules\Transaction\Models\LedgerTransaction;

class InvestmentService
{
    public const PAY_FROM_ACCOUNT = 'account';

    public const PAY_FROM_CASH_DRAWER = 'cash_drawer';

    private const MONEY_TOLERANCE = 0.005;

    public function __construct(private readonly AccountService $accountService) {}

    public function listForBusiness(Business $business): Collection
    {
        return Investment::query()
            ->with(['deductAccount', 'ledgerTransactions'])
            ->where('business_id', $business->id)
            ->latest()
            ->get();
    }

    public function create(User $user, Business $business, array $data): Investment
    {
        $data['user_id'] = $user->id;
        $data['business_id'] = $business->id;

        return Investment::create($data);
    }

    public function deleteForBusiness(Investment $investment): void
    {
        $investment->delete();
    }

    /**
     * Scheduled contribution dates (a single date for one-time plans).
     *
     * @return BaseCollection<int, Carbon>
     */
    public function scheduledDates(Investment $investment): BaseCollection
    {
        $anchor = $investment->start_date;
        if (! $anchor instanceof Carbon) {
            return collect();
        }

        if ($investment->isOneTime()) {
            return collect([$anchor->copy()->startOfDay()]);
        }

        $end = $investment->schedule_valid_until_year
            ? Carbon::parse($investment->schedule_valid_until_year.'-12-31')->endOfDay()
            : null;

        $dates = collect();
        $cursor = $anchor->copy()->startOfDay();

        // Without an end year, cap the projected schedule so it never runs forever.
        while ($dates->count() < 10000 && ($end ? $cursor->lte($end) : $dates->count() < 120)) {
            $dates->push($cursor->copy());
            match ($investment->recurring_type) {
                Investment::RECURRING_PER_DAY => $cursor->addDay(),
                Investment::RECURRING_PER_YEAR => $cursor->addYear(),
                default => $cursor->addMonthNoOverflow(),
            };
        }

        return $dates;
    }

    public function paidTowardDate(Investment $investment, Carbon $day): float
    {
        $needle = $day->toDateString();

        return round((float) $investment->ledgerTransactions
            ->filter(fn ($tx) => $tx->occurrence_date?->toDateString() === $needle)
            ->sum(fn ($tx) => (float) $tx->amount), 2);
    }

    /** @return BaseCollection<int, array<string, mixed>> */
    public function scheduleWithStatus(Investment $investment, ?Carbon $asOf = null): BaseCollection
    {
        $today = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $scheduled = round((float) $investment->contribution_amount, 2);
        $period = 0;

        return $this->scheduledDates($investment)->map(function (Carbon $due) use ($investment, $today, $scheduled, &$period): array {
            $period++;
            $paid = $this->paidTowardDate($investment, $due);
            $outstanding = max(0.0, round($scheduled - $paid, 2));
            $fullyPaid = $outstanding <= self::MONEY_TOLERANCE;
            $partial = ! $fullyPaid && $paid >= self::MONEY_TOLERANCE;
            $pastDue = $due->lte($today) && ! $fullyPaid;

            $status = match (true) {
                $fullyPaid => 'Paid',
                $partial && $pastDue => 'Past due · partial',
                $partial => 'Partially paid',
                $pastDue && $due->isSameDay($today) => 'Due today',
                $pastDue => 'Past due',
                default => 'Upcoming',
            };

            return [
                'period' => $period,
                'due_ymd' => $due->toDateString(),
                'amount' => $scheduled,
                'amount_formatted' => number_format($scheduled, 2, '.', ','),
                'paid' => $fullyPaid,
                'partially_paid' => $partial,
                'paid_total' => $paid,
                'paid_total_formatted' => number_format($paid, 2, '.', ','),
                'outstanding' => $outstanding,
                'outstanding_formatted' => number_format($outstanding, 2, '.', ','),
                'past_due_unpaid' => $pastDue,
                'status_label' => $status,
            ];
        })->values();
    }

    /**
     * Portfolio numbers for one investment.
     *
     * @return array<string, mixed>
     */
    public function summary(Investment $investment, ?Carbon $asOf = null): array
    {
        $schedule = $this->scheduleWithStatus($investment, $asOf);
        $invested = round((float) $investment->ledgerTransactions->sum(fn ($tx) => (float) $tx->amount), 2);
        $planned = round((float) $schedule->sum('amount'), 2);
        $goal = $investment->target_amount !== null && (float) $investment->target_amount > 0
            ? round((float) $investment->target_amount, 2)
            : $planned;
        $next = $schedule->first(fn ($row) => ! $row['paid']);
        $overdue = $schedule->where('past_due_unpaid', true)->count();

        $expected = null;
        if ($investment->expected_return_rate !== null && $invested > 0) {
            $expected = round($invested * (float) $investment->expected_return_rate / 100, 2);
        }

        return [
            'total_invested' => $invested,
            'planned_total' => $planned,
            'goal_amount' => $goal,
            'progress_pct' => $goal > 0 ? min(100, (int) round($invested / $goal * 100)) : 0,
            'periods_total' => $schedule->count(),
            'periods_paid' => $schedule->where('paid', true)->count(),
            'overdue_count' => $overdue,
            'next_due_ymd' => $next['due_ymd'] ?? null,
            'expected_annual_return' => $expected,
            'schedule' => $schedule,
        ];
    }

    /**
     * Post a contribution against a scheduled date, debiting the chosen account (petty cash included).
     */
    public function contribute(
        Investment $investment,
        Business $business,
        User $user,
        string $occurrenceDateYmd,
        ?int $accountId,
        ?float $amount = null,
        string $payFrom = self::PAY_FROM_ACCOUNT,
    ): LedgerTransaction {
        $occurrence = Carbon::parse($occurrenceDateYmd)->startOfDay();

        $schedule = $this->scheduledDates($investment);
        $idx = $schedule->search(fn (Carbon $d) => $d->toDateString() === $occurrence->toDateString());
        if ($idx === false) {
            throw ValidationException::withMessages(['occurrence_date' => 'That date is not on this investment schedule.']);
        }

        return DB::transaction(function () use ($investment, $business, $user, $occurrence, $accountId, $amount, $idx, $schedule, $payFrom) {
            $investment->unsetRelation('ledgerTransactions');
            $investment->load('ledgerTransactions');

            $outstanding = round(max(0.0, (float) $investment->contribution_amount - $this->paidTowardDate($investment, $occurrence)), 2);
            if ($outstanding <= self::MONEY_TOLERANCE) {
                throw ValidationException::withMessages(['occurrence_date' => 'This contribution date is already fully paid.']);
            }

            $pay = $amount !== null ? round($amount, 2) : $outstanding;
            if ($pay > $outstanding + self::MONEY_TOLERANCE) {
                throw ValidationException::withMessages(['amount' => 'Amount cannot exceed the outstanding '.number_format($outstanding, 2, '.', ',').'.']);
            }

            $account = null;
            $withdrawalId = null;

            if ($payFrom === self::PAY_FROM_CASH_DRAWER) {
                $drawer = app(PosCashDrawerService::class);
                $status = $drawer->todayStatus($business);
                if (! $status['is_opened']) {
                    throw ValidationException::withMessages(['pay_from' => 'Open today\'s cash drawer (opening float) before paying from it.']);
                }
                if ($pay > (float) $status['balance'] + self::MONEY_TOLERANCE) {
                    throw ValidationException::withMessages(['amount' => 'Cash drawer balance is only '.number_format((float) $status['balance'], 2, '.', ',').'.']);
                }
                $withdrawalId = $drawer->addWithdrawal(
                    $business,
                    $pay,
                    'Investment: '.$investment->name.' ('.$occurrence->toDateString().')',
                    $user->id,
                )->id;
            } else {
                $account = Account::query()
                    ->whereKey($accountId)
                    ->where('user_id', $user->id)
                    ->where('business_id', $business->id)
                    ->lockForUpdate()
                    ->first();
                if ($account === null) {
                    throw ValidationException::withMessages(['deduct_account_id' => 'Choose an account that belongs to your business.']);
                }

                $this->accountService->applyBalanceDeduction($account, $pay);
            }

            $currency = (string) (get_settings('business.currency', '', $business) ?: '');

            return $investment->ledgerTransactions()->create([
                'business_id' => $investment->business_id,
                'user_id' => $investment->user_id,
                'deduct_account_id' => $account?->id,
                'occurrence_date' => $occurrence->toDateString(),
                'period_number' => $idx + 1,
                'amount' => $pay,
                'currency' => $currency !== '' ? $currency : null,
                'cadence_snapshot' => $investment->isOneTime() ? Investment::PAYMENT_MODE_ONE_TIME : $investment->recurring_type,
                'periods_total_snapshot' => $schedule->count(),
                'meta' => [
                    'settlement_source' => 'investment_contribution',
                    'investment_name_snapshot' => $investment->name,
                    'investment_type_snapshot' => $investment->typeDisplayLabel(),
                    'pay_from' => $payFrom,
                    'account_category_snapshot' => $account?->category,
                    'cash_withdrawal_id' => $withdrawalId,
                ],
            ]);
        });
    }
}
