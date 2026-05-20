<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollItem extends Model
{
    protected $table = 'hr_payroll_items';

    protected $fillable = [
        'payroll_cycle_id',
        'employee_id',
        'status',
        'basic_salary',
        'overtime_amount',
        'gross_earnings',
        'total_deductions',
        'net_pay',
        'inputs_json',
        'snapshot_json',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'gross_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'inputs_json' => 'array',
            'snapshot_json' => 'array',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PayrollCycle::class, 'payroll_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(PayrollItemComponent::class, 'payroll_item_id')->orderBy('id');
    }
}
