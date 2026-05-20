<?php

declare(strict_types=1);

namespace Modules\HRManagement\Payroll\RegionalTemplates;

use Modules\Business\Models\Business;
use Modules\HRManagement\Models\PayrollRule;
use Modules\HRManagement\Models\PayrollRuleSet;
use Modules\Settings\Services\SettingsService;

final class IndianPayrollRegionalTemplate implements PayrollRegionalTemplateContract
{
    public const KEY = 'indian_employee_standard';

    public const RULE_SET_NAME = 'India payroll starter';

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
            'title' => __('Indian payroll template'),
            'description' => __('Illustrative India payroll scaffolding: PF, ESI, professional tax slab, indicative monthly TDS-style slabs on taxable pay, plus overtime helper. Tune rates and slabs for your state and FY rules before production use.'),
            'highlights' => [
                __('PF employee 12% & employer 12% (EPCA-style tracking)'),
                __('ESI employee 0.75% & employer 3.25% on gross earnings'),
                __('Professional tax lump slab + illustrative TDS slabs (INR)'),
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
            $ruleSet = PayrollRuleSet::query()->create([
                'business_id' => $business->id,
                'name' => self::RULE_SET_NAME,
                'currency' => (string) (get_settings('business.currency', 'INR', $business) ?: 'INR'),
                'effective_from' => now()->toDateString(),
                'is_default' => false,
                'is_active' => true,
                'notes' => 'Starter rule set for Indian statutory-style components (PF, ESI, PT, TDS).',
            ]);
        }

        $ruleSet->forceFill([
            'currency' => (string) (get_settings('business.currency', 'INR', $business) ?: 'INR'),
            'effective_from' => now()->toDateString(),
            'is_active' => true,
            'notes' => 'Template: Indian payroll template',
        ])->save();

        $this->installHelper->makeRuleSetSoleDefault($business, $ruleSet);

        $ruleSet->rules()->delete();
        $this->attachStandardRules($ruleSet);

        $this->settings->setMany($business, [
            'hr.payroll.template' => self::KEY,
            'hr.payroll.build_mode' => 'default',
            'hr.payroll.cycle.default_name' => 'Monthly Payroll',
            'hr.payroll.cycle.default_working_days' => 26,
            'hr.payroll.statutory.epf.employee.percent' => 12,
            'hr.payroll.statutory.epf.employer.percent' => 12,
            'hr.payroll.statutory.etf.employer.percent' => 0,
            'hr.payroll.statutory.apit.enabled' => false,
            'hr.payroll.statutory.tds.enabled' => true,
            'hr.payroll.statutory.india.pf_employee.percent' => 12,
            'hr.payroll.statutory.india.pf_employer.percent' => 12,
            'hr.payroll.statutory.india.esi_employee.percent' => 0.75,
            'hr.payroll.statutory.india.esi_employer.percent' => 3.25,
        ]);

        return (string) __('Indian payroll template applied. PF, ESI, professional tax, TDS-style slabs, and payroll defaults are configured.');
    }

    private function attachStandardRules(PayrollRuleSet $ruleSet): void
    {
        $ruleSet->rules()->createMany([
            [
                'code' => 'PF_EMPLOYEE',
                'name' => 'PF employee contribution (12%)',
                'component_type' => PayrollRule::TYPE_STATUTORY,
                'calculation_mode' => PayrollRule::MODE_PERCENTAGE,
                'sort_order' => 10,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => ['base_field' => 'basic_salary', 'percent' => 12],
            ],
            [
                'code' => 'PF_EMPLOYER',
                'name' => 'PF employer contribution (12%, tracking)',
                'component_type' => PayrollRule::TYPE_EMPLOYER_TRACKING,
                'calculation_mode' => PayrollRule::MODE_PERCENTAGE,
                'sort_order' => 20,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => ['base_field' => 'basic_salary', 'percent' => 12],
            ],
            [
                'code' => 'ESI_EMPLOYEE',
                'name' => 'ESI employee contribution (0.75%)',
                'component_type' => PayrollRule::TYPE_STATUTORY,
                'calculation_mode' => PayrollRule::MODE_PERCENTAGE,
                'sort_order' => 30,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => ['base_field' => 'gross_earnings', 'percent' => 0.75],
            ],
            [
                'code' => 'ESI_EMPLOYER',
                'name' => 'ESI employer contribution (3.25%, tracking)',
                'component_type' => PayrollRule::TYPE_EMPLOYER_TRACKING,
                'calculation_mode' => PayrollRule::MODE_PERCENTAGE,
                'sort_order' => 40,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => ['base_field' => 'gross_earnings', 'percent' => 3.25],
            ],
            [
                'code' => 'PT_IN',
                'name' => 'Professional tax (illustrative slab)',
                'component_type' => PayrollRule::TYPE_DEDUCTION,
                'calculation_mode' => PayrollRule::MODE_SLAB,
                'sort_order' => 50,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => [
                    'input_field' => 'gross_earnings',
                    'slabs' => [
                        ['from' => 0, 'to' => 10000, 'percent' => 0, 'fixed' => 0],
                        ['from' => 10001, 'to' => null, 'percent' => 0, 'fixed' => 200],
                    ],
                ],
            ],
            [
                'code' => 'TDS_IN',
                'name' => 'TDS (illustrative monthly slabs, INR)',
                'component_type' => PayrollRule::TYPE_DEDUCTION,
                'calculation_mode' => PayrollRule::MODE_SLAB,
                'sort_order' => 60,
                'is_taxable' => false,
                'is_statutory' => true,
                'is_active' => true,
                'config_json' => [
                    'input_field' => 'taxable_earnings',
                    'slabs' => [
                        ['from' => 0, 'to' => 40000, 'percent' => 0],
                        ['from' => 40000, 'to' => 80000, 'percent' => 5],
                        ['from' => 80000, 'to' => 120000, 'percent' => 10],
                        ['from' => 120000, 'to' => 200000, 'percent' => 15],
                        ['from' => 200000, 'to' => null, 'percent' => 20],
                    ],
                ],
            ],
            [
                'code' => 'OT_RATE_FORMULA_IN',
                'name' => 'Overtime rate helper (double rate reference)',
                'component_type' => PayrollRule::TYPE_OVERTIME,
                'calculation_mode' => PayrollRule::MODE_FORMULA,
                'sort_order' => 70,
                'is_taxable' => true,
                'is_statutory' => false,
                'is_active' => false,
                'config_json' => ['formula' => '(basic_salary/26/8)*2'],
            ],
        ]);
    }
}
