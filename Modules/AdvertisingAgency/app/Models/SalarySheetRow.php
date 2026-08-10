<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySheetRow extends Model
{
    protected $table = 'bm_salary_sheet_rows';

    protected $fillable = [
        'sheet_id', 'item_number', 'location', 'position',
        'promoter_id', 'promoter_name',
        'bank_name', 'bank_branch', 'bank_account',
        'daily_rate', 'transport_allowance', 'expenses', 'hold_amount',
        'total_days', 'attendance_amount', 'base_amount', 'net_amount',
        'coordinator_id', 'coordinator_name', 'coordination_fee',
        'coordinator_bank_details', 'sort_order',
    ];

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalarySheetAttendance::class, 'row_id');
    }
}
