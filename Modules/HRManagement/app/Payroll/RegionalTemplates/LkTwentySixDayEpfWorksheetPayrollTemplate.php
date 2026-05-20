<?php

declare(strict_types=1);

namespace Modules\HRManagement\Payroll\RegionalTemplates;

use Modules\Business\Models\Business;
use Modules\HRManagement\Models\PayrollRule;
use Modules\HRManagement\Models\PayrollRuleSet;
use Modules\Settings\Services\SettingsService;

/**
 * Sri Lanka–style monthly worksheet: BASIC (reference), NO PAY (reference), EPF salary @ configured standard days,
 * pro-rated allowances keyed by allowance type name (medical / COLA / attendance / performance),
 * PAYE (APIT slabs), salary advance + stamp duty inputs, EPF 8% on EPF salary,
 * employer EPF 12% + ETF 3% (cost lines), and COST TO COMPANY ({@see PayrollRule::TYPE_EMPLOYER_TRACKING}).
 *
 * Attendance: set “Actual days” per employee on the payroll cycle recompute form (defaults to standard days when left blank).
 * Map allowance types in HR by including those keywords in the allowance name.
 */
final class LkTwentySixDayEpfWorksheetPayrollTemplate implements PayrollRegionalTemplateContract
{
    public const KEY = 'lk_26_day_epf_worksheet';

    public const RULE_SET_NAME = 'LK 26-day EPF worksheet';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly PayrollRegionalTemplateInstallHelper $installHelper,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function card(): array
    {
        return [
            'title' => __('LK 26-day EPF worksheet'),
            'description' => __(
                'Compensation on configured monthly working days: pro-rated EPF salary and matching allowance lines from actual days, NO PAY shown for reference, PAYE (APIT slabs), EPF 8% on EPF salary, advance & stamp inputs, plus employer EPF/ETF and cost-to-company. Allowance types must include “medical”, “COLA” or “cost of living”, “attendance”, or “performance” in the name.'
            ),
            'highlights' => [
                __('Uses hr.payroll.cycle.default_working_days from HR settings and per-employee Actual days'),
                __('Supplementary pay pro-rated × (actual ÷ standard) from employee allowances'),
                __('Columns: BASIC, NO PAY, EPF Salary, allowances, APIT Salary ref, PAYE, advance, stamp, EPF %, totals, employer EPF/ETF, COST'),
            ],
        ];
    }

    public function install(Business $business): string
    {
        $configuredWorkingDays = (float) get_settings('hr.payroll.cycle.default_working_days', 26, $business);
        if ($configuredWorkingDays <= 0) {
            $configuredWorkingDays = 26;
        }

        $ruleSet = PayrollRuleSet::query()
            ->where('business_id', $business->id)
            ->where('name', self::RULE_SET_NAME)
            ->first();

        if (! $ruleSet) {
            $ruleSet = PayrollRuleSet::query()->create([
                'business_id' => $business->id,
                'name' => self::RULE_SET_NAME,
                'currency' => (string) (get_settings('business.currency', 'LKR', $business) ?: 'LKR'),
                'effective_from' => now()->toDateString(),
                'is_default' => false,
                'is_active' => true,
                'notes' => self::RULE_SET_NAME,
            ]);
        }

        $ruleSet->forceFill([
            'currency' => (string) (get_settings('business.currency', 'LKR', $business) ?: 'LKR'),
            'effective_from' => now()->toDateString(),
            'is_active' => true,
            'notes' => 'Template: LK 26-day EPF worksheet',
        ])->save();

        $this->installHelper->makeRuleSetSoleDefault($business, $ruleSet);

        $ruleSet->rules()->delete();
        $this->attachWorksheetRules($ruleSet);

        $this->settings->setMany($business, [
            'hr.payroll.template' => self::KEY,
            'hr.payroll.build_mode' => 'standard_26_epf_sheet',
            'hr.payroll.cycle.default_name' => 'Monthly Payroll',
            // Respect current HR setting instead of forcing 26 on template apply.
            'hr.payroll.cycle.default_working_days' => round($configuredWorkingDays, 2),
            'hr.payroll.statutory.epf.employee.percent' => 8,
            'hr.payroll.statutory.epf.employer.percent' => 12,
            'hr.payroll.statutory.etf.employer.percent' => 3,
            'hr.payroll.statutory.apit.enabled' => true,
            'hr.payroll.statutory.tds.enabled' => false,
        ]);

        return (string) __('LK 26-day EPF worksheet template applied. Use Actual days when recomputing each employee; allowances are mapped by allowance type name keywords.');
    }

