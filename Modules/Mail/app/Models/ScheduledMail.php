<?php

namespace Modules\Mail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class ScheduledMail extends Model
{
    protected $table = 'mail_scheduled';

    const STATUS_PENDING   = 'pending';
    const STATUS_SENDING   = 'sending';
    const STATUS_SENT      = 'sent';
    const STATUS_FAILED    = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_id',
        'to_address',
        'subject',
        'body',
        'scheduled_at',
        'status',
        'sent_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at'      => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
