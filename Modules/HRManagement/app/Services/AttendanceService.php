<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\AttendanceRecord;

final class AttendanceService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function monthlyEmployeeSummary(Business $business, Carbon $monthDate): Collection
    {
        $start = $monthDate->copy()->startOfMonth();
        $end = $monthDate->copy()->endOfMonth();

        $employees = $business->employees()->get();
        if ($employees->isEmpty()) {
            return collect();
        }

        $records = AttendanceRecord::query()
            ->where('business_id', $business->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('employee_id');

        return $employees->map(function ($employee) use ($records): array {
            $rows = collect($records->get($employee->id, []));

            $present = $rows->where('status', AttendanceRecord::STATUS_PRESENT)->count();
            $halfDay = $rows->where('status', AttendanceRecord::STATUS_HALF_DAY)->count();
            $paidLeave = $rows->where('status', AttendanceRecord::STATUS_PAID_LEAVE)->count();
            $unpaidLeave = $rows->where('status', AttendanceRecord::STATUS_UNPAID_LEAVE)->count();
            $absent = $rows->where('status', AttendanceRecord::STATUS_ABSENT)->count();

            return [
                'employee' => $employee,
                'present_days' => $present,
                'half_days' => $halfDay,
                'paid_leave_days' => $paidLeave,
                'unpaid_leave_days' => $unpaidLeave,
                'absent_days' => $absent,
                'worked_minutes' => (int) $rows->sum('worked_minutes'),
            ];
        });
    }

    /**
     * @return array<int, string>
     */
    public function statusOptions(): array
    {
        return collect(AttendanceRecord::STATUSES)
            ->mapWithKeys(fn (string $status): array => [$status => (string) __(str_replace('_', ' ', ucfirst($status)))])
            ->all();
    }

    /**
     * @return array{working_days: int, attendance_days: float, leave_without_pay_days: float}
     */
    public function payrollInputsForMonth(Business $business, int $employeeId, Carbon $monthDate): array
    {
        $start = $monthDate->copy()->startOfMonth()->toDateString();
        $end = $monthDate->copy()->endOfMonth()->toDateString();

        $records = AttendanceRecord::query()
            ->where('business_id', $business->id)
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$start, $end])
            ->get();

        $attendanceDays = 0.0;
        $leaveWithoutPay = 0.0;
        foreach ($records as $record) {
            if ($record->status === AttendanceRecord::STATUS_PRESENT) {
                $attendanceDays += 1.0;
            } elseif ($record->status === AttendanceRecord::STATUS_HALF_DAY) {
                $attendanceDays += 0.5;
                $leaveWithoutPay += 0.5;
            } elseif (in_array($record->status, [AttendanceRecord::STATUS_ABSENT, AttendanceRecord::STATUS_UNPAID_LEAVE], true)) {
                $leaveWithoutPay += 1.0;
            }
        }

        $workingDays = CarbonPeriod::create(
            $monthDate->copy()->startOfMonth(),
            $monthDate->copy()->endOfMonth()
        )->filter(fn (Carbon $day): bool => ! $day->isWeekend())->count();

        return [
            'working_days' => (int) $workingDays,
            'attendance_days' => round($attendanceDays, 2),
            'leave_without_pay_days' => round($leaveWithoutPay, 2),
        ];
    }
}
