<?php

namespace Modules\Payment\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessInvitation;
use Modules\Business\Models\BusinessMember;
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
     * Admin "User Seats & Extra Users" page: every business (limited or not) with
     * its seat usage, filtered and paginated.
     *
     * Filters: search (business / owner name / email), seats (limited|unlimited|full|near_full|extras),
     * package (id), sort (fullest|extras|users|name|newest).
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $rows = Business::query()
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->when(! empty($filters['package']), fn ($q) => $q->where('package_id', (int) $filters['package']))
            ->with(['package', 'user:id,name,email'])
            ->get()
            ->map(fn (Business $b) => $this->row($b));

        $rows = match ($filters['seats'] ?? '') {
            'limited' => $rows->where('limited', true),
            'unlimited' => $rows->where('limited', false),
            'full' => $rows->where('is_full', true),
            'near_full' => $rows->where('is_near_full', true),
            'extras' => $rows->where('extra_users', '>', 0),
            default => $rows,
        };

        $rows = match ($filters['sort'] ?? 'fullest') {
            'extras' => $rows->sortByDesc(fn ($r) => [$r['extra_users'], $r['users_used']]),
            'users' => $rows->sortByDesc('users_used'),
            'name' => $rows->sortBy(fn ($r) => mb_strtolower((string) $r['business'])),
            'newest' => $rows->sortByDesc('created_at'),
            default => $rows->sortByDesc(fn ($r) => [$r['limited'] ? 1 : 0, $r['usage_ratio'], $r['extra_users'], $r['users_used']]),
        };
        $rows = $rows->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()],
        );
    }

    /**
     * Everything about one business's seats: the numbers, who takes each seat
     * (owner, members, open invitations) and every extra-user purchase.
     *
     * @return array<string, mixed>
     */
    public function detail(Business $business): array
    {
        $business->loadMissing(['package', 'user']);
        $summary = $this->extraUsers->summary($business, null);

        $members = BusinessMember::query()
            ->where('business_id', $business->id)
            ->whereHas('user')
            ->with(['user', 'invitedBy:id,name,email'])
            ->orderBy('created_at')
            ->get();

        $invitations = BusinessInvitation::query()
            ->where('business_id', $business->id)
            ->with('invitedBy:id,name,email')
            ->latest()
            ->get();

        $purchases = Payment::query()
            ->where('business_id', $business->id)
            ->where('payment_type', Payment::TYPE_EXTRA_USERS)
            ->with('user:id,name,email')
            ->latest()
            ->get();

        $succeeded = $purchases->where('payment_status', Payment::STATUS_SUCCEEDED);

        return [
            'summary' => $summary,
            'row' => $this->row($business),
            'owner' => $business->user,
            'members' => $members,
            'active_members' => $members->where('status', 'active')->count(),
            'open_invitations' => $invitations->filter(fn (BusinessInvitation $i) => $i->isOpen())->values(),
            'past_invitations' => $invitations->reject(fn (BusinessInvitation $i) => $i->isOpen())->values(),
            'purchases' => $purchases,
            'seats_bought_total' => (int) $succeeded->sum(fn (Payment $p) => max(1, (int) ($p->metadata['quantity'] ?? 1))),
            'purchase_revenue' => (float) $succeeded->sum('amount'),
        ];
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
            ->map(fn (Business $business) => $this->row($business))
            ->sortByDesc(fn ($r) => [$r['usage_ratio'], $r['extra_users']])
            ->values();
    }

    /** @return array<string, mixed> */
    private function row(Business $business): array
    {
        $s = $this->extraUsers->summary($business, null);
        $ratio = $s['user_limit'] ? $s['users_used'] / $s['user_limit'] : 0;

        return [
            'business_id' => $business->id,
            'business' => $business->name,
            'owner_id' => $business->user?->id,
            'owner_name' => $business->user?->name,
            'owner_email' => $business->user?->email,
            'plan' => $s['plan'],
            'limited' => $s['limited'],
            'users_used' => $s['users_used'],
            'user_limit' => $s['user_limit'],
            'users_remaining' => $s['users_remaining'],
            'included_users' => $s['included_users'],
            'custom_limit' => $business->max_users_override !== null,
            'extra_users' => $s['extra_users'],
            'extra_user_price' => $s['extra_user_price'],
            'extra_monthly' => $s['extra_monthly'],
            'base_amount' => $s['base_amount'],
            'monthly_total' => $s['monthly_total'],
            'currency' => $s['currency'],
            'usage_ratio' => $ratio,
            'is_full' => $s['users_remaining'] === 0,
            'is_near_full' => $s['users_remaining'] !== null && $s['users_remaining'] !== 0 && $ratio >= self::NEAR_FULL_RATIO,
            'created_at' => $business->created_at,
        ];
    }
}
