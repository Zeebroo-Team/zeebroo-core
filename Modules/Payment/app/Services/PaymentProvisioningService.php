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
    public function createInitialPayment(Business $business, ?Package $package, User $user, string $platform = 'desktop', ?Package $trialFeaturePackage = null): ?Payment
    {
        if (! $package) {
            $business->setSetting('business.subscription_status', 'active');

            return null;
        }

        if ($package->is_trial) {
            return $this->recordTrial($business, $package, $user, $platform, $trialFeaturePackage);
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
    /**
     * Packages a trial user may pick to try: every active, non-trial package
     * shown on that platform. Their features are what the trial unlocks.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Package>
     */
    public function trialChoices(string $platform = 'desktop')
    {
        return Package::query()
            ->where('is_active', true)
            ->where('is_trial', false)
            ->visibleForPlatform($platform)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * The package to try during the trial, validated against trialChoices().
     * Null when $package is not the trial package, or when none was sent and
     * it isn't required (older desktop clients - the trial package's own
     * features then apply, as before).
     */
    public function resolveTrialFeaturePackage(?Package $package, mixed $trialPackageId, string $platform = 'desktop', bool $required = false): ?Package
    {
        if (! $package?->is_trial) {
            return null;
        }

        if ($trialPackageId === null || $trialPackageId === '') {
            if ($required) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'trial_package_id' => ['Choose which package you want to try for free.'],
                ]);
            }

            return null;
        }

        $tried = $this->trialChoices($platform)->firstWhere('id', (int) $trialPackageId);
        if (! $tried) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'trial_package_id' => ['The package you chose to try is no longer available.'],
            ]);
        }

        return $tried;
    }

    private function recordTrial(Business $business, Package $package, User $user, string $platform, ?Package $trialFeaturePackage = null): Payment
    {
        if ($user->trial_used_at !== null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'package_id' => ['You have already used your free trial. Please choose another package.'],
            ]);
        }

        $endsAt = now()->addDays($package->trialDays());

        $business->forceFill([
            'trial_ends_at' => $endsAt,
            'trial_reminder_sent_at' => null,
            'trial_package_id' => $trialFeaturePackage?->id,
        ])->save();
        $business->unsetRelation('trialPackage');
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
