<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A board column (task status) for a single project: either a user-defined status, or an
 * override of a built-in one (key = todo / in_progress / review / done) holding its renamed
 * label, colour and sort number. A deleted built-in keeps its row with is_hidden = true.
 */
class TaskStatus extends Model
{
    protected $table = 'pm_task_statuses';

    protected $fillable = [
        'project_id',
        'key',
        'label',
        'color',
        'sort_order',
        'is_hidden',
        'auto_completion_status', // completion status a task gets on entering this stage (null = off)
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
    ];

    public function isBuiltin(): bool
    {
        return array_key_exists($this->key, Task::BUILTIN_STATUSES);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
