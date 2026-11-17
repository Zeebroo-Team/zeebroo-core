<?php

namespace Modules\CRM\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;

/**
 * A lead moved into a stage that has entry actions (LeadStage::entryRules) — the stage's task,
 * notifications, auto-assign and the follow-up date asked for on the move.
 */
class LeadEnteredStage implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly LeadStage $stage,
        public readonly ?int $byUserId = null,
        public readonly ?string $followUpAt = null,
    ) {}
}
