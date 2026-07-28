<?php

namespace Modules\AutomationEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class AutomationNotification extends Model
{
    protected $table = 'automation_notifications';

    protected $fillable = [
        'business_id', 'flow_id', 'title', 'message', 'payload', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AutomationFlow::class, 'flow_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
