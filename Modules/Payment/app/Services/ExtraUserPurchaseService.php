<?php

namespace Modules\Payment\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Business\Services\UserSeatService;
use Modules\Package\Models\Package;
use Modules\Payment\Exceptions\SubscriptionActionException;
use Modules\Payment\Models\Payment;
use Modules\Pos\Services\PosNotificationService;

/**
 * Buying user seats beyond the package's `max_users`.
 *
 * Flow: the owner pays the first month for N extra users through a one-off
 * Stripe Checkout; once Stripe confirms it, the seats are added to the
 * business and an "Extra users" item (seats × price per extra user) is put on
 * the Stripe subscription — so every renewal from then on is
 * plan price + extra users (e.g. 3,500 + 1 × 500 = 4,000 a month).
 */
class ExtraUserPurchaseService
{
    public const MAX_QUANTITY = 50;

    public function __construct(
        private readonly StripeSubscriptionService $stripe,
        private readonly UserSeatService $seats,
        private readonly PosNotificationService $notifications,
    ) {}

    /**
     * Seat usage and monthly pricing for the billing screen and the desktop
     * "user limit reached" dialog.
     *
     * @return array<string, mixed>
     */
    public function summary(Business $business, ?User $requester): array
    {
        $package = $business->package;
        $active = $this->activeSubscription($business);

        $limit = $this->seats->limit($business);
        $used = $this->seats->used($business);
        $extra = $this->seats->extraSeats($business);
        $unit = $this->unitPrice($package);
        $billedUnit = $this->seats->extraUnitPrice($business) ?? $unit ?? 0.0;
        $base = $this->baseAmount($active, $package);
        $extraMonthly = round($extra * $billedUnit, 2);
        $blocked = $this->purchaseBlockedReason($business, $requester, $active, $package, $limit);

        return [
            'plan' => $package?->name,
            'limited' => $limit !== null,
            'included_users' => $this->seats->includedSeats($business),
            // Admin set a user limit for this business instead of the package's Max Users.
            'custom_limit' => $business->max_users_override !== null,
            'extra_users' => $extra,
            'user_limit' => $limit,
            'users_used' => $used,
            'users_remaining' => $limit === null ? null : max(0, $limit - $used),
            'currency' => strtoupper((string) ($active?->currency ?? $package?->currency ?? 'USD')),
            'base_amount' => $base,
            'extra_user_price' => $unit,
            'extra_monthly' => $extraMonthly,
            'monthly_total' => round($base + $extraMonthly, 2),
            'next_renewal_at' => $active?->current_period_end?->toIso8601String(),
            'can_purchase' => $blocked === null,
            'purchase_blocked_reason' => $blocked,
            'max_quantity' => self::MAX_QUANTITY,
        ];
    }

    /**
     * Creates the pending extra-users Payment and its Stripe Checkout session.
     *
     * @return array{payment: Payment, checkout_url: string}
     *
     * @throws SubscriptionActionException
     */
    public function startCheckout(Business $business, User $requester, int $quantity, string $successUrl, string $cancelUrlBase): array
    {
        $package = $business->package;
        $active = $this->activeSubscription($business);
        $limit = $this->seats->limit($business);

        $blocked = $this->purchaseBlockedReason($business, $requester, $active, $package, $limit);
        if ($blocked !== null) {
            throw new SubscriptionActionException($blocked);
        }

        $quantity = max(1, min(self::MAX_QUANTITY, $quantity));
        $unit = (float) $this->unitPrice($package);
        $extraBefore = $this->seats->extraSeats($business);
        $base = $this->baseAmount($active, $package);

        $payment = Payment::create([
            'business_id' => $business->id,
            'user_id' => $requester->id,
            'package_id' => $package->id,
            'payment_type' => Payment::TYPE_EXTRA_USERS,
            'payment_status' => Payment::STATUS_PENDING,
            'billing_cycle' => 'monthly',
            'platform' => 'desktop',
            'gateway' => 'stripe',
            'amount' => round($quantity * $unit, 2),
            'currency' => strtolower((string) $active->currency),
            'metadata' => [
                'quantity' => $quantity,
                'unit_price' => $unit,
                'base_amount' => $base,
                'extra_users_before' => $extraBefore,
                // Every seat is billed at the current price from the next renewal on.
                'new_monthly_total' => round($base + ($extraBefore + $quantity) * $unit, 2),
            ],
        ]);

        try {
            $session = $this->stripe->createExtraUsersCheckoutSession(
                $payment,
                $business,
                $package,
                $quantity,
                $unit,
                $active->stripe_customer_id,
                $successUrl,
                $cancelUrlBase.(str_contains($cancelUrlBase, '?') ? '&' : '?').'payment='.$payment->id,
            );
        } catch (\Throwable $e) {
            report($e);
            $payment->update([
                'payment_status' => Payment::STATUS_FAILED,
                'failure_reason' => 'Could not start Stripe checkout.',
            ]);

            throw new SubscriptionActionException('Could not start Stripe checkout. Please try again later.', 502);
        }

        $payment->update(['stripe_checkout_session_id' => $session->id]);

        return ['payment' => $payment, 'checkout_url' => (string) $session->url];
    }

