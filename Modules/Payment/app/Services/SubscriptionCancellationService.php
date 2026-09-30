<?php

namespace Modules\Payment\Services;

use App\Models\User;
use Modules\Business\Models\Business;
use Modules\Payment\Exceptions\SubscriptionActionException;
use Modules\Payment\Models\Payment;

/**
 * Cancels/resumes the business's active Stripe subscription — shared by the
 * desktop POS API and the web billing page so both surfaces change the same
 * subscription the same way instead of maintaining parallel Stripe calls.
 */
class SubscriptionCancellationService
{
    public function __construct(private readonly StripeSubscriptionService $stripe) {}

    public function change(Business $business, User $requester, bool $cancel): Payment
    {
        $payment = $business->payments()
            ->where('payment_type', Payment::TYPE_SUBSCRIPTION)
            ->where('payment_status', Payment::STATUS_SUCCEEDED)
            ->where('stripe_subscription_status', 'active')
            ->whereNotNull('stripe_subscription_id')
            ->latest('paid_at')
            ->first();

        if (! $payment) {
            throw new SubscriptionActionException('There is no active subscription to change.', 422);
        }

        if ((int) $payment->user_id !== (int) $requester->id) {
            throw new SubscriptionActionException('You do not have permission to manage this subscription.', 403);
        }

        if ($payment->cancel_at_period_end === $cancel) {
            throw new SubscriptionActionException($cancel
                ? 'This subscription is already set to cancel.'
                : 'This subscription is not scheduled to cancel.', 422);
        }

        try {
            $this->stripe->setCancelAtPeriodEnd($payment->stripe_subscription_id, $cancel);
        } catch (\Throwable $e) {
            report($e);

            throw new SubscriptionActionException('Could not update your subscription. Please try again later.', 502);
        }

        // Every billing row of this Stripe subscription shares the flag.
        Payment::query()
            ->where('stripe_subscription_id', $payment->stripe_subscription_id)
            ->update(['cancel_at_period_end' => $cancel]);

        return $payment->refresh();
    }
}
