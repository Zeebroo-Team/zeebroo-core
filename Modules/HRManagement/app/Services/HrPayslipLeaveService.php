<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\LeaveRequest;
use Modules\HRManagement\Models\PayrollCycle;

final class HrPayslipLeaveService
{
    /**
     * @return Collection<int, LeaveRequest>
     */
    public function approvedLeavesOverlappingCycle(PayrollCycle $cycle, Employee $employee): Collection
    {
        $periodEndYmd = Carbon::parse($cycle->period_end)->toDateString();
        $periodStartYmd = Carbon::parse($cycle->period_start)->toDateString();

        return LeaveRequest::query()
            ->where('business_id', $cycle->business_id)
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('starts_on', '<=', $periodEndYmd)
            ->whereDate('ends_on', '>=', $periodStartYmd)
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get();
    }

    public function calendarDaysInCyclePeriod(LeaveRequest $leave, PayrollCycle $cycle): int
    {
        $periodStart = Carbon::parse($cycle->period_start)->startOfDay();
        $periodEnd = Carbon::parse($cycle->period_end)->startOfDay();
        $leaveStart = Carbon::parse($leave->starts_on)->startOfDay();
        $leaveEnd = Carbon::parse($leave->ends_on)->startOfDay();

        $overlapStart = $leaveStart->greaterThan($periodStart) ? $leaveStart : $periodStart;
        $overlapEnd = $leaveEnd->lessThan($periodEnd) ? $leaveEnd : $periodEnd;
        if ($overlapEnd->lt($overlapStart)) {
            return 0;
        }

        return (int) ($overlapStart->diffInDays($overlapEnd) + 1);
    }

    public function pendingLeaveCount(PayrollCycle $cycle, Employee $employee): int
    {
        return LeaveRequest::query()
            ->where('business_id', $cycle->business_id)
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->count();
    }

    /**
     * @return Collection<int, array{leave: LeaveRequest, days_in_period: int}>
     */
    public function approvedLeaveRowsForPayslip(PayrollCycle $cycle, Employee $employee): Collection
    {
        return $this->approvedLeavesOverlappingCycle($cycle, $employee)
            ->map(fn (LeaveRequest $leave): array => [
                'leave' => $leave,
                'days_in_period' => $this->calendarDaysInCyclePeriod($leave, $cycle),
            ])
            ->values();
    }

    /**
     * @return array{
     *     approved_leave_rows: Collection<int, array{leave: LeaveRequest, days_in_period: int}>,
     *     pending_count: int,
     *     employee_leave_url: ?string,
     *     leave_inbox_url: string
     * }
     */
    public function payslipLeaveContext(PayrollCycle $cycle, ?Employee $employee): array
    {
        if ($employee === null) {
            return [
                'approved_leave_rows' => collect(),
                'pending_count' => 0,
                'employee_leave_url' => null,
                'leave_inbox_url' => route('hr.leave-requests.index'),
            ];
        }

        return [
            'approved_leave_rows' => $this->approvedLeaveRowsForPayslip($cycle, $employee),
            'pending_count' => $this->pendingLeaveCount($cycle, $employee),
            'employee_leave_url' => route('hr.employees.show', $employee).'#leave',
            'leave_inbox_url' => route('hr.leave-requests.index'),
        ];
    }
}
