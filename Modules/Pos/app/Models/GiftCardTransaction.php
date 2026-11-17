<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardTransaction extends Model
{
    public const TYPE_ISSUE  = 'issue';
    public const TYPE_REDEEM = 'redeem';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUST = 'adjust';

    protected $table = 'pos_gift_card_transactions';

    protected $fillable = [
        'pos_gift_card_id',
        'pos_sale_id',
        'type',
        'amount',
        'balance_after',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class, 'pos_gift_card_id');
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
