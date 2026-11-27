<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Auth\Models\AffiliateReferral;
use Modules\Business\Models\Business;
use Modules\Payment\Models\Payment;

/**
 * Commission an affiliate earns from the plan payments of the accounts they referred.
 *
 * Per referred account, successful plan payments are numbered oldest first:
 *   - payments 1 to 3 earn half of the amount paid;
 *   - every payment after that earns a flat LKR 250.
 *
 * Worked out from the payments table on every view (nothing is stored), so a
 * refunded or failed payment simply drops out and the numbering follows.
 */
class AffiliateCommissionService
{
    /** How many of an account's first payments earn the percentage commission. */
    public const FIRST_PAYMENTS = 3;

    public const FIRST_PAYMENTS_RATE = 0.5;

    /** Flat commission for every payment after the first ones. */
    public const LATER_PAYMENT_COMMISSION = 250.0;

    public const LATER_PAYMENT_CURRENCY = 'LKR';

    public const RULE_FIRST = 'first';

    public const RULE_LATER = 'later';

    /**
     * Every commission entry for the affiliate, newest first.
     *
     * @return Collection<int, array{payment: Payment, referred: ?User, number: int, rule: string,
     *     amount: float, currency: string, commission: float, commission_currency: string, paid_at: Carbon}>
     */
    public function entries(User $affiliate): Collection
    {
        $referrals = AffiliateReferral::query()
            ->where('affiliate_user_id', $affiliate->id)
            ->with('referredUser:id,name,email')
            ->get()
            ->keyBy('referred_user_id');

        $entries = collect();

        foreach ($this->paymentsByReferredUser($referrals->keys()) as $referredId => $payments) {
            foreach ($payments->values() as $index => $payment) {
                $number = $index + 1;
                $isFirst = $number <= self::FIRST_PAYMENTS;
                $amount = (float) $payment->amount;
                $currency = strtoupper((string) ($payment->currency ?: self::LATER_PAYMENT_CURRENCY));

                $entries->push([
                    'payment' => $payment,
                    'referred' => $referrals[$referredId]->referredUser ?? null,
                    'number' => $number,
                    'rule' => $isFirst ? self::RULE_FIRST : self::RULE_LATER,
                    'amount' => $amount,
                    'currency' => $currency,
                    'commission' => $isFirst ? round($amount * self::FIRST_PAYMENTS_RATE, 2) : self::LATER_PAYMENT_COMMISSION,
                    'commission_currency' => $isFirst ? $currency : self::LATER_PAYMENT_CURRENCY,
                    'paid_at' => $this->paidAt($payment),
                ]);
            }
        }

        return $entries
            ->sortByDesc(fn (array $e) => sprintf('%012d-%012d', $e['paid_at']->getTimestamp(), $e['payment']->id))
            ->values();
    }

    /**
     * Everything the "Payments & commission" page shows, for the affiliate panel
     * and the admin view of one affiliate.
     *
     * @return array<string, mixed>
     */
    public function pageData(User $affiliate, ?string $month = null, ?string $search = null): array
    {
        $month = is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : null;
        $search = trim((string) $search);

        $all = $this->entries($affiliate);
        $filtered = $this->filter($all, $month, $search);

        return [
            'summary' => $this->summary($all),
            'entries' => $this->paginate($filtered),
            'filteredTotal' => $this->formatTotals($this->totals($filtered)),
            'months' => $this->months($all),
            'month' => $month,
            'search' => $search,
            'commissions' => $this,
        ];
    }

    /** All-time commission for the affiliate, formatted ("LKR 5,250.00"). */
    public function totalFor(User $affiliate): string
    {
        return $this->formatTotals($this->totals($this->entries($affiliate)));
    }

    /**
     * Referred accounts that have made at least one plan payment.
     *
     * @return Collection<int, int>
     */
    public function payingUserIds(User $affiliate): Collection
    {
        $referredIds = AffiliateReferral::query()
            ->where('affiliate_user_id', $affiliate->id)
            ->pluck('referred_user_id');

        return $this->paymentsByReferredUser($referredIds)->keys()->map(fn ($id) => (int) $id)->values();
    }

    /**
     * Narrows entries to one month ("2026-11") and/or a name or email search.
     *
     * @param  Collection<int, array>  $entries
     * @return Collection<int, array>
     */
    public function filter(Collection $entries, ?string $month = null, ?string $search = null): Collection
    {
        $search = mb_strtolower(trim((string) $search));

        return $entries
            ->when($month, fn (Collection $c) => $c->filter(fn (array $e) => $e['paid_at']->format('Y-m') === $month))
            ->when($search !== '', fn (Collection $c) => $c->filter(fn (array $e) => str_contains(mb_strtolower((string) $e['referred']?->name), $search)
                || str_contains(mb_strtolower((string) $e['referred']?->email), $search)))
            ->values();
    }

