<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySheetAttendance extends Model
{
    protected $table = 'bm_salary_sheet_attendances';

    protected $fillable = ['row_id', 'attendance_date', 'attendance_status'];

    protected $casts = ['attendance_date' => 'date:Y-m-d'];
}
