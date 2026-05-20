<?php

namespace Modules\Settings\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Settings\Services\SettingsService;

class SeedUserSettingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $userId)
    {
    }

    public function handle(SettingsService $settingsService): void
    {
        $user = User::query()->find($this->userId);
        if (!$user) {
            return;
        }

        $defaultUserSettings = $settingsService->getDefaultSettingsByScope('user');
        if (empty($defaultUserSettings)) {
            return;
        }

        $settingsService->setMany($user, $defaultUserSettings);
    }
}
