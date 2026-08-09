<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $table = 'bm_jobs';

    protected $fillable = [
        'business_id',
        'name',
        'job_ref',
        'client_brand_id',
        'officer_id',
        'reporter_id',
        'description',
        'status',
        'start_date',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
    ];

    public function clientBrand(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Brand::class, 'client_brand_id');
    }

    public function officer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Officer::class, 'officer_id');
    }

    public function reporter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Reporter::class, 'reporter_id');
    }
}
