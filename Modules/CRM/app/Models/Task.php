<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Business\Models\Business;

class Task extends Model
{
    protected $table = 'crm_tasks';

    const STATUS_PENDING   = 'pending';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'business_id',
        'subject_type',
        'subject_id',
        'title',
        'description',
        'due_at',
        'status',
        'completed_at',
        'assigned_to',
        'created_by',
    ];

    protected $casts = [
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isOverdue(): bool
    {
        return !$this->isCompleted() && $this->due_at && $this->due_at->isPast();
    }
}
