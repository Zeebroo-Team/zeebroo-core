<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\HRManagement\Models\PayrollItem;
use Modules\HRManagement\Models\PayrollRule;
use Modules\HRManagement\Models\PayrollRuleSet;

final class PayrollComputationService
{
    public function __construct(
        private readonly PayrollComponentBuilderService $componentBuilder,
    ) {}

    public function resolveRuleSetForCycle(Business $business, ?PayrollCycle $cycle = null): ?PayrollRuleSet
    {
        $date = $cycle?->period_end ?? now()->toDateString();

        return $business->payrollRuleSets()
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(static function ($q) use ($date): void {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('is_default')
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{item: PayrollItem, errors: list<string>}
     */
    public function computeEmployee(PayrollCycle $cycle, Employee $employee, array $inputs = []): array
    {
        $ruleSet = $cycle->ruleSet ?: $this->resolveRuleSetForCycle($cycle->business, $cycle);
        if (! $ruleSet) {
            throw new \RuntimeException('No active payroll rule set found for this cycle.');
        }

        $business = $cycle->business;
        $defaultOvertimeRate = round((float) ($employee->basic_salary ?? 0) / 240, 2);

        $buildMode = (string) get_settings('hr.payroll.build_mode', 'default', $business);
        $standardDaysSetting = round((float) get_settings('hr.payroll.cycle.default_working_days', 26, $business), 4);
        if ($standardDaysSetting <= 0.0) {
            $standardDaysSetting = 26.0;
        }
        /** Max attendance days credited for this cycle (scales down when joined mid-period). */
        $cycleStandardDays = $this->nominalWorkingDaysCapForCycle($cycle, $employee, $standardDaysSetting);
        $enteredAttendance = isset($inputs['attendance_days']) && $inputs['attendance_days'] !== null && $inputs['attendance_days'] !== '';
        $rawAttendance = (float) ($inputs['attendance_days'] ?? 0);
        $actualDaysRaw = $enteredAttendance ? $rawAttendance : $cycleStandardDays;
        $effectiveActual = max(0.0, min($actualDaysRaw, $cycleStandardDays));

        $basicSalaryMonthly = round((float) ($employee->basic_salary ?? 0), 2);
        $dailyBasic = round($basicSalaryMonthly / $standardDaysSetting, 6);
        $noPayAmount = round($dailyBasic * max(0.0, $cycleStandardDays - $effectiveActual), 2);
        $epfSalaryEarnedBase = round($dailyBasic * $effectiveActual, 2);
        $basicSalaryEarned = round($dailyBasic * $effectiveActual, 2);
        $prorationRatio = $cycleStandardDays > 0 ? round($effectiveActual / $cycleStandardDays, 6) : 1.0;

        $employee->loadMissing('employeeAllowances.allowanceType');

        $reductionMeta = [
            'basic_salary_monthly' => $basicSalaryMonthly,
            'basic_salary_earned' => $basicSalaryEarned,
            'standard_days_setting' => $standardDaysSetting,
            'standard_days' => $cycleStandardDays,
            'actual_days' => $effectiveActual,
            'proration_ratio' => $prorationRatio,
            'no_pay_amount' => $noPayAmount,
            'entered_attendance' => $enteredAttendance,
            'cycle_period_start' => Carbon::parse($cycle->period_start)->toDateString(),
            'cycle_period_end' => Carbon::parse($cycle->period_end)->toDateString(),
            'employee_join_date' => $employee->date_of_joining?->toDateString(),
        ];

        $ctx = [
            'payroll_build_mode' => $buildMode,
            'standard_days' => $cycleStandardDays,
            'actual_days' => $effectiveActual,
            'proration_ratio' => $prorationRatio,
            'no_pay_amount' => $noPayAmount,
            'epf_salary' => $epfSalaryEarnedBase,
            'salary_advance' => round((float) ($inputs['salary_advance'] ?? 0), 2),
            'stamp_duty' => round((float) ($inputs['stamp_duty'] ?? 0), 2),
            'basic_salary' => $basicSalaryEarned,
            'basic_salary_monthly' => $basicSalaryMonthly,
            'gross_salary' => (float) ($employee->salary ?? 0),
            'overtime_hours' => (float) ($inputs['overtime_hours'] ?? 0),
            'overtime_rate' => (float) ($inputs['overtime_rate'] ?? $defaultOvertimeRate),
            'attendance_days' => (float) ($inputs['attendance_days'] ?? 0),
            'working_days' => (float) ($inputs['working_days'] ?? 0),
            'leave_without_pay_days' => (float) ($inputs['leave_without_pay_days'] ?? 0),
        ];

        foreach ($this->monthlyAllowanceBuckets($employee) as $key => $amount) {
            $ctx[$key] = $amount;
        }

        $build = $this->componentBuilder->build($ruleSet, $ctx);
        $components = $build['components'];
        $errors = $build['errors'];

        $gross = 0.0;
        $deductions = 0.0;
        $basic = (float) ($employee->basic_salary ?? 0);
        $overtime = 0.0;

        foreach ($components as $c) {
            $amount = (float) ($c['amount'] ?? 0);
            $type = (string) ($c['component_type'] ?? '');
            $meta = is_array($c['meta_json'] ?? null) ? $c['meta_json'] : [];
            if (($c['code'] ?? '') === 'BASIC_SALARY') {
                $basic = $amount;
            }
            if (($c['code'] ?? '') === 'OVERTIME') {
                $overtime = $amount;
            }
            if ($type === PayrollRule::TYPE_INFORMATIONAL || $type === PayrollRule::TYPE_EMPLOYER_TRACKING) {
                continue;
            }
            if (! empty($meta['exclude_from_payroll_totals'])) {
                continue;
            }
            if (in_array($type, [PayrollRule::TYPE_EARNING, PayrollRule::TYPE_OVERTIME], true)) {
                $gross += $amount;
            } else {
                $deductions += abs($amount);
            }
        }
        $gross = round($gross, 2);
        $deductions = round($deductions, 2);
        $net = round($gross - $deductions, 2);

        /** @var PayrollItem $item */
        $item = DB::transaction(function () use ($cycle, $employee, $inputs, $components, $errors, $basic, $overtime, $gross, $deductions, $net, $reductionMeta): PayrollItem {
            $item = PayrollItem::query()->firstOrNew([
                'payroll_cycle_id' => $cycle->id,
                'employee_id' => $employee->id,
            ]);

            $item->fill([
                'status' => $errors === [] ? 'computed' : 'error',
                'basic_salary' => round($basic, 2),
                'overtime_amount' => round($overtime, 2),
                'gross_earnings' => $gross,
                'total_deductions' => $deductions,
                'net_pay' => $net,
                'inputs_json' => $inputs,
                'snapshot_json' => [
                    'errors' => $errors,
                    'reduction' => $reductionMeta,
                ],
            ]);
            $item->save();

            $item->components()->delete();
            foreach ($components as $c) {
                $item->components()->create([
                    'rule_id' => $c['rule_id'] ?? null,
                    'code' => (string) $c['code'],
                    'name' => (string) $c['name'],
                    'component_type' => (string) $c['component_type'],
                    'quantity' => round((float) ($c['quantity'] ?? 1), 4),
                    'rate' => round((float) ($c['rate'] ?? 0), 4),
                    'amount' => round((float) ($c['amount'] ?? 0), 2),
                    'meta_json' => $c['meta_json'] ?? null,
                ]);
            }

            return $item->fresh(['components', 'employee']);
        });

        return ['item' => $item, 'errors' => $errors];
    }

    /**
     * Map employee allowance type names (case-insensitive) into monthly amounts for prorated formulas.
     *
     * @return array<string, float>
     */
    private function monthlyAllowanceBuckets(Employee $employee): array
    {
        $buckets = [
            'medical_allowance_monthly' => 0.0,
            'cola_allowance_monthly' => 0.0,
            'attendance_allowance_monthly' => 0.0,
            'performance_allowance_monthly' => 0.0,
        ];

        foreach ($employee->employeeAllowances as $ea) {
            $name = strtolower(trim((string) ($ea->allowanceType?->name ?? '')));
            if ($name === '') {
                continue;
            }
            $amount = round((float) ($ea->amount ?? 0), 2);
            if (str_contains($name, 'medical')) {
                $buckets['medical_allowance_monthly'] = round($buckets['medical_allowance_monthly'] + $amount, 2);
            } elseif (str_contains($name, 'cola') || str_contains($name, 'cost of living') || str_contains($name, 'living allowance')) {
                $buckets['cola_allowance_monthly'] = round($buckets['cola_allowance_monthly'] + $amount, 2);
            } elseif (str_contains($name, 'attendance')) {
                $buckets['attendance_allowance_monthly'] = round($buckets['attendance_allowance_monthly'] + $amount, 2);
            } elseif (str_contains($name, 'performance')) {
                $buckets['performance_allowance_monthly'] = round($buckets['performance_allowance_monthly'] + $amount, 2);
            }
        }

        return $buckets;
    }

    public function employeeEligibleForPayrollCycle(PayrollCycle $cycle, Employee $employee): bool
    {
        if ((int) $employee->business_id !== (int) $cycle->business_id) {
            return false;
        }
        $join = $employee->date_of_joining;
        if ($join === null) {
            return true;
        }

        return ! $join->isAfter(Carbon::parse($cycle->period_end)->startOfDay());
    }

    /**
     * Employees with a joining date after the payroll period ends are excluded.
     *
     * @return HasMany<\Modules\HRManagement\Models\Employee, \Modules\Business\Models\Business>
     */
    public function employeesQueryForCycle(PayrollCycle $cycle): HasMany
    {
        $periodEndDate = Carbon::parse($cycle->period_end)->toDateString();

        return $cycle->business
            ->employees()
            ->with(['employeeAllowances'])
            ->where(static function ($q) use ($periodEndDate): void {
                $q->whereNull('date_of_joining')
                    ->orWhereDate('date_of_joining', '<=', $periodEndDate);
            });
    }

    /**
     * Scales the nominal working-day cap when the hire date falls after period start so mid-month hires are capped fairly.
     */
    private function nominalWorkingDaysCapForCycle(PayrollCycle $cycle, Employee $employee, float $standardDaysSetting): float
    {
        if ($standardDaysSetting <= 0.0) {
            return 1.0;
        }

        $periodStart = Carbon::parse($cycle->period_start)->startOfDay();
        $periodEnd = Carbon::parse($cycle->period_end)->startOfDay();
        if ($periodEnd->lt($periodStart)) {
            return max(1.0, $standardDaysSetting);
        }

        $join = $employee->date_of_joining;
        if ($join === null) {
            return $standardDaysSetting;
        }
        $joinDay = Carbon::parse($join)->startOfDay();
        if ($joinDay->lte($periodStart)) {
            return $standardDaysSetting;
        }
        if ($joinDay->gt($periodEnd)) {
            return 1.0;
        }

        $totalCal = max(1, $periodStart->diffInDays($periodEnd) + 1);
        $overlapCal = max(1, $joinDay->diffInDays($periodEnd) + 1);

        return max(1.0, round($standardDaysSetting * ($overlapCal / $totalCal), 4));
    }

    /**
     * @return array{computed: int, errors: list<string>}
     */
    public function computeCycle(PayrollCycle $cycle): array
    {
        $employees = $this->employeesQueryForCycle($cycle)->get();
        $eligibleIds = $employees->modelKeys();

        $itemsQuery = PayrollItem::query()->where('payroll_cycle_id', $cycle->id);
        if ($eligibleIds !== []) {
            $itemsQuery->whereNotIn('employee_id', $eligibleIds);
        }
        $itemsQuery->delete();

        $errorBag = [];
        $count = 0;
        foreach ($employees as $employee) {
            $result = $this->computeEmployee($cycle, $employee);
            foreach ($result['errors'] as $err) {
                $errorBag[] = $employee->full_name.': '.$err;
            }
            $count++;
        }

        $cycle->forceFill([
            'status' => $errorBag === [] ? PayrollCycle::STATUS_COMPUTED : PayrollCycle::STATUS_DRAFT,
            'computed_at' => now(),
        ])->save();

        return ['computed' => $count, 'errors' => $errorBag];
    }

    public function finalizeCycle(PayrollCycle $cycle, int $byUserId): void
    {
        $hasErrors = $cycle->items()->where('status', 'error')->exists();
        if ($hasErrors) {
            throw new \RuntimeException('Cannot finalize payroll cycle with errored items.');
        }
        if (! $cycle->items()->exists()) {
            throw new \RuntimeException('Cannot finalize payroll cycle without computed items.');
        }

        $cycle->forceFill([
            'status' => PayrollCycle::STATUS_FINALIZED,
            'finalized_at' => now(),
            'finalized_by_user_id' => $byUserId,
        ])->save();
    }
}
