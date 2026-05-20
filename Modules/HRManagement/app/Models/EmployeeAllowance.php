<?php

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAllowance extends Model
{
    protected $table = 'hr_employee_allowances';

    protected $fillable = [
        'allowance_type_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function allowanceType(): BelongsTo
    {
        return $this->belongsTo(AllowanceType::class, 'allowance_type_id');
    }
}
