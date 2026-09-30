<?php

namespace Modules\Payment\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Payment\Models\Payment;

/**
 * Builds the subscription status + payment-history summary shown on both the
 * desktop POS billing screen (JSON) and the web billing page (Blade) — kept
 * in one place so the two surfaces never drift on what "overdue" or "active"
 * means.
 */
class SubscriptionSummaryService
{
    public function __construct(
        private readonly StripeSubscriptionService $stripe,
        private readonly PaymentProvisioningService $provisioning,
    ) {}

    /**
     * Never includes raw Stripe identifiers or metadata, only what's safe to
     * show the business owner in a billing screen.
     *
     * @return array<string, mixed>
     */
    public function summarize(Business $business, ?User $requester): array
    {
        $this->backfillMissingPayment($business);

        $payments = $business->payments()->with('package')->limit(50)->get();
        $this->backfillMissingDueDate($payments);

        $activeSubscription = $payments->first(
            fn (Payment $p) => $p->payment_type === Payment::TYPE_SUBSCRIPTION
                && $p->isSucceeded()
                && $p->stripe_subscription_status === 'active'
        );
        if ($activeSubscription) {
            $this->syncPeriodFromStripe($activeSubscription);
        }

        $overdue = $business->overdueSubscriptionPayment();
        $due = $overdue ? null : $business->dueSubscriptionPayment();

        return [
            'subscription_status' => $activeSubscription?->stripe_subscription_status
                ?? $business->getSetting('business.subscription_status'),
            'next_renewal_at' => $activeSubscription?->current_period_end?->toIso8601String(),
            'cancel_at_period_end' => (bool) $activeSubscription?->cancel_at_period_end,
            'access_until' => $activeSubscription?->cancel_at_period_end
                ? $activeSubscription->current_period_end?->toIso8601String()
                : null,
            'can_manage_subscription' => $activeSubscription !== null
                && $requester instanceof User
                && (int) $activeSubscription->user_id === (int) $requester->id,
            'subscription_ended' => $business->subscriptionHasEnded(),
            'overdue' => $overdue ? [
                'payment_id' => $overdue->id,
                'due_at' => $overdue->due_at?->toIso8601String(),
                'can_pay' => $requester instanceof User && (int) $overdue->user_id === (int) $requester->id,
            ] : null,
            // Unpaid but still inside the grace period — warn, don't lock yet.
            'payment_due' => $due ? $this->formatDue($due, $requester) : null,
            'items' => $payments->map(fn (Payment $p) => $this->formatPayment($p))->all(),
        ];
    }

    /**
     * Rows created before period tracking (or before the webhook arrived) have
     * no renewal date — pull it from Stripe once so the billing screen and the
     * cancel flow have a real "access until" date.
     */
    private function syncPeriodFromStripe(Payment $payment): void
    {
        if ($payment->current_period_end !== null || ! $payment->stripe_subscription_id) {
            return;
        }

        try {
            $sub = $this->stripe->retrieveSubscription($payment->stripe_subscription_id);
        } catch (\Throwable $e) {
            report($e);

            return;
        }

        $end = $sub->current_period_end ?? ($sub->items->data[0]->current_period_end ?? null);

        Payment::query()->where('stripe_subscription_id', $payment->stripe_subscription_id)->update([
            'current_period_end' => $end !== null ? \Illuminate\Support\Carbon::createFromTimestamp($end) : null,
            'cancel_at_period_end' => (bool) ($sub->cancel_at_period_end ?? false) || ($sub->cancel_at ?? null) !== null,
        ]);
        $payment->refresh();
    }

    /**
     * Businesses created before the Payment module shipped (or otherwise
     * missing their initial billing record) never got a Payment row, so they
     * show as neither paid nor due. Back-fill it here, on first read, using
     * the same provisioning logic that runs at signup — this only ever
     * creates the missing row once, since it's guarded on having none at all.
     */
    private function backfillMissingPayment(Business $business): void
    {
        if ($business->payments()->exists()) {
            return;
        }

        $package = $business->package;
        $owner = $business->user;
        if (! $package || ! $owner) {
            return;
        }

        $this->provisioning->createInitialPayment($business, $package, $owner);
    }

    /**
     * Payments left pending/failed/canceled from before `due_at` tracking
     * existed have no deadline at all, so they could never lock or show a
     * real countdown. Give them a fresh grace period from now — we don't know
     * their true original due date, but "never enforceable" is worse than a
     * slightly generous one.
     *
     * @param Collection<int, Payment> $payments
     */
    private function backfillMissingDueDate(Collection $payments): void
    {
        $actionable = [Payment::STATUS_PENDING, Payment::STATUS_FAILED, Payment::STATUS_CANCELED];

        foreach ($payments as $payment) {
            if ($payment->payment_type === Payment::TYPE_SUBSCRIPTION
                && in_array($payment->payment_status, $actionable, true)
                && $payment->due_at === null
            ) {
                $payment->update(['due_at' => now()->addDays(Payment::GRACE_PERIOD_DAYS)]);
            }
        }
    }

    /**
     * Grace-period warning payload, shared by the billing summary and the
     * web layout's warning bar (EnsureWebSubscriptionSettled).
     *
     * @return array<string, mixed>
     */
    public function formatDue(Payment $payment, ?User $requester): array
    {
        return [
            'payment_id' => $payment->id,
            'payment_status' => $payment->payment_status,
            'due_at' => $payment->due_at?->toIso8601String(),
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'can_pay' => $requester instanceof User && (int) $payment->user_id === (int) $requester->id,
        ];
    }

    /** @return array<string, mixed> */
    public function formatPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'plan' => $payment->package?->name,
            'payment_type' => $payment->payment_type,
            'payment_status' => $payment->payment_status,
            'billing_cycle' => $payment->billing_cycle,
            'gateway' => $payment->gateway,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'current_period_end' => $payment->current_period_end?->toIso8601String(),
            'failure_reason' => $payment->failure_reason,
            'due_at' => $payment->due_at?->toIso8601String(),
            'is_overdue' => $payment->isOverdue(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }
}
