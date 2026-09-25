<?php

namespace Modules\Auth\Listeners;

use Illuminate\Auth\Events\Login;
use Modules\Business\Models\Business;

class ResetPlatformChoicePrompt
{
    /**
     * Re-arm the "how do you want to use Zeebroo" prompt on every web login,
     * so it is asked again each session instead of once per business ever.
     */
    public function handle(Login $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        $business = $event->user->businesses()->latest()->first();
        if ($business instanceof Business && $business->platform_choice_shown_at !== null) {
            $business->forceFill(['platform_choice_shown_at' => null])->save();
        }
    }
}
