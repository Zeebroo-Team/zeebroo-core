<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user's membership of an inbox thread, with their own read / star / archive / trash state. */
class InboxParticipant extends Model
{
    protected $table = 'pm_inbox_participants';

    protected $fillable = [
        'thread_id',
        'user_id',
        'last_read_message_id',
        'is_starred',
        'archived_at',
        'trashed_at',
    ];

    protected $casts = [
        'last_read_message_id' => 'integer',
        'is_starred'   => 'boolean',
        'archived_at'  => 'datetime',
        'trashed_at'   => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(InboxThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
