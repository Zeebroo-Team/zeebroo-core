<?php

namespace Modules\Pos\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Business\Models\Business;
use Modules\Mail\Services\BusinessMailerService;
use Modules\Pos\Mail\SubscriptionRenewalReminderMail;
use Modules\Pos\Models\CustomerSubscription;
use Modules\Product\Models\Product;

class CustomerSubscriptionService
{
    public const PERIODS = ['weekly', 'monthly', 'quarterly', 'yearly'];

    /** Subscriptions with a next billing date within this many days are "due soon". */
    public const DUE_SOON_DAYS = 7;

    public function __construct(
        private readonly BusinessMailerService $mailer,
    ) {
    }

    public function createForSaleLine(
        Business $business,
        Product $product,
        int $saleId,
        ?int $saleItemId,
        ?int $customerId,
        float $price,
        float $quantity,
    ): CustomerSubscription {
        $period = in_array($product->subscription_recurring_period, self::PERIODS, true)
            ? $product->subscription_recurring_period
            : 'monthly';
        $startedAt     = now()->startOfDay();
        $nextBillingAt = $this->addPeriod($startedAt->copy(), $period);

        return CustomerSubscription::query()->create([
            'business_id'      => $business->id,
            'pos_customer_id'  => $customerId,
            'product_id'       => $product->id,
            'pos_sale_id'      => $saleId,
            'pos_sale_item_id' => $saleItemId,
            'recurring_period' => $period,
            'free_trial'       => (bool) $product->subscription_free_trial,
            'price'            => round($price, 2),
            'quantity'         => round($quantity, 3),
            'status'           => $product->subscription_free_trial ? CustomerSubscription::STATUS_TRIAL : CustomerSubscription::STATUS_ACTIVE,
            'started_at'       => $startedAt,
            'next_billing_at'  => $nextBillingAt,
        ]);
    }

    public function addPeriod(Carbon $date, string $period): Carbon
    {
        return match ($period) {
            'weekly'    => $date->addWeek(),
            'quarterly' => $date->addMonths(3),
            'yearly'    => $date->addYear(),
            default     => $date->addMonth(),
        };
    }

    /**
     * @return LengthAwarePaginator<int, CustomerSubscription>
     */
    public function list(Business $business, ?string $status, ?int $customerId, ?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return CustomerSubscription::query()
            ->where('business_id', $business->id)
            ->with(['customer:id,name,phone,email', 'product:id,name,sku'])
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($customerId, fn ($q) => $q->where('pos_customer_id', $customerId))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function cancel(CustomerSubscription $subscription): CustomerSubscription
    {
        $subscription->update([
            'status'       => CustomerSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $subscription;
    }

    public function pause(CustomerSubscription $subscription): CustomerSubscription
    {
        $subscription->update(['status' => CustomerSubscription::STATUS_PAUSED]);

        return $subscription;
    }

    public function resume(CustomerSubscription $subscription): CustomerSubscription
    {
        $subscription->update(['status' => CustomerSubscription::STATUS_ACTIVE]);

        return $subscription;
    }

    public function renew(CustomerSubscription $subscription): CustomerSubscription
    {
        $from = $subscription->next_billing_at ? Carbon::parse($subscription->next_billing_at) : now();
        $subscription->update([
            'status'          => CustomerSubscription::STATUS_ACTIVE,
            'last_renewed_at' => now()->toDateString(),
            'next_billing_at' => $this->addPeriod($from, $subscription->recurring_period),
        ]);

        return $subscription;
    }

    /**
     * @return array{success: bool, error: ?string}
     */
    public function notify(Business $business, CustomerSubscription $subscription): array
    {
        $subscription->loadMissing(['customer', 'product']);
        $email = $subscription->customer?->email;

        if (!filled($email)) {
            return ['success' => false, 'error' => 'This customer has no email address on file.'];
        }

        $periodLabels = [
            'weekly'    => 'weekly',
            'monthly'   => 'monthly',
            'quarterly' => 'quarterly',
            'yearly'    => 'yearly',
        ];

        $mailable = new SubscriptionRenewalReminderMail(
            businessName:     $business->name,
            customerName:     $subscription->customer->name ?? 'there',
            productName:      $subscription->product?->name ?? 'your subscription',
            priceLabel:       trim(number_format((float) $subscription->price, 2) . ' ' . (string) $business->getSetting('business.currency', '')),
            periodLabel:      $periodLabels[$subscription->recurring_period] ?? $subscription->recurring_period,
            nextBillingLabel: $subscription->next_billing_at?->format('M j, Y') ?? '—',
        );

        $result = $this->mailer->send($business, $mailable, $email);

        if ($result['success']) {
            $subscription->update(['last_notified_at' => now()]);
        }

        return $result;
    }
}
