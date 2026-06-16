<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Customer;

class Invoice extends Model
{
    const STATUS_DRAFT     = 'draft';
    const STATUS_SENT      = 'sent';
    const STATUS_PAID      = 'paid';
    const STATUS_OVERDUE   = 'overdue';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_id',
        'branch_id',
        'invoice_number',
        'customer_id',
        'reference',
        'issue_date',
        'due_date',
        'status',
        'share_token',
        'notes',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'issue_date'      => 'date',
        'due_date'        => 'date',
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
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function isPublic(): bool
    {
        return !empty($this->share_token);
    }

    public function shareUrl(): string
    {
        return route('sales.invoices.public', $this->share_token);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SENT]);
    }

    public function isPaymentDue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && !in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => 'Draft',
            self::STATUS_SENT      => 'Sent',
            self::STATUS_PAID      => 'Paid',
            self::STATUS_OVERDUE   => 'Overdue',
            self::STATUS_CANCELLED => 'Cancelled',
            default                => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => '#64748b',
            self::STATUS_SENT      => '#3b82f6',
            self::STATUS_PAID      => '#10b981',
            self::STATUS_OVERDUE   => '#ef4444',
            self::STATUS_CANCELLED => '#94a3b8',
            default                => '#64748b',
        };
    }
}
