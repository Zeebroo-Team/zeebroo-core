<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Business\Models\Business;

class PosCounter extends Model
{
    protected $table = 'pos_counters';

    protected $fillable = [
        'business_id',
        'branch_id',
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