    /**
     * Stripe confirmed the extra-users charge: mark it paid, add the seats and
     * raise the subscription's monthly amount. Idempotent — the webhook and the
     * desktop return page may both arrive for the same session.
     */
    public function settle(Payment $payment, ?string $sessionId, ?string $customerId, ?string $paymentIntentId): bool
    {
        $settled = DB::transaction(function () use ($payment, $sessionId, $customerId, $paymentIntentId): ?Payment {
            $locked = Payment::query()->lockForUpdate()->find($payment->id);
            if (! $locked || $locked->isSucceeded() || ! $locked->business) {
                return null;
            }

            $meta = $locked->metadata ?? [];
            $qty = max(1, (int) ($meta['quantity'] ?? 1));
            $unit = (float) ($meta['unit_price'] ?? ((float) $locked->amount / $qty));
            $total = $this->seats->addExtraSeats($locked->business, $qty, $unit);

            $locked->update([
                'payment_status' => Payment::STATUS_SUCCEEDED,
                'stripe_checkout_session_id' => $sessionId ?? $locked->stripe_checkout_session_id,
                'stripe_customer_id' => $customerId ?? $locked->stripe_customer_id,
                'stripe_payment_intent_id' => $paymentIntentId ?? $locked->stripe_payment_intent_id,
                'paid_at' => now(),
                'failure_reason' => null,
                'due_at' => null,
                'metadata' => $meta + ['extra_users_after' => $total],
            ]);

            return $locked;
        });

        if (! $settled) {
            return false;
        }

        $this->syncSubscriptionItem($settled->business, $settled);
        $this->notifications->notifyPaymentSucceeded($settled->business, $settled);

        return true;
    }

    /**
     * Puts the business's current extra seats on its Stripe subscription. A
     * failure is reported and flagged on the payment (`stripe_synced: false`)
     * rather than undoing the paid seats.
     */
    public function syncSubscriptionItem(Business $business, ?Payment $record = null): bool
    {
        $active = $this->activeSubscription($business);
        $ok = false;

        if ($active) {
            try {
                $this->stripe->upsertExtraUsersItem(
                    (string) $active->stripe_subscription_id,
                    $this->seats->extraSeats($business),
                    (float) ($this->seats->extraUnitPrice($business) ?? 0),
                    (string) $active->currency,
                    'Extra users — '.($business->package?->name ?? 'subscription').' plan',
                );
                $ok = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($record) {
            $record->update(['metadata' => ($record->metadata ?? []) + ['stripe_synced' => $ok]]);
        }

        return $ok;
    }

    /**
     * Breakdown stored on renewal rows so the billing screen and receipt can
     * show "plan + extra users" instead of a single lump sum.
     *
     * @return array<string, mixed>|null
     */
    public function renewalMetadata(?Business $business): ?array
    {
        if (! $business) {
            return null;
        }

        $seats = $this->seats->extraSeats($business);
        $unit = $this->seats->extraUnitPrice($business);

        return $seats > 0 && $unit !== null && $unit > 0
            ? ['extra_users' => $seats, 'extra_user_price' => $unit]
            : null;
    }

    /** The business's live paid Stripe subscription row, newest first. */
    public function activeSubscription(Business $business): ?Payment
    {
        return $business->payments()
            ->where('payment_type', Payment::TYPE_SUBSCRIPTION)
            ->where('payment_status', Payment::STATUS_SUCCEEDED)
            ->whereIn('stripe_subscription_status', ['active', 'trialing'])
            ->whereNotNull('stripe_subscription_id')
            ->first();
    }

    /** Monthly plan price without extra users — what the subscription was signed up at. */
    private function baseAmount(?Payment $active, ?Package $package): float
    {
        if ($active) {
            $anchor = Payment::query()
                ->where('stripe_subscription_id', $active->stripe_subscription_id)
                ->where('payment_type', Payment::TYPE_SUBSCRIPTION)
                ->oldest('id')
                ->first();

            return (float) ($anchor ?? $active)->amount;
        }

        if (! $package || $package->is_free) {
            return 0.0;
        }

        return ($package->discounted_price !== null && (float) $package->discounted_price < (float) $package->price)
            ? (float) $package->discounted_price
            : (float) $package->price;
    }

    private function unitPrice(?Package $package): ?float
    {
        if (! $package || $package->max_users === null || $package->extra_user_price === null) {
            return null;
        }

        return (float) $package->extra_user_price > 0 ? (float) $package->extra_user_price : null;
    }

    private function purchaseBlockedReason(Business $business, ?User $requester, ?Payment $active, ?Package $package, ?int $limit): ?string
    {
        if ($limit === null) {
            return 'Your plan has no user limit, so there is nothing to buy.';
        }

        $planName = $package?->name ?? 'current';

        if ($this->unitPrice($package) === null) {
            return "Your {$planName} plan doesn't offer extra users. Upgrade to a bigger package or contact Zeebroo to add more users.";
        }

        if (! $requester instanceof User || (int) $business->user_id !== (int) $requester->id) {
            return 'Only the business owner can buy extra users.';
        }

        if (! $active) {
            return $package?->is_free
                ? 'Extra users are available on paid monthly plans. Upgrade your package to add more users.'
                : 'Extra users can be bought once your monthly subscription is active.';
        }

        if ($active->cancel_at_period_end) {
            return 'Your subscription is set to cancel. Resume it from Billing & Payments before buying extra users.';
        }

        return null;
    }
}
