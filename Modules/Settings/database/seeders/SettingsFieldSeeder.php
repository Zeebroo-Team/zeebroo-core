<?php

namespace Modules\Settings\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Business\Models\Business;
use Modules\Settings\Services\SettingsService;

class SettingsFieldSeeder extends Seeder
{
    public function run(): void
    {
        /** @var SettingsService $settingsService */
        $settingsService = app(SettingsService::class);
        $userFields = $settingsService->getDefaultSettingsByScope('user');
        $businessFields = $settingsService->getDefaultSettingsByScope('business');

        User::query()->each(function (User $user) use ($settingsService, $userFields): void {
            $settingsService->setMany($user, $userFields);
        });

        Business::query()->each(function (Business $business) use ($settingsService, $businessFields): void {
            $settingsService->setMany($business, $businessFields);
        });
    }
}
