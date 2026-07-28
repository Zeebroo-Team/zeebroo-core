<?php

namespace Modules\ProjectManage\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\ProjectManage\Models\Task;
use Modules\ProjectManage\Models\TaskComment;
use Modules\ProjectManage\Models\TimeLog;
use Modules\ProjectManage\Models\Project;

class TaskService
{
    public function listForProject(Project $project, array $filters = []): Collection
    {
        $query = Task::query()
            ->where('project_id', $project->id)
            ->with(['assignedTo', 'milestone'])
            ->orderBy('sort_order')
            ->orderByDesc('id');

        if (filled($filters['status'] ?? '')) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['milestone_id'] ?? '')) {
            $query->where('milestone_id', (int) $filters['milestone_id']);
        }

        if (filled($filters['assigned_to'] ?? '')) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        if (filled($filters['priority'] ?? '')) {
            $query->where('priority', $filters['priority']);
        }

        return $query->get();
    }

    public function listForBusiness(Business $business, string $filter = 'open'): Collection
    {
        $query = Task::query()
            ->whereHas('project', fn ($q) => $q->where('business_id', $business->id))
            ->with(['assignedTo', 'project', 'milestone']);

        match ($filter) {
            'overdue' => $query->whereNotIn('status', [Task::STATUS_DONE])
                               ->whereNotNull('due_date')
                               ->whereDate('due_date', '<', now()->toDateString()),
            'mine'    => $query->where('assigned_to', auth()->id())
                               ->whereNotIn('status', [Task::STATUS_DONE]),
            'done'    => $query->where('status', Task::STATUS_DONE),
            'open'    => $query->whereNotIn('status', [Task::STATUS_DONE]),
            default   => null,
        };

        return $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderByDesc('id')->get();
    }

    /**
     * Returns tasks grouped by status column with sort_order.
     *
     * @return array<string, Collection>
     */
    public function boardForProject(Project $project): array
    {
        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with(['assignedTo', 'milestone'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $columns = [
            Task::STATUS_TODO        => collect(),
            Task::STATUS_IN_PROGRESS => collect(),
            Task::STATUS_REVIEW      => collect(),
            Task::STATUS_DONE        => collect(),
        ];

        foreach ($tasks as $task) {
            if (isset($columns[$task->status])) {
                $columns[$task->status]->push($task);
            }
        }

        return $columns;
    }

    public function create(Project $project, array $data): Task
    {
        return Task::create([
            'project_id'      => $project->id,
            'milestone_id'    => filled($data['milestone_id'] ?? '') ? (int) $data['milestone_id'] : null,
            'title'           => $data['title'],
            'description'     => filled($data['description'] ?? '') ? $data['description'] : null,
            'status'          => $data['status'] ?? Task::STATUS_TODO,
            'priority'        => $data['priority'] ?? Task::PRIORITY_NORMAL,
            'assigned_to'     => filled($data['assigned_to'] ?? '') ? (int) $data['assigned_to'] : null,
            'due_date'        => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
            'sort_order'      => (int) ($data['sort_order'] ?? 0),
            'estimated_hours' => filled($data['estimated_hours'] ?? '') ? $data['estimated_hours'] : null,
        ]);
    }

    public function update(Task $task, array $data): Task
    {
        $task->update([
            'milestone_id'    => filled($data['milestone_id'] ?? '') ? (int) $data['milestone_id'] : null,
            'title'           => $data['title'],
            'description'     => filled($data['description'] ?? '') ? $data['description'] : null,
            'status'          => $data['status'] ?? $task->status,
            'priority'        => $data['priority'] ?? $task->priority,
            'assigned_to'     => filled($data['assigned_to'] ?? '') ? (int) $data['assigned_to'] : null,
            'due_date'        => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
            'estimated_hours' => filled($data['estimated_hours'] ?? '') ? $data['estimated_hours'] : null,
        ]);

        return $task->fresh();
    }

    public function moveStatus(Task $task, string $status): Task
    {
        $updates = ['status' => $status];

        if ($status === Task::STATUS_DONE && !$task->completed_at) {
            $updates['completed_at'] = now();
        } elseif ($status !== Task::STATUS_DONE) {
            $updates['completed_at'] = null;
        }

        $task->update($updates);

        return $task;
    }

    public function complete(Task $task): Task
    {
        $task->update([
            'status'       => Task::STATUS_DONE,
            'completed_at' => now(),
        ]);

        return $task;
    }

    public function reopen(Task $task): Task
    {
        $task->update([
            'status'       => Task::STATUS_TODO,
            'completed_at' => null,
        ]);

        return $task;
    }

    public function addComment(Task $task, int $userId, string $body): TaskComment
    {
        return TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'body'    => $body,
        ]);
    }

    public function logTime(Task $task, int $userId, array $data): TimeLog
    {
        return TimeLog::create([
            'task_id'   => $task->id,
            'user_id'   => $userId,
            'minutes'   => (int) $data['minutes'],
            'logged_at' => $data['logged_at'],
            'note'      => filled($data['note'] ?? '') ? $data['note'] : null,
        ]);
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function taskForBusiness(Business $business, Task $task): ?Task
    {
        return (int) $task->project->business_id === (int) $business->id ? $task : null;
    }
}
