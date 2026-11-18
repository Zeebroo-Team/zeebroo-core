<?php

namespace Modules\CRM\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\CRM\Models\Lead;
use Modules\Sales\Models\QuotationRevisionRequest;

/** Staff asked the head for permission to change an issued lead quotation. */
class QuotationRevisionRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly QuotationRevisionRequest $request,
        public readonly int $byUserId,
    ) {}
}
