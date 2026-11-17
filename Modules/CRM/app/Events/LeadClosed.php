<?php

namespace Modules\CRM\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\CRM\Models\Lead;

/** An open lead was won or lost — by any path (board, Won / Lost action, customer response, convert). */
class LeadClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    const WON  = 'won';
    const LOST = 'lost';

    public function __construct(
        public readonly Lead $lead,
        public readonly string $outcome,
        public readonly ?int $byUserId = null,
    ) {}
}
