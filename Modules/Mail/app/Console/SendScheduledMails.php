<?php

namespace Modules\Mail\Console;

use Illuminate\Console\Command;
use Modules\Mail\Jobs\SendScheduledMailJob;
use Modules\Mail\Services\ScheduledMailService;

class SendScheduledMails extends Command
{
    protected $signature = 'mail:send-scheduled';

    protected $description = 'Queue a send job for every scheduled email that is now due';

    public function handle(ScheduledMailService $scheduledMails): int
    {
        $due = $scheduledMails->due();

        if ($due->isEmpty()) {
            $this->line('Nothing due.');

            return self::SUCCESS;
        }

        foreach ($due as $scheduled) {
            SendScheduledMailJob::dispatch($scheduled->id);
            $this->line("Queued scheduled mail #{$scheduled->id} to {$scheduled->to_address}");
        }

        return self::SUCCESS;
    }
}
