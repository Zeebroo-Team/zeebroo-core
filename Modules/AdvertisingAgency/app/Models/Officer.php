<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Officer extends Model
{
    protected $table = 'bm_officers';

    protected $fillable = [
        'business_id',
        'user_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = ['password'];

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::make($value);
    }
}
