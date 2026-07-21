<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Business\Models\Business;

class PosCashWithdrawal extends Model
{
    protected $table = 'pos_cash_withdrawals';

    protected $fillable = [
        'business_id',
        'register_date',
        'amount',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'register_date' => 'date',
            'amount'        => 'float',
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
