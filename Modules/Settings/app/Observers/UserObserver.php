<?php

namespace Modules\Settings\Observers;

use App\Models\User;
use Modules\Settings\Jobs\SeedUserSettingsJob;

class UserObserver
{
    public function created(User $user): void
    {
        SeedUserSettingsJob::dispatch((int) $user->id);
    }
}
