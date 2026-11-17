<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received later against a credit ("pay later") sale. One receipt that
 * covers several sales is stored as one row per sale sharing a `reference`.
 */
class CreditPayment extends Model
{
    protected $table = 'pos_credit_payments';

    protected $fillable = [
        'business_id',
        'pos_customer_id',
        'pos_sale_id',
        'user_id',
        'reference',
        'amount',
        'payment_method',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'  => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'pos_sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'pos_customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
