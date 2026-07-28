<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Business\Models\Business;

class Project extends Model
{
    protected $table = 'pm_projects';

    const STATUS_ACTIVE    = 'active';
    const STATUS_ON_HOLD   = 'on_hold';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ARCHIVED  = 'archived';

    const PRIORITY_LOW    = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH   = 'high';

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'status',
        'priority',
        'color',
        'start_date',
        'due_date',
        'budget',
        'client_name',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date'   => 'date',
        'budget'     => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function assignedUsers(): HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\User::class,
            Task::class,
            'project_id',
            'id',
            'id',
            'assigned_to'
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * @return array{todo:int,in_progress:int,review:int,done:int,total:int}
     */
    public function taskStats(): array
    {
        $counts = $this->tasks()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'todo'        => (int) ($counts[Task::STATUS_TODO] ?? 0),
            'in_progress' => (int) ($counts[Task::STATUS_IN_PROGRESS] ?? 0),
            'review'      => (int) ($counts[Task::STATUS_REVIEW] ?? 0),
            'done'        => (int) ($counts[Task::STATUS_DONE] ?? 0),
            'total'       => (int) $counts->sum(),
        ];
    }
}
