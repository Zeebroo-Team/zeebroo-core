<?php

namespace Modules\Payment\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Payment\Models\Payment;

/**
 * Platform-wide payment history for the admin panel — every subscription /
 * free-plan Payment row across all businesses, with filtering and totals.
 */
class AdminPaymentHistoryService
{
    public const STATUSES = [
        Payment::STATUS_SUCCEEDED,
        Payment::STATUS_PENDING,
        Payment::STATUS_PROCESSING,
        Payment::STATUS_FAILED,
        Payment::STATUS_CANCELED,
        Payment::STATUS_REFUNDED,
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'newest';

        return $this->filteredQuery($filters)
            ->with(['business:id,name', 'user:id,name,email', 'package:id,name'])
            ->when($sort === 'oldest', fn ($q) => $q->oldest('created_at'))
            ->when($sort === 'amount_desc', fn ($q) => $q->orderByDesc('amount'))
            ->when($sort === 'amount_asc', fn ($q) => $q->orderBy('amount'))
            ->when(! in_array($sort, ['oldest', 'amount_desc', 'amount_asc'], true), fn ($q) => $q->latest('created_at'))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Totals for the currently filtered set, so the summary cards always
     * describe exactly what the table below them is showing.
     *
     * @param  array<string, mixed>  $filters
     * @return array{count: int, succeeded_count: int, failed_count: int, pending_count: int, revenue: Collection<string, float>, revenue_this_month: Collection<string, float>}
     */
    public function summary(array $filters = []): array
    {
        $byStatus = $this->filteredQuery($filters)
            ->selectRaw('payment_status, count(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');

        return [
            'count' => (int) $byStatus->sum(),
            'succeeded_count' => (int) ($byStatus[Payment::STATUS_SUCCEEDED] ?? 0),
            'failed_count' => (int) ($byStatus[Payment::STATUS_FAILED] ?? 0),
            'pending_count' => (int) (($byStatus[Payment::STATUS_PENDING] ?? 0) + ($byStatus[Payment::STATUS_PROCESSING] ?? 0)),
            'revenue' => $this->revenueByCurrency($this->filteredQuery($filters)),
            'revenue_this_month' => $this->revenueByCurrency(
                $this->filteredQuery($filters)->where('paid_at', '>=', now()->startOfMonth())
            ),
        ];
    }

    /**
     * Latest payments for the admin dashboard widget.
     *
     * @return Collection<int, Payment>
     */
    public function recent(int $limit = 5): Collection
    {
        return $this->filteredQuery()
            ->with(['business:id,name', 'user:id,name,email', 'package:id,name'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function find(int $id): Payment
    {
        return $this->filteredQuery()
            ->with(['business', 'user', 'package'])
            ->findOrFail($id);
    }

    /**
     * Other payments made by the same business — shown on the detail page.
     *
     * @return Collection<int, Payment>
     */
    public function relatedForBusiness(Payment $payment, int $limit = 10): Collection
    {
        if (! $payment->business_id) {
            return collect();
        }

        return Payment::query()
            ->with('package:id,name')
            ->where('business_id', $payment->business_id)
            ->whereKeyNot($payment->getKey())
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, string>
     */
    public function gateways(): Collection
    {
        return Payment::query()->whereNotNull('gateway')->distinct()->orderBy('gateway')->pluck('gateway');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $type = (string) ($filters['type'] ?? '');
        $gateway = (string) ($filters['gateway'] ?? '');
        $platform = (string) ($filters['platform'] ?? '');
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $hiddenDomains = config('app.hidden_user_email_domains', []);

        return Payment::query()
            // Keep throwaway/test accounts out of admin views, same as User Management.
            ->when($hiddenDomains, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('user_id')
                ->orWhereHas('user', function ($u) use ($hiddenDomains) {
                    foreach ($hiddenDomains as $domain) {
                        $u->whereRaw('LOWER(email) NOT LIKE ?', ['%@'.$domain]);
                    }
                })))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('id', ctype_digit($search) ? (int) $search : 0)
                ->orWhere('stripe_customer_id', 'like', "%{$search}%")
                ->orWhere('stripe_subscription_id', 'like', "%{$search}%")
                ->orWhere('stripe_payment_intent_id', 'like', "%{$search}%")
                ->orWhere('stripe_invoice_id', 'like', "%{$search}%")
                ->orWhere('stripe_checkout_session_id', 'like', "%{$search}%")
                ->orWhereHas('business', fn ($b) => $b->where('name', 'like', "%{$search}%"))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('payment_status', $status))
            ->when(in_array($type, [Payment::TYPE_SUBSCRIPTION, Payment::TYPE_FREE], true), fn ($q) => $q->where('payment_type', $type))
            ->when($gateway !== '', fn ($q) => $q->where('gateway', $gateway))
            ->when($platform !== '', fn ($q) => $q->where('platform', $platform))
            ->when($from, fn ($q) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()));
    }

    /**
     * @return Collection<string, float>
     */
    private function revenueByCurrency(Builder $query): Collection
    {
        return $query
            ->where('payment_status', Payment::STATUS_SUCCEEDED)
            ->selectRaw('UPPER(currency) as cur, SUM(amount) as total')
            ->groupBy('cur')
            ->pluck('total', 'cur')
            ->map(fn ($total) => (float) $total);
    }
}
