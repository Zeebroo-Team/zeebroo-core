<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;

class ProductRental extends Model
{
    protected $table = 'pos_product_rentals';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'pos_customer_id',
        'pos_sale_id',
        'pos_sale_item_id',
        'daily_rate',
        'quantity',
        'rented_at',
        'due_at',
        'returned_at',
        'late_fee',
        'late_fee_multiplier',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate'           => 'decimal:2',
            'quantity'             => 'decimal:3',
            'rented_at'            => 'date',
            'due_at'               => 'date',
            'returned_at'          => 'date',
            'late_fee'             => 'decimal:2',
            'late_fee_multiplier'  => 'decimal:2',
        ];
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE    => 'Active',
            self::STATUS_OVERDUE   => 'Overdue',
            self::STATUS_RETURNED  => 'Returned',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'pos_customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'pos_sale_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'pos_sale_item_id');
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_OVERDUE], true)
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function effectiveStatus(): string
    {
        return $this->status === self::STATUS_ACTIVE && $this->isOverdue()
            ? self::STATUS_OVERDUE
            : $this->status;
    }
}
