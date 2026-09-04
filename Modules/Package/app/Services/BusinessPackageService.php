<?php

namespace Modules\Package\Services;

use Modules\Business\Models\Business;
use Modules\Package\Models\BusinessFeatureOverride;
use Modules\Package\Models\Package;

class BusinessPackageService
{
    /**
     * Assign a package (or none) to a business, grant/revoke unlimited access,
     * and store only the feature keys that differ from the package's own
     * default list as per-business overrides.
     */
    public function assign(Business $business, ?int $packageId, bool $unlimitedAccess, array $selectedFeatures): void
    {
        $business->package_id = $packageId;
        $business->has_unlimited_access = $unlimitedAccess;
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
}
