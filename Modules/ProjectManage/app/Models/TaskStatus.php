<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user-defined board column (task status) for a single project.
 * Built-in statuses (todo / in_progress / review / done) are not stored here.
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
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
