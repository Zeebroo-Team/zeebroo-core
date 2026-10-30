<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An internal message on a lead (staff only). Replies point at a top-level message. */
class LeadMessage extends Model
{
    protected $table = 'crm_lead_messages';

    protected $fillable = [
        'business_id',
        'lead_id',
        'user_id',
        'parent_id',
        'body',
        'mentions',
        'edited_at',
    ];

    protected $casts = [
        'mentions'  => 'array',
        'edited_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Files and images shared in the message. */
    public function attachments(): HasMany
    {
        return $this->hasMany(LeadAttachment::class, 'message_id')->orderBy('id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at')->orderBy('id');
    }
}
