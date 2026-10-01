<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    protected $table = 'pos_coupon_redemptions';

    protected $fillable = [
        'pos_coupon_id',
        'pos_sale_id',
        'discount_amount',
        'reversed_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'reversed_at'     => 'datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'pos_coupon_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'pos_sale_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
