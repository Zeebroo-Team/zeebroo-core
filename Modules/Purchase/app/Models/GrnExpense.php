<?php

namespace Modules\Purchase\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class GrnExpense extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'goods_receive_note_id',
        'ledger_transaction_id',
        'amount',
        'payment_method',
        'payment_reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function goodsReceiveNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiveNote::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }
}
