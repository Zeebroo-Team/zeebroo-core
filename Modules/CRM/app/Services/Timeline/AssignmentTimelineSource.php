<?php

namespace Modules\CRM\Services\Timeline;

use Modules\CRM\Models\Lead;
use Modules\CRM\Services\LeadAssignmentService;

final class AssignmentTimelineSource implements TimelineSource
{
    public function __construct(private readonly LeadAssignmentService $assignments) {}

    public function key(): string { return 'assignment'; }

    public function label(): string { return 'Assignments'; }

    public function entries(Lead $lead): iterable
    {
        $logs = $this->assignments->history($lead);

        foreach ($this->assignments->presentHistory($logs) as $row) {
            $log = $logs->firstWhere('id', $row['id']);
            yield new TimelineEntry('assignment', $row['id'], $log->created_at, $row['title'], $row['note'],
                $row['by']['id'] ?? null, $row['by']['name'] ?? null,
                $row['action'] === 'forwarded' ? 'fa-share' : 'fa-user-tag', $row['action'] === 'forwarded' ? '#6366f1' : '#0ea5e9',
                ['action' => $row['action'], 'assignees' => $row['assignees'], 'added' => $row['added'], 'removed' => $row['removed']]);
        }
    }
}
