<?php

namespace Modules\CRM\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\CRM\Models\Lead;

/**
 * The lead's assignees changed (LeadAssignmentService). $addedUserIds are the people now on
 * the lead who were not before; $newPrimaryUserId is set when the primary moved to someone else.
 */
class LeadAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param int[] $addedUserIds */
    public function __construct(
        public readonly Lead $lead,
        public readonly array $addedUserIds,
        public readonly string $action,
        public readonly ?int $byUserId = null,
        public readonly ?int $newPrimaryUserId = null,
    ) {}
}
