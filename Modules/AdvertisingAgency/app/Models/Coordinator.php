<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;

class Coordinator extends Model
{
    protected $table = 'bm_coordinators';

    protected $fillable = [
        'business_id',
        'name',
        'nic',
        'phone',
        'status',
        'bank_name',
        'bank_branch',
        'bank_account',
    ];
}
