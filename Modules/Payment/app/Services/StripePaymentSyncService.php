<?php

namespace Modules\Payment\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\Payment\Models\Payment;
use Modules\Pos\Services\PosNotificationService;

/**
 * Applies Stripe state (checkout sessions, invoices, subscriptions) to local
 * Payment rows. Shared by the Stripe webhook and the scheduled/admin payment
 * check (PaymentCheckService) so both paths settle a payment identically and
 * idempotently — whichever sees an event first records it, the other no-ops.
 *
 * Every mutating method returns true only when it actually changed something,
 * which the payment check uses to report "fixed" vs "already in sync".
 */
class StripePaymentSyncService
{
    /**
     * Every Stripe event PaymentController::webhook() handles — shown in the
     * admin setup guide (Admin › Payment Checks) as the events to subscribe
     * the Stripe endpoint to. Keep in sync with the webhook's switch.
     */
    public const WEBHOOK_EVENTS = [
        'checkout.session.completed' => 'First payment done — payment marked paid',
        'checkout.session.async_payment_succeeded' => 'Delayed payment succeeded — marked paid',
        'checkout.session.async_payment_failed' => 'Delayed payment declined — marked canceled',
        'checkout.session.expired' => 'Customer left checkout — marked canceled, due date starts',
        'invoice.paid' => 'Monthly renewal paid — new payment row added',
        'invoice.payment_succeeded' => 'Same as invoice.paid (older event name)',
        'invoice_payment.paid' => 'Same as invoice.paid (newer event name)',
        'invoice.payment_failed' => 'Renewal card failed — failed row added, grace period starts',
        'customer.subscription.updated' => 'Status, renewal date or cancel flag changed',
        'customer.subscription.deleted' => 'Subscription ended',
    ];

    /** Stripe invoice statuses that mean the charge was attempted and didn't go through. */
    private const UNPAID_INVOICE_STATUSES = ['open', 'uncollectible'];

    public function __construct(
        private readonly PosNotificationService $notifications,
    ) {}

    public function paymentForSession(object $session): ?Payment
    {
        $paymentId = $session->metadata['payment_id'] ?? $session->client_reference_id ?? null;

        return $paymentId ? Payment::find($paymentId) : null;
    }

    /**
     * Marks the session's Payment succeeded when Stripe says it was paid.
     * `status === complete` alone is not enough: with delayed payment methods
     * a session completes while the money is still `unpaid`.
     */
    public function applyCheckoutSession(object $session): bool
    {
        $payment = $this->paymentForSession($session);
        if (! $payment || $payment->isSucceeded()) {
            return false;
        }

        if (! in_array($session->payment_status ?? null, ['paid', 'no_payment_required'], true)) {
            return false;
        }

        // retrieveSession() expands subscription/payment_intent into full Stripe
        // objects — store only their IDs, never the object itself.
        $this->markSucceeded(
            $payment,
            $session->id,
            $this->idOf($session->customer ?? null),
            $this->idOf($session->subscription ?? null),
            $this->idOf($session->payment_intent ?? null),
        );

        return true;
    }

    /**
     * A Checkout Session that expired or was abandoned — the pending Payment
     * becomes canceled but keeps (or gains) its grace-period deadline so the
     * business is still asked to pay.
     */
    public function markCanceledIfPending(?Payment $payment, string $reason): bool
    {
        if (! $payment || $payment->payment_status !== Payment::STATUS_PENDING) {
            return false;
        }

        $payment->update([
            'payment_status' => Payment::STATUS_CANCELED,
            'failure_reason' => $reason,
            'due_at' => $payment->due_at ?? Payment::dueAtFor($payment->business),
        ]);

        return true;
    }

    public function markSucceeded(Payment $payment, ?string $sessionId, ?string $customerId, ?string $subscriptionId, ?string $paymentIntentId): void
    {
        $payment->update([
            'payment_status' => Payment::STATUS_SUCCEEDED,
            'stripe_checkout_session_id' => $sessionId ?? $payment->stripe_checkout_session_id,
            'stripe_customer_id' => $customerId ?? $payment->stripe_customer_id,
            'stripe_subscription_id' => $subscriptionId ?? $payment->stripe_subscription_id,
            'stripe_payment_intent_id' => $paymentIntentId ?? $payment->stripe_payment_intent_id,
            'stripe_subscription_status' => 'active',
            'paid_at' => $payment->paid_at ?? now(),
            // A resumed checkout (canceled/failed first) must not keep its stale failure state.
            'failure_reason' => null,
            'due_at' => null,
        ]);

        $payment->business?->setSetting('business.subscription_status', 'active');

        if ($payment->business) {
            $this->notifications->notifyPaymentSucceeded($payment->business, $payment->refresh());
        }
    }

