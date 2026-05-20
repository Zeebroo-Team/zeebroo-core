<?php

declare(strict_types=1);

namespace Modules\HRManagement\Payroll\RegionalTemplates;

use Modules\Business\Models\Business;
use Modules\HRManagement\Models\PayrollRule;
use Modules\HRManagement\Models\PayrollRuleSet;
use Modules\Settings\Services\SettingsService;

final class SriLankanEmployeeStandardPayrollTemplate implements PayrollRegionalTemplateContract
{
    public const KEY = 'sri_lankan_employee_standard';

    public const RULE_SET_NAME = 'Sri Lanka payroll starter';

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
            'title' => __('Sri Lankan employee standard'),
            'description' => __('Regional defaults for Sri Lanka payroll: statutory components, tax slabs, and starter cycle presets. Applying replaces rules on the linked starter rule set.'),
            'highlights' => [
                __('EPF employee 8%, employer 12%; ETF employer 3%'),
                __('APIT slabs on taxable earnings'),
                __('Overtime rate reference + monthly cycle defaults'),
            ],
        ];
    }

    public function install(Business $business): string
    {
        $ruleSet = PayrollRuleSet::query()
            ->where('business_id', $business->id)
            ->where('name', self::RULE_SET_NAME)
            ->first();

        if (! $ruleSet) {
            $ruleSet = $this->createStarterRuleSet($business);
        }

        $ruleSet->forceFill([
            'currency' => (string) (get_settings('business.currency', 'LKR', $business) ?: 'LKR'),
            'effective_from' => now()->toDateString(),
            'is_active' => true,
            'notes' => 'Template: Sri Lankan employee standard',
        ])->save();

        $this->installHelper->makeRuleSetSoleDefault($business, $ruleSet);

        $ruleSet->rules()->delete();
        $this->attachStandardRules($ruleSet);

        $this->settings->setMany($business, [
            'hr.payroll.template' => self::KEY,
            'hr.payroll.build_mode' => 'default',
            'hr.payroll.cycle.default_name' => 'Monthly Payroll',
            'hr.payroll.cycle.default_working_days' => 26,
            'hr.payroll.statutory.epf.employee.percent' => 8,
            'hr.payroll.statutory.epf.employer.percent' => 12,
            'hr.payroll.statutory.etf.employer.percent' => 3,
            'hr.payroll.statutory.apit.enabled' => true,
            'hr.payroll.statutory.tds.enabled' => false,
        ]);

        return (string) __('Sri Lankan employee standard template applied. EPF, ETF, APIT, and payroll defaults are configured.');
    }

    private function createStarterRuleSet(Business $business): PayrollRuleSet
    {
        return PayrollRuleSet::query()->create([
            'business_id' => $business->id,
            'name' => self::RULE_SET_NAME,
            'currency' => (string) (get_settings('business.currency', 'LKR', $business) ?: 'LKR'),
            'effective_from' => now()->toDateString(),
            'is_default' => true,
            'is_active' => true,
            'notes' => 'Editable starter template with EPF/ETF/APIT style components.',
        ]);
    }

    private function attachStandardRules(PayrollRuleSet $ruleSet): void
    {
        $ruleSet->rules()->createMany([
            [
                'code' => 'EPF_EMPLOYEE',
                'name' => 'EPF employee contribution',
                'component_type' => PayrollRule::TYPE_STATUTORY,
                'calculation_mode' => PayrollRule::MODE_PERCENTAGE,
                'sort_order' => 10,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => ['base_field' => 'basic_salary', 'percent' => 8],
            ],
            [
                'code' => 'ETF_EMPLOYER',
                'name' => 'ETF employer contribution (tracking)',
                'component_type' => PayrollRule::TYPE_EMPLOYER_TRACKING,
                'calculation_mode' => PayrollRule::MODE_PERCENTAGE,
                'sort_order' => 20,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => ['base_field' => 'basic_salary', 'percent' => 3],
            ],
            [
                'code' => 'EPF_EMPLOYER',
                'name' => 'EPF employer contribution (tracking)',
                'component_type' => PayrollRule::TYPE_EMPLOYER_TRACKING,
                'calculation_mode' => PayrollRule::MODE_PERCENTAGE,
                'sort_order' => 25,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => ['base_field' => 'basic_salary', 'percent' => 12],
            ],
            [
                'code' => 'APIT',
                'name' => 'APIT',
                'component_type' => PayrollRule::TYPE_DEDUCTION,
                'calculation_mode' => PayrollRule::MODE_SLAB,
                'sort_order' => 30,
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
                'code' => 'OT_RATE_FORMULA',
                'name' => 'Overtime rate helper (reference)',
                'component_type' => PayrollRule::TYPE_OVERTIME,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 40,
                'is_taxable' => true,
                'is_statutory' => false,
                'is_active' => true,
                'config_json' => ['formula' => '(basic_salary/26/8)*1.5'],
            ],
        ]);
    }
}
