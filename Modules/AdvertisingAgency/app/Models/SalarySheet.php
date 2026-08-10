<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySheet extends Model
{
    protected $table = 'bm_salary_sheets';

    protected $fillable = [
        'business_id', 'job_id', 'sheet_ref', 'title',
        'location', 'date_from', 'date_to', 'custom_dates', 'status', 'notes',
        'default_coordinator_fee',
    ];

    protected $casts = [
        'date_from'    => 'date:Y-m-d',
        'date_to'      => 'date:Y-m-d',
        'custom_dates' => 'array',
    ];

    public function job(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function rows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalarySheetRow::class, 'sheet_id')->orderBy('sort_order');
    }

    public function allowances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalarySheetAllowance::class, 'sheet_id')->orderBy('sort_order');
    }

    public function positionRules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalarySheetPositionRule::class, 'sheet_id')->orderBy('sort_order');
    }
}
