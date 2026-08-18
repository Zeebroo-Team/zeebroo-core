<?php

namespace Modules\EventManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class Event extends Model
{
    protected $table = 'em_events';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
    ];

    public const CATEGORIES = [
        'conference',
        'seminar',
        'workshop',
        'gala',
        'sports',
        'concert',
        'exhibition',
        'other',
    ];

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'venue',
        'start_at',
        'end_at',
        'capacity',
        'category',
        'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
        'capacity' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function isUpcoming(): bool
    {
        return $this->start_at !== null && $this->start_at->isFuture();
    }

    public function isPast(): bool
    {
        $end = $this->end_at ?? $this->start_at;
        return $end !== null && $end->isPast();
    }

    public function attendeesCount(): int
    {
        return $this->attendees()->count();
    }
}
