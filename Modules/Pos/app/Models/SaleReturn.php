<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;

class SaleReturn extends Model
{
    public const REFUND_CASH   = 'cash';
    public const REFUND_CREDIT = 'credit';
    public const REFUND_NONE   = 'none';

    public const REASONS = [
        'low_quality'        => 'Low quality products',
        'expired'            => 'Date expired',
        'requirement_mismatch' => 'Customer requirement not match',
        'wrong_item'         => 'Wrong item delivered',
        'damaged'            => 'Damaged / defective',
        'changed_mind'       => 'Changed mind',
        'overstock'          => 'Overstock / excess quantity',
        'other'              => 'Other',
    ];

    protected $table = 'pos_sale_returns';

    protected $fillable = [
        'business_id',
        'pos_sale_id',
        'user_id',
        'credit_account_id',
        'return_number',
        'refund_method',
        'refund_reason',
        'total',
        'notes',
        'returned_at',
    ];

    protected $casts = [
        'returned_at' => 'datetime',
        'total'       => 'float',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'pos_sale_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class, 'pos_sale_return_id');
    }

    public function reasonLabel(): string
    {
        if (blank($this->refund_reason)) {
            return '';
        }

        return self::REASONS[$this->refund_reason] ?? ucfirst((string) $this->refund_reason);
    }

    public function refundMethodLabel(): string
    {
        return match ($this->refund_method) {
            self::REFUND_CASH   => 'Cash refund',
            self::REFUND_CREDIT => 'Credit account',
            default             => 'No refund',
        };
    }
}
