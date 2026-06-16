<?php

namespace Modules\Service\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Customer;

class ServiceRequest extends Model
{
    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'business_id',
        'service_item_id',
        'customer_id',
        'request_number',
        'title',
        'reference',
        'notes',
        'scheduled_at',
        'status',
        'total_price',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'total_price'  => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(ServiceItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING     => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED   => 'Completed',
            self::STATUS_CANCELLED   => 'Cancelled',
            default                  => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING     => '#f59e0b',
            self::STATUS_IN_PROGRESS => '#3b82f6',
            self::STATUS_COMPLETED   => '#10b981',
            self::STATUS_CANCELLED   => '#94a3b8',
            default                  => '#64748b',
        };
    }
}
