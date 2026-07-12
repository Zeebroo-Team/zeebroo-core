<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Business\Models\Business;

class Activity extends Model
{
    protected $table = 'crm_activities';

    const TYPE_NOTE    = 'note';
    const TYPE_CALL    = 'call';
    const TYPE_EMAIL   = 'email';
    const TYPE_MEETING = 'meeting';

    protected $fillable = [
        'business_id',
        'subject_type',
        'subject_id',
        'type',
        'body',
        'occurred_at',
        'created_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public static function types(): array
    {
        return [
            self::TYPE_NOTE    => 'Note',
            self::TYPE_CALL    => 'Call',
            self::TYPE_EMAIL   => 'Email',
            self::TYPE_MEETING => 'Meeting',
        ];
    }

    public function typeLabel(): string
    {
        return self::types()[$this->type] ?? ucfirst($this->type);
    }

    public function typeIcon(): string
    {
        return match ($this->type) {
            self::TYPE_CALL    => 'fa-phone',
            self::TYPE_EMAIL   => 'fa-envelope',
            self::TYPE_MEETING => 'fa-people-arrows',
            default            => 'fa-note-sticky',
        };
    }
}
