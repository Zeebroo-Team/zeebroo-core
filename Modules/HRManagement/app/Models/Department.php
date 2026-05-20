<?php

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Account\Models\Bill;
use Modules\Business\Models\Business;

class Department extends Model
{
    protected $table = 'hr_departments';

    protected $fillable = [
        'name',
        'salary_range_min',
        'salary_range_max',
        'head_employee_id',
        'co_head_employee_id',
    ];

    protected function casts(): array
    {
        return [
            'salary_range_min' => 'decimal:2',
            'salary_range_max' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function headEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function coHeadEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'co_head_employee_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'department_id');
    }
}
