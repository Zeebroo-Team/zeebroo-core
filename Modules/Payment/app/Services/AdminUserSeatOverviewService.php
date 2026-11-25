<?php

namespace Modules\Payment\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Package\Models\Package;
use Modules\Payment\Models\Payment;

/**
 * Admin view of package user limits across the platform: which businesses are
 * at / near their cap, how many extra users were sold, and what they add to
 * monthly revenue. Per-business numbers come from ExtraUserPurchaseService so
 * the admin sees exactly what the customer's Billing screen shows.
 */
class AdminUserSeatOverviewService
{
    /** Usage at or above this share of the limit counts as "near the limit". */
    private const NEAR_FULL_RATIO = 0.8;

    public function __construct(private readonly ExtraUserPurchaseService $extraUsers) {}

    /**
     * @return array{
     *   limited_businesses: int, full_businesses: int, near_full_businesses: int,
     *   extra_seats_total: int, businesses_with_extras: int,
     *   extra_mrr: Collection<string, float>, extra_revenue: Collection<string, float>,
     *   extra_purchases_this_month: int, rows: Collection<int, array<string, mixed>>
     * }
     */
    public function overview(int $rowLimit = 10): array
    {
        $rows = $this->rows();

        $extraRevenue = Payment::query()
            ->where('payment_type', Payment::TYPE_EXTRA_USERS)
            ->where('payment_status', Payment::STATUS_SUCCEEDED)
            ->selectRaw('UPPER(currency) as cur, SUM(amount) as total')
            ->groupBy('cur')
            ->pluck('total', 'cur')
            ->map(fn ($t) => (float) $t);

        return [
            'limited_businesses' => $rows->count(),
            'full_businesses' => $rows->where('is_full', true)->count(),
            'near_full_businesses' => $rows->where('is_near_full', true)->count(),
            'extra_seats_total' => (int) $rows->sum('extra_users'),
            'businesses_with_extras' => $rows->where('extra_users', '>', 0)->count(),
            'extra_mrr' => $rows->where('extra_monthly', '>', 0)
                ->groupBy('currency')
                ->map(fn ($g) => (float) $g->sum('extra_monthly')),
            'extra_revenue' => $extraRevenue,
            'extra_purchases_this_month' => Payment::query()
                ->where('payment_type', Payment::TYPE_EXTRA_USERS)
                ->where('payment_status', Payment::STATUS_SUCCEEDED)
                ->where('paid_at', '>=', now()->startOfMonth())
                ->count(),
            'rows' => $rows->take($rowLimit)->values(),
        ];
    }

    /** Seat summary for one business, e.g. on the admin user-detail page. */
    public function forBusiness(Business $business): array
    {
        return $this->extraUsers->summary($business, null);
    }

    /**
     * Every business with a user cap (from its package or an admin override), fullest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(): Collection
    {
        $limitedPackageIds = Package::query()->whereNotNull('max_users')->pluck('id');

        return Business::query()
            ->where(fn ($q) => $q->whereIn('package_id', $limitedPackageIds)->orWhereNotNull('max_users_override'))
            ->with(['package', 'user:id,name,email'])
            ->get()
            ->map(function (Business $business): array {
                $s = $this->extraUsers->summary($business, null);
                $ratio = $s['user_limit'] ? $s['users_used'] / $s['user_limit'] : 0;

                return [
                    'business_id' => $business->id,
                    'business' => $business->name,
                    'owner_id' => $business->user?->id,
                    'owner_email' => $business->user?->email,
                    'plan' => $s['plan'],
                    'users_used' => $s['users_used'],
                    'user_limit' => $s['user_limit'],
                    'included_users' => $s['included_users'],
                    'custom_limit' => $business->max_users_override !== null,
                    'extra_users' => $s['extra_users'],
                    'extra_monthly' => $s['extra_monthly'],
                    'base_amount' => $s['base_amount'],
                    'monthly_total' => $s['monthly_total'],
                    'currency' => $s['currency'],
                    'usage_ratio' => $ratio,
                    'is_full' => $s['users_remaining'] === 0,
                    'is_near_full' => $s['users_remaining'] !== 0 && $ratio >= self::NEAR_FULL_RATIO,
                ];
            })
            ->sortByDesc(fn ($r) => [$r['usage_ratio'], $r['extra_users']])
            ->values();
    }
}
