<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

final class EmployeeBiometricMapping extends Model
{
    protected $table = 'hr_employee_biometric_mappings';

    protected $fillable = [
        'business_id',
        'employee_id',
        'device_id',
        'device_employee_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
