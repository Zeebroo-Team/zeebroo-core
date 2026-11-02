<?php

namespace Modules\CRM\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\CRM\Models\Lead;
use Modules\Sales\Models\Quotation;

/**
 * A lead quotation version reached the customer for the first time (not a re-send of the
 * same version). Follow-ups are scheduled from it.
 */
class QuotationSent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly Quotation $quotation,
        public readonly ?int $byUserId = null,
    ) {}
}
