<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRule extends Model
{
    protected $table = 'hr_payroll_rules';

    public const TYPE_EARNING = 'earning';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_STATUTORY = 'statutory';

    public const TYPE_OVERTIME = 'overtime';

    /** Shown on payslip/working sheet only; excluded from payroll totals */
    public const TYPE_INFORMATIONAL = 'informational';

    /** Employer-only cost lines; excluded from employee net pay rollup */
    public const TYPE_EMPLOYER_TRACKING = 'employer_tracking';

    public const MODE_FIXED = 'fixed';

    public const MODE_PERCENTAGE = 'percentage';

    public const MODE_SLAB = 'slab';

    public const MODE_FORMULA = 'formula';

    protected $fillable = [
        'rule_set_id',
        'code',
        'name',
        'component_type',
        'calculation_mode',
        'sort_order',
        'is_taxable',
        'is_statutory',
        'is_active',
        'config_json',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_taxable' => 'boolean',
            'is_statutory' => 'boolean',
            'is_active' => 'boolean',
            'config_json' => 'array',
        ];
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(PayrollRuleSet::class, 'rule_set_id');
    }
}
