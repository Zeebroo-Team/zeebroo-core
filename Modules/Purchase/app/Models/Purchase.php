<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class Purchase extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ORDERED = 'ordered';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_CHEQUE = 'cheque';

    public const PAYMENT_CREDIT = 'credit';

    protected $fillable = [
        'business_id',
        'po_number',
        'supplier_id',
        'reference',
        'purchase_date',
        'expected_delivery_date',
        'status',
        'notes',
        'subtotal',
        'total',
        'stock_applied',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'expected_delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'stock_applied' => 'boolean',
        ];
    }

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
        return $this->hasMany(PurchaseItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function goodsReceiveNotes(): HasMany
    {
        return $this->hasMany(GoodsReceiveNote::class);
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOrdered(): bool
    {
        return $this->status === self::STATUS_ORDERED;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_RECEIVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

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
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ORDERED => 'Ordered',
            self::STATUS_PARTIALLY_RECEIVED => 'Partially received',
            self::STATUS_RECEIVED => 'Received',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    /** @return array<string, string> */
    public static function paymentMethods(): array
    {
        return [
            self::PAYMENT_CASH => 'Cash',
            self::PAYMENT_CHEQUE => 'Cheque',
            self::PAYMENT_CREDIT => 'Credit',
        ];
    }

    public function paymentMethodLabel(): ?string
    {
        if (!filled($this->payment_method)) {
            return null;
        }

        return self::paymentMethods()[$this->payment_method] ?? ucfirst((string) $this->payment_method);
    }
}
