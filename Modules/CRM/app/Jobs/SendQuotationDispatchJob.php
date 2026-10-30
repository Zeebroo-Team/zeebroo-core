<?php

namespace Modules\CRM\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\CRM\Services\QuotationDispatch\QuotationDispatcher;
use Modules\Sales\Models\QuotationDispatch;

/** Sends one queued quotation dispatch (see QuotationDispatcher::deliver). */
class SendQuotationDispatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Delivery failures are recorded on the dispatch, not retried blindly. */
    public int $tries = 1;

    public function __construct(public readonly int $dispatchId) {}

    public function handle(QuotationDispatcher $dispatcher): void
    {
        $dispatch = QuotationDispatch::find($this->dispatchId);
        if ($dispatch !== null && $dispatch->status === QuotationDispatch::STATUS_QUEUED) {
            $dispatcher->deliver($dispatch);
        }
    }
}
