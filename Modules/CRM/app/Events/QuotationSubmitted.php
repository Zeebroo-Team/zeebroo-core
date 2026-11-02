<?php

namespace Modules\CRM\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\CRM\Models\Lead;
use Modules\Sales\Models\Quotation;

/** A lead quotation was submitted for the head's approval. */
class QuotationSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param array{percent: float, limit: ?float, over_limit: bool, requires_approval: bool} $discount */
    public function __construct(
        public readonly Lead $lead,
        public readonly Quotation $quotation,
        public readonly int $byUserId,
        public readonly array $discount,
    ) {}
}