    /**
     * Records a paid Stripe invoice. The first invoice (`subscription_create`)
     * belongs to the signup row; every later renewal (`subscription_cycle`)
     * becomes its own succeeded Payment row so history accumulates monthly.
     *
     * If a row for this invoice already exists but is still failed (the
     * renewal charge failed, then Stripe's retry or the customer succeeded),
     * that row is settled — otherwise the business would stay locked even
     * though it paid.
     */
    public function recordInvoicePaid(object $invoice): bool
    {
        return $this->withInvoiceLock($invoice->id, function () use ($invoice): bool {
            $existing = Payment::query()->where('stripe_invoice_id', $invoice->id)->first();
            if ($existing) {
                if ($existing->isSucceeded()) {
                    return false;
                }

                $this->markSucceeded($existing, null, $this->idOf($invoice->customer ?? null), $this->invoiceSubscriptionId($invoice), null);

                return true;
            }

            $anchor = $this->resolveAnchorPayment($invoice);
            if (! $anchor) {
                return false;
            }

            if ($anchor->stripe_invoice_id === null && ($invoice->billing_reason ?? null) === 'subscription_create') {
                $anchor->update(['stripe_invoice_id' => $invoice->id]);

                // The checkout webhook/redirect never landed (browser closed,
                // webhook missed) — the paid first invoice settles the signup row.
                if (! $anchor->isSucceeded()) {
                    $this->markSucceeded($anchor, null, $this->idOf($invoice->customer ?? null), $this->invoiceSubscriptionId($invoice), null);
                }

                return true;
            }

            $renewal = Payment::create([
                'business_id' => $anchor->business_id,
                'user_id' => $anchor->user_id,
                'package_id' => $anchor->package_id,
                'payment_type' => Payment::TYPE_SUBSCRIPTION,
                'payment_status' => Payment::STATUS_SUCCEEDED,
                'billing_cycle' => $anchor->billing_cycle,
                'platform' => $anchor->platform ?? 'desktop',
                'gateway' => 'stripe',
                'amount' => ($invoice->amount_paid ?? 0) / 100,
                'currency' => strtolower($invoice->currency ?? $anchor->currency),
                'stripe_customer_id' => $this->idOf($invoice->customer ?? null) ?? $anchor->stripe_customer_id,
                'stripe_subscription_id' => $this->invoiceSubscriptionId($invoice) ?? $anchor->stripe_subscription_id,
                'stripe_invoice_id' => $invoice->id,
                'stripe_payment_intent_id' => $this->idOf($invoice->payment_intent ?? null),
                'stripe_subscription_status' => 'active',
                'paid_at' => $this->invoicePaidAt($invoice) ?? now(),
            ]);

            if ($renewal->business) {
                $this->notifications->notifyPaymentSucceeded($renewal->business, $renewal);
            }

            return true;
        });
    }

    /**
     * Records a failed Stripe invoice. The first invoice failing flips the
     * still-pending anchor row; a renewal failing becomes its own failed row
     * so a bad month never overwrites a previously succeeded one.
     */
    public function recordInvoiceFailed(object $invoice): bool
    {
        return $this->withInvoiceLock($invoice->id, function () use ($invoice): bool {
            if (Payment::query()->where('stripe_invoice_id', $invoice->id)->exists()) {
                return false;
            }

            $anchor = $this->resolveAnchorPayment($invoice);
            if (! $anchor) {
                return false;
            }

            if ($anchor->payment_status === Payment::STATUS_PENDING && ($invoice->billing_reason ?? null) === 'subscription_create') {
                $target = $anchor;
                $target->update([
                    'payment_status' => Payment::STATUS_FAILED,
                    'failure_reason' => 'Stripe invoice payment failed for the first subscription charge.',
                    'due_at' => $anchor->due_at ?? Payment::dueAtFor($anchor->business),
                    'stripe_invoice_id' => $invoice->id,
                ]);
            } else {
                $target = Payment::create([
                    'business_id' => $anchor->business_id,
                    'user_id' => $anchor->user_id,
                    'package_id' => $anchor->package_id,
                    'payment_type' => Payment::TYPE_SUBSCRIPTION,
                    'payment_status' => Payment::STATUS_FAILED,
                    'billing_cycle' => $anchor->billing_cycle,
                    'platform' => $anchor->platform ?? 'desktop',
                    'gateway' => 'stripe',
                    'amount' => ($invoice->amount_due ?? (float) $anchor->amount * 100) / 100,
                    'currency' => strtolower($invoice->currency ?? $anchor->currency),
                    'stripe_customer_id' => $this->idOf($invoice->customer ?? null) ?? $anchor->stripe_customer_id,
                    'stripe_subscription_id' => $this->invoiceSubscriptionId($invoice) ?? $anchor->stripe_subscription_id,
                    'stripe_invoice_id' => $invoice->id,
                    'failure_reason' => 'Stripe invoice payment failed for subscription renewal.',
                    'due_at' => Payment::dueAtFor($anchor->business),
                ]);
            }

            if ($target->business) {
                $this->notifications->notifyPaymentFailed($target->business, $target);
            }

            return true;
        });
    }

