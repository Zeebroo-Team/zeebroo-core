<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Milestone extends Model
{
    protected $table = 'pm_milestones';

    const STATUS_PENDING   = 'pending';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'start_date',
        'due_date',
        'status',
        'sort_order',
        'completed_at',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'due_date'     => 'date',
        'sort_order'   => 'integer',
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** completed | overdue | active | upcoming | open (no dates) — same rules as the desktop app. */
    public function timelineState(): string
    {
        if ($this->isCompleted()) {
            return 'completed';
        }

        $today = now()->startOfDay();

        return match (true) {
            $this->due_date !== null && $this->due_date->lt($today)   => 'overdue',
            $this->start_date !== null && $this->start_date->gt($today) => 'upcoming',
            $this->start_date !== null || $this->due_date !== null    => 'active',
            default                                                   => 'open',
        };
    }

    /** "02 Oct 2026 → 15 Oct 2026 · 14 days", or "No time period". */
    public function periodLabel(): string
    {
        if (!$this->start_date && !$this->due_date) {
            return 'No time period';
        }

        $label = ($this->start_date?->format('d M Y') ?? '…') . ' → ' . ($this->due_date?->format('d M Y') ?? '…');

        if ($this->start_date && $this->due_date) {
            $days   = (int) $this->start_date->diffInDays($this->due_date) + 1;
            $label .= ' · ' . $days . ' day' . ($days === 1 ? '' : 's');
        }

        return $label;
    }
}
