<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class Reservation extends Model
{
    protected $table = 'restaurant_reservations';

    protected $fillable = [
        'business_id',
        'table_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'party_size',
        'reserved_at',
        'duration_minutes',
        'status',
        'notes',
    ];

    protected $casts = [
        'reserved_at'      => 'datetime',
        'party_size'       => 'integer',
        'duration_minutes' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending'   => '#f59e0b',
            'confirmed' => '#3b82f6',
            'seated'    => '#8b5cf6',
            'completed' => '#22c55e',
            'cancelled' => '#ef4444',
            default     => '#9ca3af',
        };
    }
}
