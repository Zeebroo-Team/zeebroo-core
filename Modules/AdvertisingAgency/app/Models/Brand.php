<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $table = 'bm_brands';

    protected $fillable = [
        'business_id',
        'name',
        'short_code',
        'email',
        'phone',
        'company_name',
        'contact_person',
        'address',
    ];

    /** Ensure short_code is always stored as uppercase. */
    public function setShortCodeAttribute(string $value): void
    {
        $this->attributes['short_code'] = strtoupper($value);
    }
}
