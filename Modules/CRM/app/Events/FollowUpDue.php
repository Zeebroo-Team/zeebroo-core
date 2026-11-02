<?php

namespace Modules\CRM\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\CRM\Models\FollowUp;

/** A pending follow-up reached its due time (crm:process-follow-ups, once per follow-up). */
class FollowUpDue implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly FollowUp $followUp) {}
}
