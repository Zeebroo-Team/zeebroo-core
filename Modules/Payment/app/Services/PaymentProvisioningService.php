<?php

namespace Modules\Payment\Services;

use App\Models\User;
use Modules\Business\Models\Business;
use Modules\Package\Models\Package;
use Modules\Payment\Models\Payment;

class PaymentProvisioningService
{
    /**
     * Records the initial Payment row for a newly onboarded business — free
     * packages (or a zero effective price) are marked succeeded immediately
     * and the business is activated; paid packages get a pending row and the
     * business is held at `pending_payment` until Stripe Checkout confirms
     * the charge (see StripeSubscriptionService / PaymentController).
     *
     * Returns null only for the no-package case — matches the pre-existing
     * behavior where no package means nothing to record, just an active
     * business.
     */
    public function createInitialPayment(Business $business, ?Package $package, User $user): ?Payment
    {
        if (! $package) {
            $business->setSetting('business.subscription_status', 'active');

            return null;
        }

        if ($package->is_free) {
            return $this->recordFree($business, $package, $user);
        }

        $amount = ($package->discounted_price !== null && (float) $package->discounted_price < (float) $package->price)
            ? (float) $package->discounted_price
            : (float) $package->price;

        if ($amount <= 0) {
            return $this->recordFree($business, $package, $user);
        }

        $payment = Payment::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'payment_type' => Payment::TYPE_SUBSCRIPTION,
            'payment_status' => Payment::STATUS_PENDING,
            'billing_cycle' => 'monthly',
            'amount' => $amount,
            'currency' => 'usd',
            'due_at' => now()->addDays(Payment::GRACE_PERIOD_DAYS),
        ]);

        $business->setSetting('business.subscription_status', 'pending_payment');

        return $payment;
    }

    private function recordFree(Business $business, Package $package, User $user): Payment
    {
        $payment = Payment::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'payment_type' => Payment::TYPE_FREE,
            'payment_status' => Payment::STATUS_SUCCEEDED,
            'billing_cycle' => null,
            'amount' => 0,
            'currency' => 'usd',
            'paid_at' => now(),
        ]);

        $business->setSetting('business.subscription_status', 'active');

        return $payment;
    }
}
