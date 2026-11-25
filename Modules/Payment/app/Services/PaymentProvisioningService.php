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
    public function createInitialPayment(Business $business, ?Package $package, User $user, string $platform = 'desktop'): ?Payment
    {
        if (! $package) {
            $business->setSetting('business.subscription_status', 'active');

            return null;
        }

        if ($package->is_trial) {
            return $this->recordTrial($business, $package, $user, $platform);
        }

        if ($package->is_free) {
            return $this->recordFree($business, $package, $user, $platform);
        }

        $amount = $package->effectivePrice();

        if ($amount <= 0) {
            return $this->recordFree($business, $package, $user, $platform);
        }

        $payment = Payment::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'payment_type' => Payment::TYPE_SUBSCRIPTION,
            'payment_status' => Payment::STATUS_PENDING,
            'billing_cycle' => 'monthly',
            'platform' => $platform,
            'amount' => $amount,
            'currency' => strtolower($package->currency),
            'due_at' => Payment::dueAtFor($business),
        ]);

        $business->setSetting('business.subscription_status', 'pending_payment');

        return $payment;
    }

    /**
     * Starts the free trial — no payment gateway. The trial is single-use per
     * user; a user who already had one is refused (the package list hides it
     * from them too, see Package::scopeOfferedTo).
     */
    private function recordTrial(Business $business, Package $package, User $user, string $platform): Payment
    {
        if ($user->trial_used_at !== null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'package_id' => ['You have already used your free trial. Please choose another package.'],
            ]);
        }

        $endsAt = now()->addDays($package->trialDays());

        $business->forceFill(['trial_ends_at' => $endsAt, 'trial_reminder_sent_at' => null])->save();
        $user->forceFill(['trial_used_at' => now()])->save();

        $payment = Payment::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'payment_type' => Payment::TYPE_TRIAL,
            'payment_status' => Payment::STATUS_SUCCEEDED,
            'billing_cycle' => null,
            'platform' => $platform,
            'amount' => 0,
            'currency' => strtolower($package->currency),
            'paid_at' => now(),
            'current_period_end' => $endsAt,
        ]);

        $business->setSetting('business.subscription_status', 'active');

        return $payment;
    }

    private function recordFree(Business $business, Package $package, User $user, string $platform = 'desktop'): Payment
    {
        $payment = Payment::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'payment_type' => Payment::TYPE_FREE,
            'payment_status' => Payment::STATUS_SUCCEEDED,
            'billing_cycle' => null,
            'platform' => $platform,
            'amount' => 0,
            'currency' => strtolower($package->currency),
            'paid_at' => now(),
        ]);

        $business->setSetting('business.subscription_status', 'active');

        return $payment;
    }
}
