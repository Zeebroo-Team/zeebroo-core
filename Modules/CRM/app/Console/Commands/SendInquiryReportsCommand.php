<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Services\InquiryReportService;

/** Runs every 15 minutes (CRMServiceProvider): the daily inquiry status reports, at each business's set time. */
class SendInquiryReportsCommand extends Command
{
    protected $signature = 'crm:inquiry-reports';

    protected $description = 'Make the daily CRM inquiry status reports and email them to management (or leave them for the head to review)';

    public function handle(InquiryReportService $reports): int
    {
        $c = $reports->runScheduled();
        $this->info("Inquiry reports: {$c['businesses']} business(es), {$c['reports']} report(s), {$c['sent']} sent, {$c['failed']} failed.");

        return self::SUCCESS;
    }
}
