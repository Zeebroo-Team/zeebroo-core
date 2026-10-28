<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

/**
 * A POS bill reverted because it was entered by mistake. The sale is voided and
 * this row keeps why, who did it, and a snapshot of the stock and money undone.
 */
class SaleRevert extends Model
{
    public const REASONS = [
        'wrong_items'        => 'Wrong items billed',
        'wrong_quantity'     => 'Wrong quantity entered',
        'wrong_price'        => 'Wrong price or discount',
        'wrong_customer'     => 'Wrong customer selected',
        'wrong_payment'      => 'Wrong payment method',
        'duplicate'          => 'Duplicate bill',
        'customer_cancelled' => 'Customer cancelled the purchase',
        'test_bill'          => 'Test / training bill',
        'other'              => 'Other',
    ];

    protected $table = 'pos_sale_reverts';

    protected $fillable = [
        'business_id',
        'pos_sale_id',
        'user_id',
        'revert_number',
        'reason',
        'notes',
        'sale_number',
        'customer_name',
        'payment_method',
        'sale_sold_at',
        'sale_total',
        'sale_cost',
        'amount_paid',
        'gift_card_refunded',
        'returned_total',
        'amount_refunded',
        'account_reversed',
        'stock_restored_qty',
        'items',
        'reverted_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_sold_at'       => 'datetime',
            'reverted_at'        => 'datetime',
            'sale_total'         => 'decimal:2',
            'sale_cost'          => 'decimal:2',
            'amount_paid'        => 'decimal:2',
            'gift_card_refunded' => 'decimal:2',
            'returned_total'     => 'decimal:2',
            'amount_refunded'    => 'decimal:2',
            'account_reversed'   => 'decimal:2',
            'stock_restored_qty' => 'decimal:3',
            'items'              => 'array',
        ];
    }

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

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst(str_replace('_', ' ', (string) $this->reason));
    }
}
