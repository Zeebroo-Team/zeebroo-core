<?php

namespace Modules\CRM\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\CRM\Models\Lead;
use Modules\Sales\Models\QuotationRevisionRequest;

/** The head approved or rejected a revision request (its status says which). */
class QuotationRevisionDecided implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly QuotationRevisionRequest $request,
        public readonly int $byUserId,
    ) {}
}
