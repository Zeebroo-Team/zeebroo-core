<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Business\Models\Business;

class PosCashOpening extends Model
{
    protected $table = 'pos_cash_openings';

    protected $fillable = [
        'business_id',
        'register_date',
        'opening_float',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'register_date' => 'date',
            'opening_float' => 'float',
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
