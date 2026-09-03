<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\AttendanceRecord;
use Modules\HRManagement\Models\Employee;

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
     * @return Collection<int, AttendanceRecord>
     */
    public function listRecent(Business $business, ?string $search = null, int $limit = 300): Collection
    {
        $query = AttendanceRecord::query()
            ->where('business_id', $business->id)
            ->with(['employee:id,full_name,employee_id']);

        $search = trim((string) $search);
        if ($search !== '') {
            $query->whereHas('employee', function ($q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('work_date')->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * Import check-in/check-out attendance rows uploaded from the desktop app (CSV).
     *
     * @param  array<int, array{employee_id?: string, check_in?: string, check_out?: string}>  $rows
     * @return array{imported: int, skipped: int, errors: array<int, array{row: int, employee_id: mixed, message: string}>}
     */
    public function importFromRows(Business $business, array $rows, int $recordedByUserId): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $idx => $row) {
            try {
                $employeeCode = trim((string) ($row['employee_id'] ?? ''));
                if ($employeeCode === '') {
                    throw new \RuntimeException('Employee ID is required.');
                }

                $employee = Employee::query()
                    ->where('business_id', $business->id)
                    ->where('employee_id', $employeeCode)
                    ->first();

                if (! $employee) {
                    throw new \RuntimeException("No employee found with ID \"{$employeeCode}\".");
                }

                $checkInRaw = trim((string) ($row['check_in'] ?? ''));
                $checkOutRaw = trim((string) ($row['check_out'] ?? ''));

                if ($checkInRaw === '' && $checkOutRaw === '') {
                    throw new \RuntimeException('Check-in or check-out time is required.');
                }

                $checkIn = $checkInRaw !== '' ? Carbon::parse($checkInRaw) : null;
                $checkOut = $checkOutRaw !== '' ? Carbon::parse($checkOutRaw) : null;

                $workedMinutes = null;
                if ($checkIn !== null && $checkOut !== null && $checkOut->greaterThan($checkIn)) {
                    $workedMinutes = (int) $checkIn->diffInMinutes($checkOut);
                }

                AttendanceRecord::query()->updateOrCreate(
                    [
                        'business_id' => $business->id,
                        'employee_id' => $employee->id,
                        'work_date' => ($checkIn ?? $checkOut)->toDateString(),
                    ],
                    [
                        'status' => AttendanceRecord::STATUS_PRESENT,
                        'check_in_at' => $checkIn,
                        'check_out_at' => $checkOut,
                        'worked_minutes' => $workedMinutes,
                        'source' => 'csv_import',
                        'recorded_by_user_id' => $recordedByUserId,
                    ]
                );

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = [
                    'row' => (int) $idx + 2,
                    'employee_id' => $row['employee_id'] ?? null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
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
