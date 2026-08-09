<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySheetAllowance extends Model
{
    protected $table = 'bm_salary_sheet_allowances';

    protected $fillable = [
        'sheet_id',
        'allowance_type',
        'amount',
        'description',
        'sort_order',
    ];
}
