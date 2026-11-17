<?php

namespace Modules\CRM\Services\Timeline;

use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Task;

final class TaskTimelineSource implements TimelineSource
{
    public function key(): string { return 'task'; }

    public function label(): string { return 'Tasks'; }

    /** A task shows when it was added, and again when it was completed. */
    public function entries(Lead $lead): iterable
    {
        foreach ($lead->tasks()->with(['createdBy:id,name', 'assignedTo:id,name'])->get() as $t) {
            /** @var Task $t */
            $meta = ['task_id' => $t->id, 'status' => $t->status, 'due_at' => $t->due_at?->toIso8601String(), 'assigned_to' => $t->assignedTo?->name];
            yield new TimelineEntry('task', $t->id, $t->created_at, 'Added task: ' . $t->title, $t->description,
                $t->created_by, $t->createdBy?->name, 'fa-list-check', '#f59e0b', $meta);
            if ($t->completed_at !== null) {
                yield new TimelineEntry('task', $t->id . '-done', $t->completed_at, 'Completed task: ' . $t->title, null,
                    null, null, 'fa-circle-check', '#16a34a', $meta);
            }
        }
    }
}
