<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Modules\HRManagement\Models\PayrollRule;
use Modules\HRManagement\Models\PayrollRuleSet;

final class PayrollComponentBuilderService
{
    public function __construct(
        private readonly PayrollRuleEvaluationService $ruleEvaluation,
    ) {}

    /**
     * @param  array<string, float|int|string|null>  $context
     * @return array{components: array<int, array<string, mixed>>, errors: list<string>}
     */
    public function build(PayrollRuleSet $ruleSet, array $context): array
    {
        $components = [];
        $errors = [];

        $buildMode = (string) ($context['payroll_build_mode'] ?? 'default');
        $isProrated26Sheet = $buildMode === 'standard_26_epf_sheet';

        $basicSalary = round((float) ($context['basic_salary'] ?? 0), 2);
        $overtimeHours = round(max(0, (float) ($context['overtime_hours'] ?? 0)), 2);
        $overtimeRate = round(max(0, (float) ($context['overtime_rate'] ?? 0)), 2);
        $overtimeAmount = round($overtimeHours * $overtimeRate, 2);

        $basicMeta = ['system' => true];
        if ($isProrated26Sheet) {
            $basicMeta['exclude_from_payroll_totals'] = true;
            $basicMeta['worksheet_basic_reference'] = true;
        }

        $components[] = [
            'code' => 'BASIC_SALARY',
            'name' => 'Basic salary',
            'component_type' => PayrollRule::TYPE_EARNING,
            'quantity' => 1,
            'rate' => $basicSalary,
            'amount' => $basicSalary,
            'rule_id' => null,
            'meta_json' => $basicMeta,
        ];

        if ($overtimeAmount > 0) {
            $components[] = [
                'code' => 'OVERTIME',
                'name' => 'Overtime',
                'component_type' => PayrollRule::TYPE_OVERTIME,
                'quantity' => $overtimeHours,
                'rate' => $overtimeRate,
                'amount' => $overtimeAmount,
                'rule_id' => null,
                'meta_json' => ['system' => true],
            ];
        }

        $ctx = $context;

        $ctx['gross_earnings'] = $basicSalary + $overtimeAmount;
        $ctx['taxable_earnings'] = $basicSalary + $overtimeAmount;
        if ($isProrated26Sheet) {
            $ctx['gross_earnings'] = $overtimeAmount;
            $ctx['taxable_earnings'] = $overtimeAmount;
        }
        $ctx['total_deductions'] = 0;

        $rules = $ruleSet->rules()->where('is_active', true)->get();
        foreach ($rules as $rule) {
            $eval = $this->ruleEvaluation->evaluate($rule, $ctx);
            $amount = round((float) $eval['amount'], 2);
            if ($amount === 0.0 && $eval['errors'] === []) {
                continue;
            }

            $components[] = [
                'code' => $rule->code,
                'name' => $rule->name,
                'component_type' => $rule->component_type,
                'quantity' => $eval['quantity'],
                'rate' => $eval['rate'],
                'amount' => $amount,
                'rule_id' => $rule->id,
                'meta_json' => $eval['meta'],
            ];

            if (
                $rule->component_type === PayrollRule::TYPE_INFORMATIONAL
                || $rule->component_type === PayrollRule::TYPE_EMPLOYER_TRACKING
            ) {
                // Visual / employer cost tracking only — do not mutate payroll rollups.
            } elseif ($rule->component_type === PayrollRule::TYPE_EARNING || $rule->component_type === PayrollRule::TYPE_OVERTIME) {
                $ctx['gross_earnings'] = round((float) $ctx['gross_earnings'] + $amount, 2);
                if ($rule->is_taxable) {
                    $ctx['taxable_earnings'] = round((float) $ctx['taxable_earnings'] + $amount, 2);
                }
            } else {
                $ctx['total_deductions'] = round((float) $ctx['total_deductions'] + abs($amount), 2);
            }

            if (strtoupper((string) $rule->code) === 'EPF_SALARY') {
                $ctx['epf_salary'] = $amount;
            }

            foreach ($eval['errors'] as $e) {
                $errors[] = '['.$rule->code.'] '.$e;
            }
        }

        return ['components' => $components, 'errors' => $errors];
    }
}
