<?php

namespace Modules\CRM\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\CRM\Models\Lead;

/** Marketing forwarded the lead to a department's pipeline (and maybe a division). */
class LeadForwarded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly ?int $byUserId = null,
        public readonly ?string $note = null,
    ) {}
}
