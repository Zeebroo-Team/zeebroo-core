<?php

namespace Modules\HRManagement\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\HRManagement\Models\PayrollItem;

/**
 * Everything the desktop department detail page shows: members, salary band fit and payroll totals.
 */
class DepartmentDetailService
{
    private const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public function detail(Business $business, Department $department): array
    {
        abort_unless((int) $department->business_id === (int) $business->id, 404);

        $department->load(['headEmployee', 'coHeadEmployee']);

        $min = $department->salary_range_min !== null ? (float) $department->salary_range_min : null;
        $max = $department->salary_range_max !== null ? (float) $department->salary_range_max : null;

        $employees = Employee::with('jobTitle')
            ->where('business_id', $business->id)
            ->where('department_id', $department->id)
            ->orderBy('full_name')
            ->get();

        $rows = $employees->map(function (Employee $e) use ($min, $max) {
            $basic = $e->basic_salary !== null ? (float) $e->basic_salary : null;

            return [
                'id'               => $e->id,
                'name'             => $e->full_name,
                'employee_id'      => $e->employee_id,
                'employment_type'  => $e->employment_type,
                'job_title'        => $e->jobTitle?->name,
                'date_of_joining'  => $e->date_of_joining?->format('Y-m-d'),
                'basic_salary'     => $basic,
                'basic_salary_fmt' => $basic !== null ? $this->fmt($basic) : null,
                'salary'           => $e->salary !== null ? (float) $e->salary : null,
                'range_position'   => $this->rangePosition($basic, $min, $max),
            ];
        })->values();

        $salaries = $rows->pluck('basic_salary')->filter(fn ($v) => $v !== null);
        $positions = $rows->countBy('range_position');

        return [
            'id'               => $department->id,
            'name'             => $department->name,
            'employees_count'  => $rows->count(),
            'head'             => $department->headEmployee?->full_name,
            'co_head'          => $department->coHeadEmployee?->full_name,
            'head_employee_id'    => $department->head_employee_id,
            'co_head_employee_id' => $department->co_head_employee_id,
            'salary_range_min' => $min,
            'salary_range_max' => $max,
            'salary_range_min_fmt' => $min !== null ? $this->fmt($min) : null,
            'salary_range_max_fmt' => $max !== null ? $this->fmt($max) : null,
            'employment_types' => [
                'full_time' => $employees->where('employment_type', 'full_time')->count(),
                'part_time' => $employees->where('employment_type', 'part_time')->count(),
                'contract'  => $employees->where('employment_type', 'contract')->count(),
            ],
            'salary_stats' => [
                'with_salary'   => $salaries->count(),
                'monthly_basic' => round((float) $salaries->sum(), 2),
                'monthly_gross' => round((float) $employees->sum(fn (Employee $e) => (float) ($e->salary ?? 0)), 2),
                'lowest'        => $salaries->isNotEmpty() ? (float) $salaries->min() : null,
                'highest'       => $salaries->isNotEmpty() ? (float) $salaries->max() : null,
                'average'       => $salaries->isNotEmpty() ? round((float) $salaries->avg(), 2) : null,
                'below'         => (int) ($positions['below'] ?? 0),
                'within'        => (int) ($positions['within'] ?? 0),
                'above'         => (int) ($positions['above'] ?? 0),
            ],
            'employees' => $rows,
            'payroll'   => $this->payroll($business, $employees->pluck('id')->all()),
        ];
    }

    /**
     * Payroll cycles that paid this department's current members, newest first, with per-cycle sums.
     *
     * @param  array<int, int>  $employeeIds
     */
    private function payroll(Business $business, array $employeeIds): array
    {
        $totals = ['cycles' => 0, 'gross' => 0.0, 'deductions' => 0.0, 'net' => 0.0, 'finalized_net' => 0.0];

        if ($employeeIds === [] || ! Schema::hasTable('hr_payroll_items')) {
            return ['totals' => $totals, 'cycles' => []];
        }

        $sums = PayrollItem::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereHas('cycle', fn ($q) => $q->where('business_id', $business->id))
            ->selectRaw('payroll_cycle_id, COUNT(*) as employees, SUM(gross_earnings) as gross, SUM(total_deductions) as deductions, SUM(net_pay) as net')
            ->groupBy('payroll_cycle_id')
            ->get()
            ->keyBy('payroll_cycle_id');

        $cycles = PayrollCycle::whereIn('id', $sums->keys())
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(function (PayrollCycle $c) use ($sums, &$totals) {
                $s = $sums[$c->id];
                $gross = round((float) $s->gross, 2);
                $deductions = round((float) $s->deductions, 2);
                $net = round((float) $s->net, 2);

                $totals['cycles']++;
                $totals['gross'] += $gross;
                $totals['deductions'] += $deductions;
                $totals['net'] += $net;
                if ($c->isFinalized()) {
                    $totals['finalized_net'] += $net;
                }

                return [
                    'id'           => $c->id,
                    'name'         => $c->name,
                    'year'         => (int) $c->year,
                    'month'        => (int) $c->month,
                    'month_label'  => self::MONTH_NAMES[(int) $c->month] ?? '',
                    'status'       => $c->status,
                    'status_label' => ucfirst($c->status ?? 'draft'),
                    'employees'    => (int) $s->employees,
                    'gross'        => $gross,
                    'deductions'   => $deductions,
                    'net'          => $net,
                ];
            })
            ->values();

        foreach (['gross', 'deductions', 'net', 'finalized_net'] as $k) {
            $totals[$k] = round($totals[$k], 2);
        }

        return ['totals' => $totals, 'cycles' => $cycles];
    }

    /** Where a salary falls against the department band; null when either side is unknown. */
    private function rangePosition(?float $salary, ?float $min, ?float $max): ?string
    {
        if ($salary === null || ($min === null && $max === null)) {
            return null;
        }
        if ($min !== null && $salary < $min) {
            return 'below';
        }
        if ($max !== null && $salary > $max) {
            return 'above';
        }

        return 'within';
    }

    private function fmt(float $v): string
    {
        return number_format($v, 2, '.', ',');
    }
}
