<?php

namespace Modules\Settings\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Business\Models\Business;
use Modules\Settings\Models\Setting;
use Modules\Settings\Services\SettingsService;

class SeedBusinessSettingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $businessId)
    {
    }

    public function handle(SettingsService $settingsService): void
    {
        $business = Business::query()->find($this->businessId);
        if (!$business) {
            return;
        }

        $defaultBusinessSettings = $settingsService->getDefaultSettingsByScope('business');
        if (empty($defaultBusinessSettings)) {
            return;
        }

        foreach ($defaultBusinessSettings as $key => $value) {
            $exists = Setting::query()
                ->where('scope_type', $business->getMorphClass())
                ->where('scope_id', $business->getKey())
                ->where('key', $key)
                ->exists();
            if (!$exists) {
                $settingsService->set($business, (string) $key, $value);
            }
        }
    }
}
