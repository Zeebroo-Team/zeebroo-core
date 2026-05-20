<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Illuminate\Support\Carbon;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\HRManagement\Models\PayrollItem;
use Modules\HRManagement\Models\PayrollRule;
use Modules\HRManagement\Models\PayrollRuleSet;
use Modules\HRManagement\Payroll\RegionalTemplates\IndianPayrollRegionalTemplate;
use Modules\HRManagement\Payroll\RegionalTemplates\LkTwentySixDayEpfWorksheetPayrollTemplate;
use Modules\HRManagement\Payroll\RegionalTemplates\SriLankanEmployeeStandardPayrollTemplate as SlTemplate;

final class PayrollSalarySheetPresentationService
{
    /** Component codes already represented as {@see PayrollItem} columns for the “dynamic” layout. */
    private const SKIPPED_DYNAMIC_CODES = ['BASIC_SALARY', 'OVERTIME'];

    /**
     * @return array{
     *     columns: list<array<string, mixed>>,
     *     rows: list<array<string, mixed>>,
     *     variance: array{previous_cycle_label: ?string}
     * }
     */
    public function forCycle(PayrollCycle $cycle, Business $business): array
    {
        $cycle->loadMissing([
            'ruleSet.rules',
            'items.employee',
            'items.components',
        ]);

        if ($cycle->relationLoaded('ruleSet') && $cycle->ruleSet !== null && $cycle->ruleSet->relationLoaded('rules')) {
            $cycle->ruleSet->setRelation(
                'rules',
                $cycle->ruleSet->rules->sortBy([
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])->values(),
            );
        }

        $columns = $this->columnsFor($cycle, $business);
        $columns = $this->withVarianceColumns($columns);
        $previousCycle = $this->previousCycleForVariance($cycle);
        $previousByEmployee = $this->previousCycleItemsByEmployee($previousCycle);
        $rows = $this->buildRows($cycle, $columns, $previousByEmployee);
        $variance = [
            'previous_cycle_label' => $previousCycle?->name,
        ];

        return [
            'columns' => $columns,
            'rows' => $rows,
            'variance' => $variance,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  array<int, PayrollItem>  $previousByEmployee
     */
    private function buildRows(PayrollCycle $cycle, array $columns, array $previousByEmployee): array
    {
        return $cycle->items->map(function (PayrollItem $item) use ($columns, $previousByEmployee): array {
            $byCode = [];
            foreach ($item->components as $component) {
                $code = strtoupper(trim((string) $component->code));
                if ($code === '') {
                    continue;
                }
                $byCode[$code] = round(($byCode[$code] ?? 0) + abs((float) $component->amount), 2);
            }

            $previous = $previousByEmployee[(int) $item->employee_id] ?? null;
            $variance = [
                'gross' => round((float) $item->gross_earnings - (float) ($previous?->gross_earnings ?? 0), 2),
                'deductions' => round((float) $item->total_deductions - (float) ($previous?->total_deductions ?? 0), 2),
                'net' => round((float) $item->net_pay - (float) ($previous?->net_pay ?? 0), 2),
            ];

            $values = [];
            foreach ($columns as $column) {
                $kind = (string) ($column['kind'] ?? '');
                if ($kind !== 'money') {
                    continue;
                }
                $values[(string) $column['key']] = $this->numericCell($column, $item, $byCode, $variance);
            }

            return [
                'employee_name' => (string) ($item->employee?->full_name ?? __('Unknown employee')),
                'employee_id' => (string) ($item->employee?->employee_id ?? ''),
                'payroll_item_id' => (int) $item->id,
                'status' => ucfirst((string) $item->status),
                'values' => $values,
                'variance' => $variance,
            ];
        })->values()->all();
    }

    private function previousCycleForVariance(PayrollCycle $cycle): ?PayrollCycle
    {
        $periodStart = Carbon::parse($cycle->period_start)->toDateString();

        return PayrollCycle::query()
            ->where('business_id', $cycle->business_id)
            ->where('id', '!=', $cycle->id)
            ->whereDate('period_end', '<', $periodStart)
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<int, PayrollItem>
     */
    private function previousCycleItemsByEmployee(?PayrollCycle $previousCycle): array
    {
        if ($previousCycle === null) {
            return [];
        }

        return $previousCycle->items()
            ->with(['components'])
            ->get()
            ->keyBy(static fn (PayrollItem $item): int => (int) $item->employee_id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $column
     * @param  array<string, float>  $byCode
     */
    private function numericCell(array $column, PayrollItem $item, array $byCode, array $variance): float
    {
        $src = $column['src'] ?? null;
        if (! is_array($src)) {
            return 0.0;
        }
        $t = (string) ($src['t'] ?? '');

        return match ($t) {
            'item' => $this->itemNumericField($item, (string) ($src['f'] ?? '')),
            'component' => round((float) ($byCode[strtoupper((string) ($src['c'] ?? ''))] ?? 0), 2),
            'variance' => round((float) ($variance[(string) ($src['f'] ?? '')] ?? 0), 2),
            default => 0.0,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @return list<array<string, mixed>>
     */
    private function withVarianceColumns(array $columns): array
    {
        $varianceColumns = [
            [
                'key' => 'var_gross',
                'kind' => 'money',
                'label' => __('Δ Gross'),
                'src' => ['t' => 'variance', 'f' => 'gross'],
            ],
            [
                'key' => 'var_deductions',
                'kind' => 'money',
                'label' => __('Δ Deductions'),
                'src' => ['t' => 'variance', 'f' => 'deductions'],
            ],
            [
                'key' => 'var_net',
                'kind' => 'money',
                'label' => __('Δ Net'),
                'emphasize' => true,
                'src' => ['t' => 'variance', 'f' => 'net'],
            ],
        ];

        $statusIndex = null;
        foreach ($columns as $idx => $column) {
            if (($column['kind'] ?? '') === 'status') {
                $statusIndex = $idx;
                break;
            }
        }
        if ($statusIndex === null) {
            return array_merge($columns, $varianceColumns);
        }

        return array_values(array_merge(
            array_slice($columns, 0, $statusIndex),
            $varianceColumns,
            array_slice($columns, $statusIndex),
        ));
    }

    /** @return list<array<string, mixed>> */
    private function columnsFor(PayrollCycle $cycle, Business $business): array
    {
        $profile = $this->resolveProfileKey($cycle, $business);

        return match ($profile) {
            'sl' => $this->sriLankaColumns(),
            'in' => $this->indiaColumns(),
            'lk26' => $this->lk26Columns(),
            default => $this->dynamicColumnsFromRuleSet($cycle->ruleSet),
        };
    }

    private function resolveProfileKey(PayrollCycle $cycle, Business $business): string
    {
        $name = trim((string) ($cycle->ruleSet?->name ?? ''));
        if ($name === SlTemplate::RULE_SET_NAME) {
            return 'sl';
        }
        if ($name === IndianPayrollRegionalTemplate::RULE_SET_NAME) {
            return 'in';
        }
        if ($name === LkTwentySixDayEpfWorksheetPayrollTemplate::RULE_SET_NAME) {
            return 'lk26';
        }

        $tpl = trim((string) get_settings(
            'hr.payroll.template',
            SlTemplate::KEY,
            $business,
        ));

        return match ($tpl) {
            IndianPayrollRegionalTemplate::KEY => 'in',
            LkTwentySixDayEpfWorksheetPayrollTemplate::KEY => 'lk26',
            SlTemplate::KEY => 'sl',
            default => 'dynamic',
        };
    }

    /** @return list<array<string, mixed>> */
    private function sriLankaColumns(): array
    {
        return [
            $this->employeeCol(),
            $this->moneyItem('basic_salary', __('Basic')),
            $this->moneyItem('overtime_amount', __('OT')),
            $this->moneyItem('gross_earnings', __('Gross')),
            $this->moneyComponent('EPF_EMPLOYEE', __('EPF Emp.')),
            $this->moneyComponent('EPF_EMPLOYER', __('EPF Emplr.')),
            $this->moneyComponent('ETF_EMPLOYER', __('ETF Emplr.')),
            $this->moneyComponent('APIT', __('APIT')),
            $this->moneyItem('total_deductions', __('Deductions')),
            $this->moneyItem('net_pay', __('Net pay'), true),
            $this->statusCol(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function indiaColumns(): array
    {
        return [
            $this->employeeCol(),
            $this->moneyItem('basic_salary', __('Basic')),
            $this->moneyItem('overtime_amount', __('OT')),
            $this->moneyItem('gross_earnings', __('Gross')),
            $this->moneyComponent('PF_EMPLOYEE', __('PF emp.')),
            $this->moneyComponent('PF_EMPLOYER', __('PF emplr. (tracking)')),
            $this->moneyComponent('ESI_EMPLOYEE', __('ESI emp.')),
            $this->moneyComponent('ESI_EMPLOYER', __('ESI emplr. (tracking)')),
            $this->moneyComponent('PT_IN', __('Prof. tax')),
            $this->moneyComponent('TDS_IN', __('TDS')),
            $this->moneyItem('total_deductions', __('Deductions')),
            $this->moneyItem('net_pay', __('Net pay'), true),
            $this->statusCol(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lk26Columns(): array
    {
        return [
            $this->employeeCol(),
            $this->moneyItem('basic_salary', __('Basic')),
            $this->moneyItem('overtime_amount', __('OT')),
            $this->moneyItem('gross_earnings', __('Gross')),
            $this->moneyComponent('NO_PAY_AMOUNT', __('No pay (ref.)')),
            $this->moneyComponent('EPF_SALARY', __('EPF salary')),
            $this->moneyComponent('MEDICAL_ALLOWANCE', __('Medical')),
            $this->moneyComponent('COLA_ALLOWANCE', __('COLA / living')),
            $this->moneyComponent('ATTENDANCE_ALLOWANCE', __('Attendance')),
            $this->moneyComponent('PERFORMANCE_ALLOWANCE', __('Performance')),
            $this->moneyComponent('APIT_SALARY', __('Taxable gross (ref.)')),
            $this->moneyComponent('PAYE_TAX', __('PAYE / APIT')),
            $this->moneyComponent('SALARY_ADVANCE', __('Salary advance')),
            $this->moneyComponent('STAMP_DUTY', __('Stamp')),
            $this->moneyComponent('EPF_EMPLOYEE', __('EPF emp.')),
            $this->moneyComponent('EPF_EMPLOYER', __('EPF emplr. (cost)')),
            $this->moneyComponent('ETF_EMPLOYER', __('ETF emplr. (cost)')),
            $this->moneyComponent('COST_TO_COMPANY', __('Cost to company')),
            $this->moneyItem('total_deductions', __('Deductions')),
            $this->moneyItem('net_pay', __('Net pay'), true),
            $this->statusCol(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function dynamicColumnsFromRuleSet(?PayrollRuleSet $ruleSet): array
    {
        $cols = [
            $this->employeeCol(),
            $this->moneyItem('basic_salary', __('Basic')),
            $this->moneyItem('overtime_amount', __('OT')),
            $this->moneyItem('gross_earnings', __('Gross')),
        ];

        if ($ruleSet !== null) {
            $seenCodes = [];
            foreach ($ruleSet->rules as $rule) {
                if (! ($rule instanceof PayrollRule)) {
                    continue;
                }
                if (! $rule->is_active) {
                    continue;
                }
                $code = strtoupper(trim((string) $rule->code));
                if ($code === '' || in_array($code, self::SKIPPED_DYNAMIC_CODES, true)) {
                    continue;
                }
                if ($rule->component_type === PayrollRule::TYPE_OVERTIME) {
                    continue;
                }
                if (isset($seenCodes[$code])) {
                    continue;
                }
                $seenCodes[$code] = true;
                $cols[] = $this->moneyComponent($code, $this->ruleColumnLabel($rule));
            }
        }

        $cols[] = $this->moneyItem('total_deductions', __('Deductions'));
        $cols[] = $this->moneyItem('net_pay', __('Net pay'), true);
        $cols[] = $this->statusCol();

        return $cols;
    }

    private function ruleColumnLabel(PayrollRule $rule): string
    {
        $name = trim((string) $rule->name);

        return $name !== '' ? $name : (string) $rule->code;
    }

    /** @return array<string, mixed> */
    private function employeeCol(): array
    {
        return [
            'key' => 'employee',
            'kind' => 'employee',
            'label' => __('Employee'),
        ];
    }

    /** @return array<string, mixed> */
    private function statusCol(): array
    {
        return [
            'key' => 'status',
            'kind' => 'status',
            'label' => __('Status'),
        ];
    }

    /** @return array<string, mixed> */
    private function moneyItem(string $field, string $label, bool $emphasize = false): array
    {
        return [
            'key' => 'item_'.$field,
            'kind' => 'money',
            'label' => $label,
            'emphasize' => $emphasize,
            'src' => ['t' => 'item', 'f' => $field],
        ];
    }

    /** @return array<string, mixed> */
    private function moneyComponent(string $code, string $label, bool $emphasize = false): array
    {
        $key = 'comp_'.$code;

        return [
            'key' => $key,
            'kind' => 'money',
            'label' => $label,
            'emphasize' => $emphasize,
            'src' => ['t' => 'component', 'c' => $code],
        ];
    }

    private function itemNumericField(PayrollItem $item, string $field): float
    {
        if ($field === '') {
            return 0.0;
        }
        $attrs = $item->getAttributes();

        return round((float) ($attrs[$field] ?? 0), 2);
    }
}
