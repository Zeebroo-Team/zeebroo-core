<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyTask extends Model
{
    protected $table = 'aa_tasks';

    public const STATUS_TODO        = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_REVIEW      = 'review';
    public const STATUS_DONE        = 'done';

    public const STATUSES = [
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_REVIEW,
        self::STATUS_DONE,
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = [
        'campaign_id',
        'title',
        'description',
        'priority',
        'status',
        'assigned_to',
        'due_at',
        'completed_at',
    ];

    protected $casts = [
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && !$this->isDone()
            && $this->due_at->isPast();
    }

    public function statusLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }
}
