<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file (PDF, image, document…) attached to a task — stored on the private "local" disk. */
class TaskAttachment extends Model
{
    protected $table = 'pm_task_attachments';

    const DISK = 'local';

    protected $fillable = [
        'task_id',
        'user_id',
        'comment_id',
        'original_name',
        'stored_path',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** The comment this file was posted with (null for a task-level attachment). */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
