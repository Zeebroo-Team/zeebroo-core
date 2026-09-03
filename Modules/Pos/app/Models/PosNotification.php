<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class PosNotification extends Model
{
    public const TYPE_STOCK_OUT = 'stock_out';

    public const TYPE_STOCK_LOW = 'stock_low';

    public const TYPE_BILL_OVERDUE = 'bill_overdue';

    public const TYPE_LOAN_OVERDUE = 'loan_overdue';

    public const TYPE_RENTAL_OVERDUE = 'rental_overdue';

    public const TYPE_PROPERTY_EXPIRED = 'property_expired';

    public const TYPE_PURCHASE_ORDER_OVERDUE = 'purchase_order_overdue';

    public const TYPE_PURCHASE_ORDER_RECEIVED = 'purchase_order_received';

    public const TYPE_SALE_LARGE = 'sale_large';

    protected $table = 'pos_notifications';

    protected $fillable = [
        'business_id',
        'branch_id',
        'type',
        'title',
        'message',
        'reference_type',
        'reference_id',
        'payload',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeReadOnly(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }
}
