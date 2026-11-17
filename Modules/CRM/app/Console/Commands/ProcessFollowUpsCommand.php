<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Services\FollowUpService;

/** Runs every 15 minutes (CRMServiceProvider): follow-up reminders and overdue marking. */
class ProcessFollowUpsCommand extends Command
{
    protected $signature = 'crm:process-follow-ups';

    protected $description = 'Notify assignees of CRM follow-ups that are due and mark (and notify) the overdue ones';

    public function handle(FollowUpService $followUps): int
    {
        $counts = $followUps->process();
        $this->info("Follow-ups: {$counts['due']} due, {$counts['overdue']} newly overdue.");

        return self::SUCCESS;
    }
}
