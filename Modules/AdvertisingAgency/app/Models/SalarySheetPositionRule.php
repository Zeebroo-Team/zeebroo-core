<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySheetPositionRule extends Model
{
    protected $table = 'bm_salary_sheet_position_rules';

    protected $fillable = [
        'sheet_id',
        'position_name',
        'daily_rate',
        'transport_allowance',
        'sort_order',
    ];
}
