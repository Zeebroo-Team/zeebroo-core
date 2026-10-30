<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Services\CrmSettingsService;
use Modules\CRM\Services\DuplicateLeadService;
use Modules\Pos\Models\Customer;

/**
 * php artisan crm:normalize-customer-phones           → dry run: counts the customers whose normalised phone would change
 * php artisan crm:normalize-customer-phones --apply   → saves pos_customers.normalized_phone and trims / lower-cases
 *                                                        the emails (both used by the duplicate check)
 *
 * Run it once after deploying the customer duplicate check, after restoring a backup, and
 * again after changing a business's phone country code.
 */
class NormalizeCustomerPhonesCommand extends Command
{
    protected $signature = 'crm:normalize-customer-phones
        {--apply : Actually save the normalised numbers (default is a dry run)}
        {--business= : Only this business id}';

    protected $description = 'Fill pos_customers.normalized_phone from each customer\'s phone and its business\'s country code, and clean the emails';

    public function handle(CrmSettingsService $settings, DuplicateLeadService $duplicates): int
    {
        $business = $this->option('business') !== null ? (int) $this->option('business') : null;
        $apply    = (bool) $this->option('apply');
        $checked  = 0;
        $changed  = [];

        Customer::query()
            ->when($business !== null, fn ($q) => $q->where('business_id', $business))
            ->select(['id', 'business_id', 'phone', 'normalized_phone', 'email'])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use ($settings, $duplicates, $apply, &$checked, &$changed) {
                foreach ($customers as $customer) {
                    $checked++;
                    $update = array_filter([
                        'normalized_phone' => $settings->normalizePhone($customer->business_id, $customer->phone),
                        // Emails are matched as saved, so they are kept trimmed and lower-cased
                        'email'            => $duplicates->cleanEmail($customer->email),
                    ], fn ($value, $column) => $value !== $customer->{$column}, ARRAY_FILTER_USE_BOTH);
                    if ($update === []) {
                        continue;
                    }

                    $changed[$customer->business_id] = ($changed[$customer->business_id] ?? 0) + 1;
                    if ($apply) {
                        // Plain query update: no saving hook or timestamps for derived values
                        Customer::query()->whereKey($customer->id)->toBase()->update($update);
                    }
                }
            });

        $this->info($checked . ' customer(s) checked, ' . array_sum($changed) . ' ' . ($apply ? 'updated' : 'to update') . '.');
        if ($changed !== []) {
            $this->table(['business', 'country code', 'customers'], collect($changed)
                ->map(fn ($count, $businessId) => [$businessId, $settings->phoneCountryCode($businessId), $count])
                ->values()
                ->all());
        }

        if (! $apply) {
            $this->line('Dry run — nothing changed. Run again with --apply.');
        }

        return self::SUCCESS;
    }
}
