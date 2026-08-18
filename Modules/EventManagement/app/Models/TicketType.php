<?php

namespace Modules\EventManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    protected $table = 'em_ticket_types';

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'capacity',
    ];

    protected $casts = [
        'price'    => 'decimal:2',
        'capacity' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function soldCount(): int
    {
        return $this->attendees()->count();
    }

    public function available(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->soldCount());
    }
}
