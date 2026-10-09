<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskComment extends Model
{
    protected $table = 'pm_task_comments';

    protected $fillable = [
        'task_id',
        'user_id',
        'parent_id',
        'body',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** The comment this one replies to (null for a top-level comment). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    /** Files posted with this comment (images, PDFs…). */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class, 'comment_id')->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
