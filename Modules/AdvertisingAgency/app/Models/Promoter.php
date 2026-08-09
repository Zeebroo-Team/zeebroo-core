<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;

class Promoter extends Model
{
    protected $table = 'bm_promoters';

    protected $fillable = [
        'business_id',
        'name',
        'position',
        'nic',
        'phone',
        'bank_name',
        'bank_branch',
        'bank_account',
    ];
}
