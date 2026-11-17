<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    protected $table = 'pm_tasks';

    const STATUS_TODO        = 'todo';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_REVIEW      = 'review';
    const STATUS_DONE        = 'done';

    /** Tasks whose status was deleted land here ("Not Defined" board column). */
    const STATUS_UNDEFINED   = 'undefined';

    const BUILTIN_STATUSES = [
        self::STATUS_TODO        => 'To Do',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_REVIEW      => 'Review',
        self::STATUS_DONE        => 'Done',
    ];

    /**
     * Fixed board positions of the built-in statuses. Custom statuses take a sort
     * number between them (ties sort after the built-in); Done always stays last.
     */
    const BUILTIN_SORT = [
        self::STATUS_TODO        => 1,
        self::STATUS_IN_PROGRESS => 2,
        self::STATUS_REVIEW      => 3,
        self::STATUS_DONE        => 99,
    ];

    const CUSTOM_SORT_MAX = 98;

    /** Default stage automation of built-ins without an override row: entering Done marks a task complete. */
    const BUILTIN_AUTO_COMPLETION = [
        self::STATUS_DONE => self::COMPLETION_COMPLETE,
    ];

    /** Completion status — separate from the stage (board column) held in `status`. */
    const COMPLETION_INCOMPLETE = 'incomplete';
    const COMPLETION_COMPLETE   = 'complete';
    const COMPLETION_CANCELLED  = 'cancelled';

    const COMPLETION_STATUSES = [
        self::COMPLETION_INCOMPLETE => 'Incomplete',
        self::COMPLETION_COMPLETE   => 'Complete',
        self::COMPLETION_CANCELLED  => 'Cancelled',
    ];

    const PRIORITY_LOW    = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH   = 'high';

    protected $fillable = [
        'project_id',
        'milestone_id',
        'title',
        'description',
        'status',
        'completion_status',
        'priority',
        'assigned_to',
        'created_by',
        'due_date',
        'sort_order',
        'estimated_hours',
        'completed_at',
    ];

    protected $casts = [
        'due_date'        => 'date',
        'completed_at'    => 'datetime',
        'estimated_hours' => 'decimal:1',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /** First assignee only — kept in sync by syncAssignees() for older readers of assigned_to. */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    /** Everyone assigned to the task (all project members). */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'pm_task_assignees')
            ->withTimestamps()
            ->orderBy('pm_task_assignees.id');
    }

    /**
     * Replaces the assignees and mirrors the first one into assigned_to.
     *
     * @param int[] $userIds
     */
    /** @return int[] ids of users newly added as assignees */
    public function syncAssignees(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        $changes = $this->assignees()->sync($userIds);
        $this->update(['assigned_to' => $userIds[0] ?? null]);

        return array_map('intval', $changes['attached'] ?? []);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * The user who may delete the task outright: its creator; on older tasks (no creator
     * recorded) the project creator, then the business owner.
     */
    public function ownerId(): ?int
    {
        $id = $this->created_by ?? $this->project?->created_by ?? $this->project?->business?->user_id;

        return $id !== null ? (int) $id : null;
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->ownerId() === $userId;
    }

    /** The open delete request on this task, if any (one at a time). */
    public function pendingDeleteRequest(): HasOne
    {
        return $this->hasOne(TaskDeleteRequest::class)->where('status', TaskDeleteRequest::STATUS_PENDING)->latestOfMany();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('id');
    }

    /** Task-level files; files posted with a comment hang off that comment instead. */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->whereNull('comment_id')->orderByDesc('id');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class)->orderByDesc('logged_at');
    }

    /** Completed = completion status "complete" — whatever stage (board column) the task sits in. */
    public function isCompleted(): bool
    {
        return $this->completionStatus() === self::COMPLETION_COMPLETE;
    }

    /** Open = still incomplete (complete and cancelled tasks are both closed). */
    public function isOpen(): bool
    {
        return $this->completionStatus() === self::COMPLETION_INCOMPLETE;
    }

    public function completionStatus(): string
    {
        return $this->completion_status ?: self::COMPLETION_INCOMPLETE;
    }

    public function completionLabel(): string
    {
        return self::COMPLETION_STATUSES[$this->completionStatus()] ?? ucfirst($this->completionStatus());
    }

    public function isCancelled(): bool
    {
        return $this->completionStatus() === self::COMPLETION_CANCELLED;
    }

    public function isOverdue(): bool
    {
        // due_date is a midnight date, so isPast() would flag tasks due *today* — compare to today instead.
        // Complete / cancelled tasks are never overdue, whatever stage they sit in.
        return $this->isOpen() && $this->due_date && $this->due_date->lt(today());
    }

    public function totalLoggedMinutes(): int
    {
        return (int) $this->timeLogs()->sum('minutes');
    }
}
