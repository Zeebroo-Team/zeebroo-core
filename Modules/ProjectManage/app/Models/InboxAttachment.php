<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file attached to an inbox message — stored on the private "local" disk. */
class InboxAttachment extends Model
{
    protected $table = 'pm_inbox_attachments';

    const DISK = 'local';

    protected $fillable = [
        'message_id',
        'user_id',
        'original_name',
        'stored_path',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(InboxMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
