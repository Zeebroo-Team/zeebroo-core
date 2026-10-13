<?php

namespace Modules\Package\Services;

use Modules\Business\Models\Business;
use Modules\Package\Models\BusinessFeatureOverride;
use Modules\Package\Models\Package;
use Modules\Payment\Models\Payment;

class BusinessPackageService
{
    /**
     * Assign a package (or none) to a business, grant/revoke unlimited access,
     * and store only the feature keys that differ from the package's own
     * default list as per-business overrides.
     */
    public function assign(Business $business, ?int $packageId, bool $unlimitedAccess, array $selectedFeatures, bool $manual = false): void
    {
        $packageId = $manual ? null : $packageId;

        $business->package_id = $packageId;
        $business->has_unlimited_access = $unlimitedAccess;
        $business->has_manual_features = $manual;
        $business->save();

        $packageFeatures = $packageId
            ? (Package::find($packageId)?->features ?? [])
            : [];

        $catalog = array_keys(config('features.list', []));
        $keysToDelete = [];
        $overridesToUpsert = [];

        foreach ($catalog as $key) {
            $inPackage = in_array($key, $packageFeatures, true);
            $isSelected = in_array($key, $selectedFeatures, true);

            if ($inPackage === $isSelected) {
                $keysToDelete[] = $key;

                continue;
            }

            $overridesToUpsert[] = ['feature_key' => $key, 'enabled' => $isSelected];
        }

        if (! empty($keysToDelete)) {
            $business->featureOverrides()->whereIn('feature_key', $keysToDelete)->delete();
        }

        foreach ($overridesToUpsert as $row) {
            BusinessFeatureOverride::updateOrCreate(
                ['business_id' => $business->id, 'feature_key' => $row['feature_key']],
                ['enabled' => $row['enabled']],
            );
        }
    }

    /**
     * Set the business's payment grace period (null = platform default) and
     * move the deadline of its still-unsettled subscription payments by the
     * change, so the adjustment applies to the bill that's already pending —
     * and can unlock an already-overdue business — not only to future ones.
     */
    public function setGraceDays(Business $business, ?int $graceDays): void
    {
        $oldDays = $business->paymentGraceDays();
        $business->payment_grace_days = $graceDays;
        $business->save();

        $deltaDays = $business->paymentGraceDays() - $oldDays;
        if ($deltaDays === 0) {
            return;
        }

        $business->payments()
            ->where('payment_type', Payment::TYPE_SUBSCRIPTION)
            ->whereIn('payment_status', [Payment::STATUS_PENDING, Payment::STATUS_FAILED, Payment::STATUS_CANCELED])
            ->whereNotNull('due_at')
            ->get()
            ->each(fn (Payment $payment) => $payment->update(['due_at' => $payment->due_at->addDays($deltaDays)]));
    }
}
