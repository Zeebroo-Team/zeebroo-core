<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $table = 'pm_tasks';

    const STATUS_TODO        = 'todo';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_REVIEW      = 'review';
    const STATUS_DONE        = 'done';

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

    const PRIORITY_LOW    = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH   = 'high';

    protected $fillable = [
        'project_id',
        'milestone_id',
        'title',
        'description',
        'status',
        'priority',
        'assigned_to',
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
    public function syncAssignees(array $userIds): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        $this->assignees()->sync($userIds);
        $this->update(['assigned_to' => $userIds[0] ?? null]);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('id');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class)->orderByDesc('logged_at');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isOverdue(): bool
    {
        // due_date is a midnight date, so isPast() would flag tasks due *today* — compare to today instead.
        return !$this->isCompleted() && $this->due_date && $this->due_date->lt(today());
    }

    public function totalLoggedMinutes(): int
    {
        return (int) $this->timeLogs()->sum('minutes');
    }
}
