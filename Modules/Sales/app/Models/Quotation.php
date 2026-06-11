<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Customer;

class Quotation extends Model
{
    const STATUS_DRAFT    = 'draft';
    const STATUS_SENT     = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED  = 'expired';

    protected $fillable = [
        'business_id',
        'branch_id',
        'quote_number',
        'customer_id',
        'reference',
        'quote_date',
        'expiry_date',
        'status',
        'notes',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'quote_date'      => 'date',
        'expiry_date'     => 'date',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'total'           => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SENT]);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast()
            && !in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_EXPIRED]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT    => 'Draft',
            self::STATUS_SENT     => 'Sent',
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_EXPIRED  => 'Expired',
            default               => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT    => '#64748b',
            self::STATUS_SENT     => '#3b82f6',
            self::STATUS_ACCEPTED => '#10b981',
            self::STATUS_REJECTED => '#ef4444',
            self::STATUS_EXPIRED  => '#f59e0b',
            default               => '#64748b',
        };
    }
}
