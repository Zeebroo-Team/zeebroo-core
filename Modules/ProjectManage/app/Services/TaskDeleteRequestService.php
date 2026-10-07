<?php

namespace Modules\ProjectManage\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Pos\Services\PosNotificationService;
use Modules\ProjectManage\Models\Task;
use Modules\ProjectManage\Models\TaskDeleteRequest;

/**
 * My Projects task deletion: the task owner deletes directly; any other assignee
 * sends a delete request, which the owner approves (task deleted) or rejects.
 */
class TaskDeleteRequestService
{
    public function __construct(private readonly TaskService $tasks) {}

    public function request(Task $task, int $userId, ?string $reason = null): TaskDeleteRequest
    {
        $ownerId = $task->ownerId();
        abort_if($ownerId === $userId, 422, 'You own this task — delete it directly.');
        abort_unless($ownerId, 422, 'This task has no owner who could approve the request.');
        abort_if($task->pendingDeleteRequest()->exists(), 409, 'A delete request for this task is already waiting for approval.');

        $request = TaskDeleteRequest::create([
            'task_id'      => $task->id,
            'requested_by' => $userId,
            'owner_id'     => $ownerId,
            'reason'       => filled($reason) ? $reason : null,
            'status'       => TaskDeleteRequest::STATUS_PENDING,
        ]);

        $this->notify(fn (PosNotificationService $n) => $n->notifyTaskDeleteRequested($request->fresh(['task.project.business', 'requester'])));

        return $request;
    }

    /** Pending requests waiting for this owner's decision, newest first. */
    public function pendingForOwner(Business $business, int $ownerId): Collection
    {
        return TaskDeleteRequest::query()
            ->where('owner_id', $ownerId)
            ->where('status', TaskDeleteRequest::STATUS_PENDING)
            ->whereHas('task.project', fn ($q) => $q->where('business_id', $business->id))
            ->with(['task.project', 'task.milestone', 'requester'])
            ->orderByDesc('id')
            ->get();
    }

    /** A request addressed to this owner in the business (any status), or null. */
    public function findForOwner(Business $business, int $id, int $ownerId): ?TaskDeleteRequest
    {
        return TaskDeleteRequest::query()
            ->where('owner_id', $ownerId)
            ->whereHas('task.project', fn ($q) => $q->where('business_id', $business->id))
            ->with(['task.project.business', 'task.milestone', 'requester'])
            ->find($id);
    }

    /** Owner approves: the task (and with it every request on it) is deleted; the requester is told. */
    public function approve(TaskDeleteRequest $request): void
    {
        abort_unless($request->isPending(), 409, 'This request has already been handled.');

        $task = $request->task;
        $request->status = 'approved';   // in memory only — the row is removed with the task

        $this->notify(fn (PosNotificationService $n) => $n->notifyTaskDeleteDecided($request, $task, true));
        $this->tasks->delete($task);
    }

    public function reject(TaskDeleteRequest $request): TaskDeleteRequest
    {
        abort_unless($request->isPending(), 409, 'This request has already been handled.');

        $request->update(['status' => TaskDeleteRequest::STATUS_REJECTED, 'decided_at' => now()]);
        $this->notify(fn (PosNotificationService $n) => $n->notifyTaskDeleteDecided($request, $request->task, false));

        return $request;
    }

    public function fmt(TaskDeleteRequest $r): array
    {
        $task = $r->task;

        return [
            'id'             => $r->id,
            'status'         => $r->status,
            'reason'         => $r->reason,
            'task_id'        => $r->task_id,
            'task_title'     => $task?->title,
            'task_status'    => $task?->status,
            'task_priority'  => $task?->priority,
            'task_due_date'  => $task?->due_date?->toDateString(),
            'project_id'     => $task?->project_id,
            'project_name'   => $task?->project?->name,
            'milestone_name' => $task?->milestone?->name,
            'requested_by'   => (int) $r->requested_by,
            'requester_name' => $r->requester?->name ?? 'A teammate',
            'requester_avatar_url' => $r->requester?->avatarUrl(),
            'created_at'     => $r->created_at?->toDateTimeString(),
            'decided_at'     => $r->decided_at?->toDateTimeString(),
        ];
    }

    /** A failed notification must never block the request or the decision itself. */
    private function notify(callable $send): void
    {
        try {
            $send(app(PosNotificationService::class));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
