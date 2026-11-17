<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One message (the first one or a reply) in an inbox thread. */
class InboxMessage extends Model
{
    protected $table = 'pm_inbox_messages';

    protected $fillable = [
        'thread_id',
        'user_id',
        'body',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(InboxThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(InboxAttachment::class, 'message_id')->orderBy('id');
    }
}