    /**
     * Months that have at least one commission entry, newest first, for the month filter.
     *
     * @param  Collection<int, array>  $entries
     * @return array<string, string> "2026-11" => "November 2026"
     */
    public function months(Collection $entries): array
    {
        return $entries
            ->map(fn (array $e) => $e['paid_at']->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn (string $ym) => [$ym => Carbon::createFromFormat('Y-m-d', $ym.'-01')->format('F Y')])
            ->all();
    }

    /**
     * Headline numbers: commission all time and this month, commission payments
     * and paying accounts.
     *
     * @param  Collection<int, array>  $entries
     * @return array{total: array<string, float>, this_month: array<string, float>, payments: int, paying_accounts: int}
     */
    public function summary(Collection $entries): array
    {
        $thisMonth = now()->format('Y-m');

        return [
            'total' => $this->totals($entries),
            'this_month' => $this->totals($entries->filter(fn (array $e) => $e['paid_at']->format('Y-m') === $thisMonth)),
            'payments' => $entries->count(),
            'paying_accounts' => $entries->map(fn (array $e) => $e['referred']?->id)->filter()->unique()->count(),
        ];
    }

    /**
     * Commission added up per currency (almost always just LKR).
     *
     * @param  Collection<int, array>  $entries
     * @return array<string, float>
     */
    public function totals(Collection $entries): array
    {
        return $entries
            ->groupBy('commission_currency')
            ->map(fn (Collection $group) => round((float) $group->sum('commission'), 2))
            ->sortKeys()
            ->all();
    }

    /** "LKR 5,250.00", or "LKR 5,250.00 + USD 12.50"; "LKR 0.00" when empty. */
    public function formatTotals(array $totals): string
    {
        if ($totals === []) {
            return self::LATER_PAYMENT_CURRENCY.' 0.00';
        }

        return collect($totals)
            ->map(fn (float $sum, string $currency) => $currency.' '.number_format($sum, 2))
            ->implode(' + ');
    }

    /**
     * @param  Collection<int, array>  $entries
     */
    public function paginate(Collection $entries, int $perPage = 25): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $entries->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()],
        );
    }

    public function ruleLabel(string $rule): string
    {
        return $rule === self::RULE_FIRST
            ? (int) (self::FIRST_PAYMENTS_RATE * 100).'% of payment'
            : 'Monthly '.self::LATER_PAYMENT_CURRENCY.' '.number_format(self::LATER_PAYMENT_COMMISSION);
    }

    public function ordinal(int $number): string
    {
        $suffix = in_array($number % 100, [11, 12, 13], true)
            ? 'th'
            : (['th', 'st', 'nd', 'rd'][$number % 10] ?? 'th');

        return $number.$suffix;
    }

    /** When the payment went through; rows without paid_at fall back to when they were recorded. */
    private function paidAt(Payment $payment): Carbon
    {
        return Carbon::instance($payment->paid_at ?? $payment->created_at ?? Carbon::createFromTimestamp(0));
    }

    /**
     * Successful plan payments of the given referred accounts, grouped by the
     * referred account and sorted oldest first. A payment belongs to the
     * referred account that owns its business, or that made it.
     *
     * @param  Collection<int, int|string>  $referredIds
     * @return Collection<int, Collection<int, Payment>>
     */
    private function paymentsByReferredUser(Collection $referredIds): Collection
    {
        if ($referredIds->isEmpty()) {
            return collect();
        }

        $ownerByBusiness = Business::query()
            ->whereIn('user_id', $referredIds)
            ->pluck('user_id', 'id');

        $referredLookup = $referredIds->mapWithKeys(fn ($id) => [(int) $id => true]);

        return Payment::query()
            ->where('payment_status', Payment::STATUS_SUCCEEDED)
            ->where('payment_type', Payment::TYPE_SUBSCRIPTION)
            ->where('amount', '>', 0)
            ->where(fn ($q) => $q->whereIn('user_id', $referredIds)
                ->orWhereIn('business_id', $ownerByBusiness->keys()))
            ->with(['business:id,name', 'package:id,name'])
            ->get()
            ->groupBy(fn (Payment $p) => (int) ($ownerByBusiness[$p->business_id] ?? $p->user_id))
            ->filter(fn (Collection $payments, int $referredId) => isset($referredLookup[$referredId]))
            ->map(fn (Collection $payments) => $payments
                ->sortBy(fn (Payment $p) => sprintf('%012d-%012d', $this->paidAt($p)->getTimestamp(), $p->id))
                ->values());
    }
}
