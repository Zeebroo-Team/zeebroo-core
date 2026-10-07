<?php

namespace Modules\ProjectManage\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Pos\Services\PosNotificationService;
use Modules\ProjectManage\Models\Task;
use Modules\ProjectManage\Models\TaskComment;
use Modules\ProjectManage\Models\TaskStatus;
use Modules\ProjectManage\Models\TimeLog;
use Modules\ProjectManage\Models\Project;

class TaskService
{
    public function listForProject(Project $project, array $filters = []): Collection
    {
        $query = Task::query()
            ->where('project_id', $project->id)
            ->with(['assignees', 'milestone'])
            ->withCount('attachments')
            ->orderBy('sort_order')
            ->orderByDesc('id');

        if (filled($filters['status'] ?? '')) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['milestone_id'] ?? '')) {
            $query->where('milestone_id', (int) $filters['milestone_id']);
        }

        if (filled($filters['assigned_to'] ?? '')) {
            $query->whereHas('assignees', fn ($q) => $q->where('users.id', (int) $filters['assigned_to']));
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
            ->with(['assignees', 'project', 'milestone'])
            ->withCount('attachments');

        match ($filter) {
            'overdue' => $query->whereNotIn('status', [Task::STATUS_DONE])
                               ->whereNotNull('due_date')
                               ->whereDate('due_date', '<', now()->toDateString()),
            'mine'    => $query->whereHas('assignees', fn ($q) => $q->where('users.id', auth()->id()))
                               ->whereNotIn('status', [Task::STATUS_DONE]),
            'done'    => $query->where('status', Task::STATUS_DONE),
            'open'    => $query->whereNotIn('status', [Task::STATUS_DONE]),
            default   => null,
        };

        return $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderByDesc('id')->get();
    }

    /**
     * "My Projects" data for one user: every task assigned to them (all statuses) in the
     * business's non-archived projects, plus those projects — the ones whose team they are on
     * or that hold one of their tasks — so the client can build per-project board columns.
     *
     * @return array{tasks: Collection, projects: Collection}
     */
    public function assignedWorkForUser(Business $business, int $userId): array
    {
        $tasks = Task::query()
            ->whereHas('project', fn ($q) => $q->where('business_id', $business->id)->where('status', '!=', Project::STATUS_ARCHIVED))
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $userId))
            ->with(['assignees', 'project', 'milestone'])
            ->withCount('attachments')
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderByDesc('id')
            ->get();

        $projectIds = $tasks->pluck('project_id')->unique()->values()->all();

        $projects = Project::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', Project::STATUS_ARCHIVED)
            ->where(fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $userId))
                                 ->orWhereIn('id', $projectIds))
            ->orderBy('name')
            ->get();

        return ['tasks' => $tasks, 'projects' => $projects];
    }

    public function isAssignee(Task $task, int $userId): bool
    {
        return $task->assignees()->where('users.id', $userId)->exists();
    }

    /**
     * Comment threads (oldest first, each with its replies), time logs and attachments
     * (newest first) of a task, for the task detail view.
     *
     * @return array{comments: Collection, time_logs: Collection, attachments: Collection}
     */
    public function activityForTask(Task $task): array
    {
        $task->loadMissing(['comments.user', 'timeLogs.user', 'attachments.user']);
        $files   = app(TaskAttachmentService::class);
        $replies = $task->comments->whereNotNull('parent_id')->groupBy('parent_id');

        return [
            'attachments' => $task->attachments->map(fn ($a) => $files->fmt($a))->values(),
            'comments'  => $task->comments->whereNull('parent_id')->map(fn (TaskComment $c) => $this->fmtComment($c) + [
                'replies' => ($replies[$c->id] ?? collect())->map(fn (TaskComment $r) => $this->fmtComment($r))->values(),
            ])->values(),
            'time_logs' => $task->timeLogs->map(fn (TimeLog $l) => [
                'id'        => $l->id,
                'user'      => $l->user?->name ?? 'System',
                'minutes'   => (int) $l->minutes,
                'logged_at' => $l->logged_at?->toDateString(),
                'note'      => $l->note,
            ])->values(),
        ];
    }

    /**
     * All board statuses for a project in column order (by sort number; on a tie
     * the built-in status comes first). "done" always sorts last.
     *
     * @return array<int, array{id:?int,status:string,label:string,color:?string,sort_order:int,is_custom:bool}>
     */
    public function statusesForProject(Project $project): array
    {
        $builtin = collect(Task::BUILTIN_STATUSES)->map(fn (string $label, string $key) => [
            'id'         => null,
            'status'     => $key,
            'label'      => $label,
            'color'      => null,
            'sort_order' => Task::BUILTIN_SORT[$key],
            'is_custom'  => false,
        ])->values();

        $custom = TaskStatus::where('project_id', $project->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (TaskStatus $s) => $this->fmtStatus($s));

        return $builtin->concat($custom)
            ->sortBy([['sort_order', 'asc'], ['is_custom', 'asc'], ['id', 'asc']])
            ->values()
            ->all();
    }

    /** @return array{id:int,status:string,label:string,color:?string,sort_order:int,is_custom:bool} */
    public function fmtStatus(TaskStatus $s): array
    {
        return [
            'id'         => $s->id,
            'status'     => $s->key,
            'label'      => $s->label,
            'color'      => $s->color,
            'sort_order' => (int) $s->sort_order,
            'is_custom'  => true,
        ];
    }

    /** Validation rules for creating / updating a custom status (shared by web + API). */
    public static function statusRules(bool $partial = false): array
    {
        return [
            'label'      => [$partial ? 'sometimes' : 'required', 'string', 'max:60'],
            'color'      => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:' . Task::CUSTOM_SORT_MAX],
        ];
    }

    /**
     * Assignee validation (shared by web + API): every id in assignee_ids — or the legacy
     * single assigned_to — must be a member of the project team.
     */
    public static function assigneeRules(Project $project): array
    {
        $member = Rule::exists('pm_project_members', 'user_id')->where(fn ($q) => $q->where('project_id', $project->id));

        return [
            'assignee_ids'   => ['nullable', 'array', 'max:100'],
            'assignee_ids.*' => ['integer', 'distinct', $member],
            'assigned_to'    => ['nullable', 'integer', $member],
        ];
    }

    public static function assigneeMessages(): array
    {
        $msg = 'Tasks can only be assigned to members of this project.';

        return ['assignee_ids.*.exists' => $msg, 'assigned_to.exists' => $msg];
    }

    /**
     * Assignee ids from validated data: assignee_ids wins; a legacy assigned_to is a one-person list.
     * Returns null when neither key was sent (leave assignees unchanged).
     *
     * @return int[]|null
     */
    public static function assigneeIdsFrom(array $data): ?array
    {
        if (array_key_exists('assignee_ids', $data)) {
            return array_map('intval', $data['assignee_ids'] ?? []);
        }
        if (array_key_exists('assigned_to', $data)) {
            return filled($data['assigned_to']) ? [(int) $data['assigned_to']] : [];
        }

        return null;
    }

    /**
     * Replaces the task's assignees (empty = unassigned). Membership is validated by assigneeRules().
     *
     * @param int[] $userIds
     */
    public function assign(Task $task, array $userIds): Task
    {
        $added = $task->syncAssignees($userIds);
        $this->notifyAssigned($task, $added);

        return $task->fresh(['assignees', 'milestone', 'project']);
    }

    /** @param int[] $userIds newly added assignees */
    private function notifyAssigned(Task $task, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        // A failed notification must never block the assignment itself.
        DB::afterCommit(function () use ($task, $userIds) {
            try {
                app(PosNotificationService::class)->notifyTaskAssigned($task->fresh('project.business'), $userIds, auth()->id());
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /** @return string[] */
    public function statusKeysForProject(Project $project): array
    {
        return array_column($this->statusesForProject($project), 'status');
    }

    /**
     * Returns the project's status columns, each with its tasks (by sort_order).
     *
     * @return array<int, array{id:?int,status:string,label:string,color:?string,is_custom:bool,tasks:Collection}>
     */
    public function boardForProject(Project $project): array
    {
        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with(['assignees', 'milestone'])
            ->withCount('attachments')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('status');

        return array_map(
            fn (array $col) => $col + ['tasks' => $tasks->get($col['status'], collect())->values()],
            $this->statusesForProject($project),
        );
    }

    public function createStatus(Project $project, array $data): TaskStatus
    {
        $base = Str::limit(Str::slug($data['label'], '_'), 16, '') ?: 'status';
        $taken = $this->statusKeysForProject($project);

        $key = $base;
        for ($i = 2; in_array($key, $taken, true); $i++) {
            $key = $base . '_' . $i;
        }

        // Default: right after the last custom status (or after Review), never past Done.
        $defaultSort = min(
            Task::CUSTOM_SORT_MAX,
            max(Task::BUILTIN_SORT[Task::STATUS_REVIEW], (int) TaskStatus::where('project_id', $project->id)->max('sort_order')) + 1,
        );

        return TaskStatus::create([
            'project_id' => $project->id,
            'key'        => $key,
            'label'      => $data['label'],
            'color'      => filled($data['color'] ?? '') ? $data['color'] : null,
            'sort_order' => filled($data['sort_order'] ?? '') ? (int) $data['sort_order'] : $defaultSort,
        ]);
    }

    /** Updates label / color / sort number. The status key never changes, so tasks stay attached. */
    public function updateStatus(TaskStatus $status, array $data): TaskStatus
    {
        $updates = [];
        if (filled($data['label'] ?? '')) {
            $updates['label'] = $data['label'];
        }
        if (array_key_exists('color', $data)) {
            $updates['color'] = filled($data['color']) ? $data['color'] : null;
        }
        if (filled($data['sort_order'] ?? '')) {
            $updates['sort_order'] = (int) $data['sort_order'];
        }

        $status->update($updates);

        return $status->fresh();
    }

    /** Deletes a custom status; its tasks fall back to "todo". */
    public function deleteStatus(TaskStatus $status): void
    {
        DB::transaction(function () use ($status) {
            Task::where('project_id', $status->project_id)
                ->where('status', $status->key)
                ->update(['status' => Task::STATUS_TODO, 'completed_at' => null]);

            $status->delete();
        });
    }

    public function create(Project $project, array $data): Task
    {
        return DB::transaction(function () use ($project, $data) {
            $task = Task::create([
                'project_id'      => $project->id,
                'milestone_id'    => filled($data['milestone_id'] ?? '') ? (int) $data['milestone_id'] : null,
                'title'           => $data['title'],
                'description'     => filled($data['description'] ?? '') ? $data['description'] : null,
                'status'          => $data['status'] ?? Task::STATUS_TODO,
                'priority'        => $data['priority'] ?? Task::PRIORITY_NORMAL,
                'due_date'        => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
                'sort_order'      => (int) ($data['sort_order'] ?? 0),
                'estimated_hours' => filled($data['estimated_hours'] ?? '') ? $data['estimated_hours'] : null,
            ]);

            $this->notifyAssigned($task, $task->syncAssignees(self::assigneeIdsFrom($data) ?? []));

            return $task->fresh(['assignees', 'milestone', 'project']);
        });
    }

    /** My Projects: a task the user adds for themselves — always assigned to them only, no milestone. */
    public function createForSelf(Project $project, array $data, int $userId): Task
    {
        unset($data['milestone_id'], $data['assigned_to']);

        return $this->create($project, ['assignee_ids' => [$userId]] + $data);
    }

    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $task->update([
                'milestone_id'    => filled($data['milestone_id'] ?? '') ? (int) $data['milestone_id'] : null,
                'title'           => $data['title'],
                'description'     => filled($data['description'] ?? '') ? $data['description'] : null,
                'status'          => $data['status'] ?? $task->status,
                'priority'        => $data['priority'] ?? $task->priority,
                'due_date'        => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
                'estimated_hours' => filled($data['estimated_hours'] ?? '') ? $data['estimated_hours'] : null,
            ]);

            $ids = self::assigneeIdsFrom($data);
            if ($ids !== null) {
                $this->notifyAssigned($task, $task->syncAssignees($ids));
            }

            return $task->fresh();
        });
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

    /** Moves a task to another milestone of the same project (null = no milestone). */
    public function moveMilestone(Task $task, ?int $milestoneId): Task
    {
        $task->update(['milestone_id' => $milestoneId]);

        return $task->fresh(['assignees', 'milestone', 'project']);
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

    /**
     * Adds a comment, or a reply when $parentId is given. Threads are one level deep:
     * replying to a reply attaches to that reply's top-level comment.
     */
    public function addComment(Task $task, int $userId, string $body, ?int $parentId = null): TaskComment
    {
        if ($parentId) {
            $parent = TaskComment::where('task_id', $task->id)->find($parentId);
            abort_unless($parent, 422, 'The comment you are replying to no longer exists.');
            $parentId = $parent->parent_id ?: $parent->id;
        }

        return TaskComment::create([
            'task_id'   => $task->id,
            'user_id'   => $userId,
            'parent_id' => $parentId,
            'body'      => $body,
        ])->load('user');
    }

    /** API shape of a comment; `initial` is the first letter of the author's first name (avatar). */
    public function fmtComment(TaskComment $c): array
    {
        $name = $c->user?->name ?? 'System';

        return [
            'id'         => $c->id,
            'parent_id'  => $c->parent_id,
            'user_id'    => $c->user_id,
            'user'       => $name,
            'first_name' => Str::before(trim($name), ' ') ?: $name,
            'initial'    => Str::upper(Str::substr(trim($name), 0, 1)) ?: '?',
            'is_mine'    => $c->user_id !== null && (int) $c->user_id === (int) auth()->id(),
            'body'       => $c->body,
            'created_at' => $c->created_at?->toDateTimeString(),
        ];
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
        // Attachment rows cascade with the task; their stored files have to go explicitly.
        $task->delete();
        app(TaskAttachmentService::class)->deleteAllForTask($task);
    }

    public function taskForBusiness(Business $business, Task $task): ?Task
    {
        return (int) $task->project->business_id === (int) $business->id ? $task : null;
    }
}
