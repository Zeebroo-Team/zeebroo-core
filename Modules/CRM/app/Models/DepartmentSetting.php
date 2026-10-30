<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Department;

class DepartmentSetting extends Model
{
    protected $table = 'crm_department_settings';

    /** Follow-up reminders (days after a quotation is sent) when none are saved. */
    const DEFAULT_FOLLOWUP_DAYS = [2, 5];

    protected $fillable = [
        'business_id',
        'department_id',
        'is_marketing_intake',
        'discount_approval_limit_percent',
        'followup_days',
    ];

    protected function casts(): array
    {
        return [
            'is_marketing_intake'             => 'boolean',
            'discount_approval_limit_percent' => 'decimal:2',
            'followup_days'                   => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return int[] */
    public function followupDays(): array
    {
        return $this->followup_days ?: self::DEFAULT_FOLLOWUP_DAYS;
    }
}
