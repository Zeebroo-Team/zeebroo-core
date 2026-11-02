<?php

namespace Modules\CRM\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\CRM\Models\LeadImport;
use Modules\CRM\Services\LeadImportService;

/** Runs a large lead import (LeadImportService::QUEUE_ABOVE_ROWS rows or more) off the request. */
class RunLeadImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly int $importId) {}

    public function handle(LeadImportService $service): void
    {
        $import = LeadImport::find($this->importId);
        if ($import !== null && $import->status === LeadImport::STATUS_QUEUED) {
            $service->run($import);
        }
    }

    public function failed(\Throwable $e): void
    {
        LeadImport::query()
            ->whereKey($this->importId)
            ->whereIn('status', [LeadImport::STATUS_QUEUED, LeadImport::STATUS_PROCESSING])
            ->update(['status' => LeadImport::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500), 'finished_at' => now()]);
    }
}
