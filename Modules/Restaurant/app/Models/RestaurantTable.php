<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class RestaurantTable extends Model
{
    protected $table = 'restaurant_tables';

    protected $fillable = ['business_id', 'name', 'capacity', 'status', 'notes', 'pos_x', 'pos_y'];

    protected $casts = [
        'capacity' => 'integer',
        'pos_x'    => 'integer',
        'pos_y'    => 'integer',
    ];

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_OCCUPIED  = 'occupied';
    public const STATUS_RESERVED  = 'reserved';
    public const STATUS_INACTIVE  = 'inactive';

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'table_id');
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'available' => '#22c55e',
            'occupied'  => '#ef4444',
            'reserved'  => '#f59e0b',
            default     => '#9ca3af',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'available' => 'Available',
            'occupied'  => 'Occupied',
            'reserved'  => 'Reserved',
            default     => 'Inactive',
        };
    }
}
