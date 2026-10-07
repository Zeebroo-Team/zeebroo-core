<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A non-owner's request to delete a task; the task owner approves (deletes) or rejects it. */
class TaskDeleteRequest extends Model
{
    protected $table = 'pm_task_delete_requests';

    const STATUS_PENDING  = 'pending';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'task_id',
        'requested_by',
        'owner_id',
        'reason',
        'status',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