    /**
     * Copies a Stripe Subscription's status / period end / cancel flag onto
     * every local row of that subscription.
     */
    public function syncSubscription(object $subscription): bool
    {
        $rawPeriodEnd = $subscription->current_period_end
            ?? ($subscription->items->data[0]->current_period_end ?? null);
        $periodEnd = $rawPeriodEnd !== null ? Carbon::createFromTimestamp($rawPeriodEnd) : null;
        $status = $subscription->status ?? 'canceled';
        $cancelAtPeriodEnd = (bool) ($subscription->cancel_at_period_end ?? false) || ($subscription->cancel_at ?? null) !== null;

        $rows = Payment::query()->where('stripe_subscription_id', $subscription->id)->get();

        $changed = $rows->contains(fn (Payment $p) => $p->stripe_subscription_status !== $status
            || (bool) $p->cancel_at_period_end !== $cancelAtPeriodEnd
            || $p->current_period_end?->timestamp !== $periodEnd?->timestamp);

        if ($changed) {
            Payment::query()->where('stripe_subscription_id', $subscription->id)->update([
                'stripe_subscription_status' => $status,
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => $cancelAtPeriodEnd,
            ]);
        }

        return $changed;
    }

    public function isUnpaidInvoice(object $invoice): bool
    {
        return in_array($invoice->status ?? null, self::UNPAID_INVOICE_STATUSES, true)
            && (int) ($invoice->attempt_count ?? 0) > 0;
    }

    public function invoicePaidAt(object $invoice): ?Carbon
    {
        $ts = $invoice->status_transitions->paid_at ?? null;

        return $ts ? Carbon::createFromTimestamp($ts) : null;
    }

    /**
     * Finds the original signup Payment row for an invoice's subscription —
     * prefers the payment_id stashed in the subscription metadata at checkout
     * (exact), falling back to the oldest row tagged with the subscription ID.
     */
    public function resolveAnchorPayment(object $invoice): ?Payment
    {
        $metaPaymentId = $this->invoiceSubscriptionMetadata($invoice)['payment_id'] ?? null;
        if ($metaPaymentId && ($byMeta = Payment::find($metaPaymentId))) {
            return $byMeta;
        }

        $subscriptionId = $this->invoiceSubscriptionId($invoice);

        return $subscriptionId
            ? Payment::query()->where('stripe_subscription_id', $subscriptionId)->oldest()->first()
            : null;
    }

    /**
     * The Invoice's subscription reference moved from top-level `subscription`
     * to `parent.subscription_details.subscription` in newer API versions.
     */
    public function invoiceSubscriptionId(object $invoice): ?string
    {
        return $this->idOf($invoice->subscription ?? null)
            ?? ($invoice->parent?->subscription_details?->subscription ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceSubscriptionMetadata(object $invoice): array
    {
        $metadata = $invoice->parent?->subscription_details?->metadata ?? null;
        if ($metadata === null) {
            return [];
        }

        return method_exists($metadata, 'toArray') ? $metadata->toArray() : (array) $metadata;
    }

    /** Stripe fields may be an ID string or an expanded object. */
    private function idOf(mixed $value): ?string
    {
        if (is_object($value)) {
            return $value->id ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The webhook and the scheduled check can process the same invoice at the
     * same moment — serialize per invoice so the "exists?" check and the
     * insert can't interleave into a duplicate row.
     */
    private function withInvoiceLock(string $invoiceId, callable $callback): bool
    {
        return (bool) Cache::lock('stripe-invoice:'.$invoiceId, 30)->block(15, $callback);
    }
}
