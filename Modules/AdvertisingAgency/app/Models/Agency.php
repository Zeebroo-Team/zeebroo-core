<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    protected $table = 'bm_agencies';

    protected $fillable = [
        'business_id',
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'status',
    ];
}
