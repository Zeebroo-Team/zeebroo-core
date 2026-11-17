<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A My Projects inbox conversation between team members (optionally about a project). */
class InboxThread extends Model
{
    protected $table = 'pm_inbox_threads';

    protected $fillable = [
        'business_id',
        'project_id',
        'created_by',
        'subject',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(\Modules\Business\Models\Business::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(InboxParticipant::class, 'thread_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InboxMessage::class, 'thread_id')->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(InboxMessage::class, 'thread_id')->latestOfMany();
    }

    public function attachments(): HasManyThrough
    {
        return $this->hasManyThrough(InboxAttachment::class, InboxMessage::class, 'thread_id', 'message_id');
    }
}
