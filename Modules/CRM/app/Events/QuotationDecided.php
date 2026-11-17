<?php

namespace Modules\CRM\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\CRM\Models\Lead;
use Modules\Sales\Models\Quotation;

/** The head approved, returned or rejected a submitted lead quotation ($action: a QuotationApproval action). */
class QuotationDecided implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly Quotation $quotation,
        public readonly string $action,
        public readonly int $byUserId,
        public readonly ?string $comment = null,
    ) {}
}
