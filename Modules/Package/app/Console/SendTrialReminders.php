<?php

namespace Modules\Package\Console;

use Illuminate\Console\Command;
use Modules\Business\Models\Business;
use Modules\Package\Notifications\TrialEndingSoon;

class SendTrialReminders extends Command
{
    protected $signature = 'package:trial-reminders';

    protected $description = 'Email business owners whose free trial ends within 2 days';

    public function handle(): int
    {
        $sent = 0;

        Business::query()
            ->whereNotNull('trial_ends_at')
            ->whereNull('trial_reminder_sent_at')
            ->where('trial_ends_at', '>', now())
            ->where('trial_ends_at', '<=', now()->addDays(2))
            ->with('user')
            ->chunkById(100, function ($businesses) use (&$sent): void {
                foreach ($businesses as $business) {
                    /** @var Business $business */
                    if ($business->user) {
                        try {
                            $business->user->notify(new TrialEndingSoon($business));
                        } catch (\Throwable $e) {
                            report($e);
                            continue; // retry next hour
                        }
                    }

                    $business->forceFill(['trial_reminder_sent_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("Sent {$sent} trial reminder(s).");

        return self::SUCCESS;
    }
}
