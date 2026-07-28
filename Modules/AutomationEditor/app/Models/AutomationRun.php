<?php

namespace Modules\AutomationEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    protected $table = 'automation_runs';

    protected $fillable = [
        'flow_id', 'status', 'trigger_payload', 'result', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'trigger_payload' => 'array',
            'result'          => 'array',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AutomationFlow::class, 'flow_id');
    }
}
