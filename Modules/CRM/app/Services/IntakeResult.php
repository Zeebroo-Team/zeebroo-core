<?php

namespace Modules\CRM\Services;

use Modules\CRM\Models\Lead;

/** What LeadIntakeService::capture() did with a contact. */
final class IntakeResult
{
    public function __construct(
        public readonly Lead $lead,
        /** False when the contact already had an open lead and the enquiry was logged on it. */
        public readonly bool $created,
        /** The lead was put in the marketing intake pipeline rather than the one it was entered in. */
        public readonly bool $routedToIntake = false,
    ) {}

    public function isDuplicate(): bool
    {
        return ! $this->created;
    }

    public function message(): string
    {
        return match (true) {
            ! $this->created       => 'This contact already has a lead ("' . $this->lead->name . '"). The enquiry was added to it and flagged for Marketing.',
            $this->routedToIntake  => 'Lead "' . $this->lead->name . '" sent to the Marketing inbox.',
            default                => 'Lead "' . $this->lead->name . '" created.',
        };
    }

    /** The `intake` block the desktop API returns next to the lead. */
    public function toArray(): array
    {
        return [
            'created'          => $this->created,
            'duplicate'        => $this->isDuplicate(),
            'routed_to_intake' => $this->routedToIntake,
            'needs_review'     => (bool) $this->lead->needs_review,
            'review_reason'    => $this->lead->review_reason,
            'message'          => $this->message(),
        ];
    }
}
