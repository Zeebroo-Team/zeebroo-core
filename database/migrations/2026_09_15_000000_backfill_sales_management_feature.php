<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Business\Models\Business;
use Modules\Package\Models\Package;

return new class extends Migration
{
    /**
     * Sales Management is split out of Point of Sale as its own feature. Any business
     * or package that already had point_of_sale enabled keeps seeing the Sales tab by
     * getting sales_management backfilled to the same state; from here on the two
     * toggles are independent.
     */
    public function up(): void
    {
        Package::query()->each(function (Package $package): void {
            $features = $package->features ?? [];
            if (in_array('point_of_sale', $features, true) && ! in_array('sales_management', $features, true)) {
                $features[] = 'sales_management';
                $package->features = $features;
                $package->save();
            }
        });

        Business::query()->each(function (Business $business): void {
            $features = (array) $business->getSetting('business.features', []);
            if (($features['point_of_sale'] ?? false) && ! array_key_exists('sales_management', $features)) {
                $features['sales_management'] = true;
                $business->setSetting('business.features', $features);
            }
        });
    }

    public function down(): void
    {
        // Data backfill only — not meaningfully reversible.
    }
};