    private function attachWorksheetRules(PayrollRuleSet $ruleSet): void
    {
        $ruleSet->rules()->createMany([
            [
                'code' => 'NO_PAY_AMOUNT',
                'name' => 'No pay amount (reference, unpaid fraction of BASIC)',
                'component_type' => PayrollRule::TYPE_INFORMATIONAL,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 10,
                'is_taxable' => false,
                'is_statutory' => false,
                'is_active' => true,
                'config_json' => ['formula' => 'no_pay_amount'],
            ],
            [
                'code' => 'EPF_SALARY',
                'name' => 'EPF salary (basic ÷ standard days × actual days)',
                'component_type' => PayrollRule::TYPE_EARNING,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 20,
                'is_taxable' => true,
                'is_statutory' => false,
                'is_active' => true,
                'config_json' => ['formula' => 'epf_salary'],
            ],
            [
                'code' => 'MEDICAL_ALLOWANCE',
                'name' => 'Medical allowance (pro-rated)',
                'component_type' => PayrollRule::TYPE_EARNING,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 30,
                'is_taxable' => true,
                'is_statutory' => false,
                'is_active' => true,
                'config_json' => ['formula' => 'medical_allowance_monthly*proration_ratio'],
            ],
            [
                'code' => 'COLA_ALLOWANCE',
                'name' => 'Cost of living allowance / COLA (pro-rated)',
                'component_type' => PayrollRule::TYPE_EARNING,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 40,
                'is_taxable' => true,
                'is_statutory' => false,
                'is_active' => true,
                'config_json' => ['formula' => 'cola_allowance_monthly*proration_ratio'],
            ],
            [
                'code' => 'ATTENDANCE_ALLOWANCE',
                'name' => 'Attendance allowance (pro-rated)',
                'component_type' => PayrollRule::TYPE_EARNING,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 50,
                'is_taxable' => true,
                'is_statutory' => false,
                'is_active' => true,
                'config_json' => ['formula' => 'attendance_allowance_monthly*proration_ratio'],
            ],
            [
                'code' => 'PERFORMANCE_ALLOWANCE',
                'name' => 'Performance allowance (pro-rated)',
                'component_type' => PayrollRule::TYPE_EARNING,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 60,
                'is_taxable' => true,
                'is_statutory' => false,
                'is_active' => true,
                'config_json' => ['formula' => 'performance_allowance_monthly*proration_ratio'],
            ],
            [
                'code' => 'APIT_SALARY',
                'name' => 'APIT salary (taxable gross before PAYE deduction — reference)',
                'component_type' => PayrollRule::TYPE_INFORMATIONAL,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 65,
                'is_taxable' => false,
                'is_statutory' => false,
                'is_active' => true,
                'config_json' => ['formula' => 'taxable_earnings'],
            ],
            [
                'code' => 'PAYE_TAX',
                'name' => 'PAYE tax / APIT (slab)',
                'component_type' => PayrollRule::TYPE_DEDUCTION,
                'calculation_mode' => PayrollRule::MODE_SLAB,
                'sort_order' => 70,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => [
                    'input_field' => 'taxable_earnings',
                    'slabs' => [
                        ['from' => 0, 'to' => 100000, 'percent' => 0],
                        ['from' => 100000, 'to' => 141667, 'percent' => 6],
                        ['from' => 141667, 'to' => 183333, 'percent' => 12],
                        ['from' => 183333, 'to' => 225000, 'percent' => 18],
                        ['from' => 225000, 'to' => 266667, 'percent' => 24],
                        ['from' => 266667, 'to' => 308333, 'percent' => 30],
                        ['from' => 308333, 'to' => null, 'percent' => 36],
                    ],
                ],
            ],
            [
                'code' => 'SALARY_ADVANCE',
                'name' => 'Salary advance recovery',
                'component_type' => PayrollRule::TYPE_DEDUCTION,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 75,
                'is_taxable' => false,
                'is_statutory' => false,
                'is_active' => true,
                'config_json' => ['formula' => 'salary_advance'],
            ],
            [
                'code' => 'STAMP_DUTY',
                'name' => 'Stamp duty',
                'component_type' => PayrollRule::TYPE_DEDUCTION,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 76,
                'is_taxable' => false,
                'is_statutory' => false,
                'is_active' => true,
                'config_json' => ['formula' => 'stamp_duty'],
            ],
            [
                'code' => 'EPF_EMPLOYEE',
                'name' => 'EPF employee 8% (on EPF salary)',
                'component_type' => PayrollRule::TYPE_STATUTORY,
                'calculation_mode' => PayrollRule::MODE_PERCENTAGE,
                'sort_order' => 85,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => ['base_field' => 'epf_salary', 'percent' => 8],
            ],
            [
                'code' => 'EPF_EMPLOYER',
                'name' => 'EPF employer 12% (cost)',
                'component_type' => PayrollRule::TYPE_EMPLOYER_TRACKING,
                'calculation_mode' => PayrollRule::MODE_PERCENTAGE,
                'sort_order' => 90,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => ['base_field' => 'epf_salary', 'percent' => 12],
            ],
            [
                'code' => 'ETF_EMPLOYER',
                'name' => 'ETF employer 3% (cost)',
                'component_type' => PayrollRule::TYPE_EMPLOYER_TRACKING,
                'calculation_mode' => PayrollRule::MODE_PERCENTAGE,
                'sort_order' => 95,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => ['base_field' => 'epf_salary', 'percent' => 3],
            ],
            [
                'code' => 'COST_TO_COMPANY',
                'name' => 'Cost to company (cash cost incl. employer EPF & ETF)',
                'component_type' => PayrollRule::TYPE_EMPLOYER_TRACKING,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 100,
                'is_taxable' => false,
                'is_statutory' => false,
                'is_active' => true,
                'config_json' => ['formula' => 'gross_earnings+epf_salary*12/100+epf_salary*3/100'],
            ],
        ]);
    }
}
