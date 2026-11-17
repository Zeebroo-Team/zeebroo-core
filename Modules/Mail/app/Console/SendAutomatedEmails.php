<?php

namespace Modules\Mail\Console;

use Illuminate\Console\Command;
use Modules\Mail\Services\AutomatedEmailService;

class SendAutomatedEmails extends Command
{
    protected $signature = 'mail:automated-emails';

    protected $description = 'Queue the scheduled automated emails (inactivity reminders, activity reports) that are due';

    public function handle(AutomatedEmailService $automated): int
    {
        foreach ($automated->runScheduled() as $key => $count) {
            $this->line("{$key}: {$count} queued");
        }

        return self::SUCCESS;
    }
}
