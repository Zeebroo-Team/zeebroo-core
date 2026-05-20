<?php

use Modules\HRManagement\Models\PayrollRule;
use Modules\HRManagement\Services\PayrollRuleEvaluationService;

test('fixed and percentage rules calculate expected amounts', function () {
    $svc = new PayrollRuleEvaluationService;

    $fixed = new PayrollRule([
        'calculation_mode' => PayrollRule::MODE_FIXED,
        'is_active' => true,
        'config_json' => ['amount' => 1500],
    ]);
    $fixedOut = $svc->evaluate($fixed, ['basic_salary' => 100000]);
    expect($fixedOut['amount'])->toBe(1500.0);

    $percentage = new PayrollRule([
        'calculation_mode' => PayrollRule::MODE_PERCENTAGE,
        'is_active' => true,
        'config_json' => ['base_field' => 'basic_salary', 'percent' => 8],
    ]);
    $percentOut = $svc->evaluate($percentage, ['basic_salary' => 120000]);
    expect($percentOut['amount'])->toBe(9600.0);
});

test('slab mode computes progressive totals', function () {
    $svc = new PayrollRuleEvaluationService;

    $slab = new PayrollRule([
        'calculation_mode' => PayrollRule::MODE_SLAB,
        'is_active' => true,
        'config_json' => [
            'input_field' => 'taxable_earnings',
            'slabs' => [
                ['from' => 0, 'to' => 100000, 'percent' => 0],
                ['from' => 100000, 'to' => 150000, 'percent' => 6],
                ['from' => 150000, 'to' => null, 'percent' => 12],
            ],
        ],
    ]);

    $out = $svc->evaluate($slab, ['taxable_earnings' => 200000]);
    // 6% of 50,000 + 12% of 50,000 = 9,000
    expect($out['amount'])->toBe(9000.0);
    expect($out['errors'])->toBeArray()->toHaveCount(0);
});

test('formula mode supports arithmetic variables', function () {
    $svc = new PayrollRuleEvaluationService;

    $formula = new PayrollRule([
        'calculation_mode' => PayrollRule::MODE_FORMULA,
        'is_active' => true,
        'config_json' => [
            'formula' => '(basic_salary + overtime_amount) * 0.1',
        ],
    ]);

    $out = $svc->evaluate($formula, [
        'basic_salary' => 100000,
        'overtime_amount' => 5000,
    ]);

    expect($out['amount'])->toBe(10500.0);
    expect($out['errors'])->toBeArray()->toHaveCount(0);
});

test('formula mode evaluates flow_v1 conditional graph before legacy formula', function () {
    $svc = new PayrollRuleEvaluationService;

    $rule = new PayrollRule([
        'calculation_mode' => PayrollRule::MODE_FORMULA,
        'is_active' => true,
        'config_json' => [
            'formula' => '999',
            'flow_v1' => [
                'version' => 1,
                'root' => 'out',
                'nodes' => [
                    'lhs' => ['type' => 'context', 'field' => 'basic_salary'],
                    'rhs' => ['type' => 'constant', 'value' => 50000],
                    'cmp' => ['type' => 'compare', 'op' => 'gt', 'left' => 'lhs', 'right' => 'rhs'],
                    'hi' => ['type' => 'constant', 'value' => 2000],
                    'lo' => ['type' => 'constant', 'value' => 1000],
                    'out' => ['type' => 'cond', 'test' => 'cmp', 'then' => 'hi', 'else' => 'lo'],
                ],
            ],
        ],
    ]);

    $high = $svc->evaluate($rule, ['basic_salary' => 60000]);
    expect($high['amount'])->toBe(2000.0);
    expect($high['errors'])->toHaveCount(0);

    $low = $svc->evaluate($rule, ['basic_salary' => 40000]);
    expect($low['amount'])->toBe(1000.0);
});
