<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;
use Modules\Purchase\Models\Supplier;

class IngredientPurchaseOrder extends Model
{
    public const STATUS_DRAFT              = 'draft';
    public const STATUS_ORDERED            = 'ordered';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED           = 'received';
    public const STATUS_CANCELLED          = 'cancelled';

    protected $table = 'restaurant_ingredient_purchase_orders';

    protected $fillable = [
        'business_id',
        'supplier_id',
        'po_number',
        'purchase_date',
        'expected_delivery_date',
        'status',
        'notes',
        'subtotal',
        'total',
    ];

    protected $casts = [
        'purchase_date'          => 'date',
        'expected_delivery_date' => 'date',
        'subtotal'               => 'decimal:2',
        'total'                  => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(IngredientPurchaseOrderItem::class, 'purchase_order_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function grns(): HasMany
    {
        return $this->hasMany(IngredientGrn::class, 'purchase_order_id');
    }

    public function isDraft(): bool        { return $this->status === self::STATUS_DRAFT; }
    public function isOrdered(): bool      { return $this->status === self::STATUS_ORDERED; }
    public function isReceived(): bool     { return $this->status === self::STATUS_RECEIVED; }
    public function isCancelled(): bool    { return $this->status === self::STATUS_CANCELLED; }
    public function isPartiallyReceived(): bool { return $this->status === self::STATUS_PARTIALLY_RECEIVED; }

    public function isEditable(): bool
    {
        return $this->isDraft() || $this->isOrdered();
    }

    public function canReceiveGoods(): bool
    {
        return !$this->isCancelled() && !$this->isReceived();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT              => 'Draft',
            self::STATUS_ORDERED            => 'Ordered',
            self::STATUS_PARTIALLY_RECEIVED => 'Partially Received',
            self::STATUS_RECEIVED           => 'Received',
            self::STATUS_CANCELLED          => 'Cancelled',
            default                         => ucfirst((string) $this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT              => '#6b7280',
            self::STATUS_ORDERED            => '#2563eb',
            self::STATUS_PARTIALLY_RECEIVED => '#d97706',
            self::STATUS_RECEIVED           => '#16a34a',
            self::STATUS_CANCELLED          => '#dc2626',
            default                         => '#6b7280',
        };
    }
}
