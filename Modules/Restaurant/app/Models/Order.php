<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;

class Order extends Model
{
    protected $table = 'restaurant_orders';

    protected $fillable = [
        'business_id',
        'table_id',
        'sale_id',
        'order_number',
        'order_type',
        'status',
        'customer_name',
        'customer_phone',
        'notes',
        'subtotal',
        'discount_amount',
        'total',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total'           => 'decimal:2',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY     = 'ready';
    public const STATUS_SERVED    = 'served';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending'   => '#f59e0b',
            'preparing' => '#3b82f6',
            'ready'     => '#8b5cf6',
            'served'    => '#22c55e',
            'paid'      => '#6b7280',
            'cancelled' => '#ef4444',
            default     => '#9ca3af',
        };
    }

    public function typeLabel(): string
    {
        return match ($this->order_type) {
            'dine_in'  => 'Dine In',
            'takeaway' => 'Takeaway',
            'delivery' => 'Delivery',
            default    => ucfirst($this->order_type),
        };
    }

    public function canTransitionTo(string $status): bool
    {
        $flow = [
            'pending'   => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
            'ready'     => ['served', 'cancelled'],
            'served'    => ['paid', 'cancelled'],
            'paid'      => [],
            'cancelled' => [],
        ];

        return in_array($status, $flow[$this->status] ?? [], true);
    }
}
