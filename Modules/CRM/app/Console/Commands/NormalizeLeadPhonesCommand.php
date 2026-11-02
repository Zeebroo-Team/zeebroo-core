<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Lead;
use Modules\CRM\Services\CrmSettingsService;

/**
 * php artisan crm:normalize-lead-phones           → dry run: counts the leads whose normalised phone would change
 * php artisan crm:normalize-lead-phones --apply   → saves crm_leads.normalized_phone (used by the duplicate check)
 *
 * Run it once after deploying the marketing intake, and again after changing a business's
 * phone country code.
 */
class NormalizeLeadPhonesCommand extends Command
{
    protected $signature = 'crm:normalize-lead-phones
        {--apply : Actually save the normalised numbers (default is a dry run)}
        {--business= : Only this business id}';

    protected $description = 'Fill crm_leads.normalized_phone from each lead\'s phone and its business\'s country code';

    public function handle(CrmSettingsService $settings): int
    {
        $business = $this->option('business') !== null ? (int) $this->option('business') : null;
        $apply    = (bool) $this->option('apply');
        $checked  = 0;
        $changed  = [];

        Lead::query()
            ->when($business !== null, fn ($q) => $q->where('business_id', $business))
            ->select(['id', 'business_id', 'phone', 'normalized_phone'])
            ->orderBy('id')
            ->chunkById(500, function ($leads) use ($settings, $apply, &$checked, &$changed) {
                foreach ($leads as $lead) {
                    $checked++;
                    $normal = $settings->normalizePhone($lead->business_id, $lead->phone);
                    if ($normal === $lead->normalized_phone) {
                        continue;
                    }

                    $changed[$lead->business_id] = ($changed[$lead->business_id] ?? 0) + 1;
                    if ($apply) {
                        // Plain query update: no audit entry or timestamps for a derived column
                        Lead::query()->whereKey($lead->id)->toBase()->update(['normalized_phone' => $normal]);
                    }
                }
            });

        $this->info($checked . ' lead(s) checked, ' . array_sum($changed) . ' ' . ($apply ? 'updated' : 'to update') . '.');
        if ($changed !== []) {
            $this->table(['business', 'country code', 'leads'], collect($changed)
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
