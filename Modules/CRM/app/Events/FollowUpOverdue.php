<?php

namespace Modules\CRM\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\CRM\Models\FollowUp;

/** A follow-up is still pending after the day it was due (crm:process-follow-ups, once per follow-up). */
class FollowUpOverdue implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly FollowUp $followUp) {}
}
