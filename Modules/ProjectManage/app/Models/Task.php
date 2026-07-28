<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $table = 'pm_tasks';

    const STATUS_TODO        = 'todo';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_REVIEW      = 'review';
    const STATUS_DONE        = 'done';

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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
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
        return !$this->isCompleted() && $this->due_date && $this->due_date->isPast();
    }

    public function totalLoggedMinutes(): int
    {
        return (int) $this->timeLogs()->sum('minutes');
    }
}
