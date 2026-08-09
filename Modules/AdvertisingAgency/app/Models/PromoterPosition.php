<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;

class PromoterPosition extends Model
{
    protected $table = 'bm_promoter_positions';

    protected $fillable = [
        'business_id',
        'name',
        'description',
    ];
}
