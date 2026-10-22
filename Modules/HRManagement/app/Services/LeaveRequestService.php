<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\AttendanceRecord;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\HrBusinessHoliday;
use Modules\HRManagement\Models\LeaveRequest;

/**
 * Recording leave from the desktop (employee Leave tab and Attendance → Leave) and deciding pending requests.
 *
 * Rules checked against the employee's leave rule:
 *  - hard errors (never bypassable): bad dates, overlap with other leave, half day / short leave not allowed, too long
 *  - soft errors (bypassable with "allow anyway"): not enough balance, still on probation, monthly short-leave limit used
 */
final class LeaveRequestService
{
    public function __construct(
        private readonly LeavePolicyService $leavePolicies,
        private readonly EmployeeLeaveBalanceService $balances,
        private readonly HrWorkSettingsService $workSettings,
    ) {}

    /**
     * Work out what a request would charge and which rules it breaks, without saving.
     *
     * @param  array<string, mixed>  $data  shape-validated input
     * @return array{days: float, remaining: float|null, short_usage: ?array, errors: array<string, string>, warnings: array<string, string>, attributes: array<string, mixed>}
     */
    public function evaluate(Business $business, Employee $employee, array $data): array
    {
        $type     = (string) $data['leave_type'];
        $isShort  = $type === LeaveRequest::TYPE_SHORT;
        $start    = CarbonImmutable::parse((string) $data['starts_on'])->startOfDay();
        $end      = $isShort || empty($data['ends_on']) ? $start : CarbonImmutable::parse((string) $data['ends_on'])->startOfDay();
        $halfDay  = ! $isShort && ! empty($data['is_half_day']);
        $policy   = $this->leavePolicies->resolveFor($business, $employee->loadMissing('jobTitle.leavePolicy'))['policy'];
        $errors   = [];
        $warnings = [];
        $days     = 0.0;
        $remaining = null;
        $shortUsage = null;

        if ($end->lt($start)) {
            $errors['ends_on'] = 'End date must be on or after the start date.';
        } elseif ($start->year !== $end->year) {
            $errors['ends_on'] = 'Leave cannot cross into a new year — split it into two requests.';
        }

        if ($isShort) {
            $minutes = (int) ($data['short_leave_minutes'] ?? 0);
            $shortUsage = $this->balances->shortLeaveUsage($employee, $policy, $start);
            if ($shortUsage === null) {
                $errors['leave_type'] = 'Short leave is not allowed by this employee\'s leave rule.';
            } else {
                if ($minutes > $shortUsage['max_minutes']) {
                    $errors['short_leave_minutes'] = "Short leave can be at most {$shortUsage['max_minutes']} minutes under this rule.";
                }
                if ($shortUsage['used'] >= $shortUsage['allowed']) {
                    $warnings['short_leave_limit'] = "Monthly short leave limit reached ({$shortUsage['used']} of {$shortUsage['allowed']} used in {$start->format('F')}).";
                }
            }
        } elseif ($halfDay) {
            if (! $start->equalTo($end)) {
                $errors['is_half_day'] = 'A half day must start and end on the same date.';
            } elseif (! in_array($type, LeaveRequest::HALF_DAY_TYPES, true)) {
                $errors['is_half_day'] = 'Half day is only available for annual, casual and sick leave.';
            } elseif ($policy !== null && ! $policy->half_day_allowed) {
                $errors['is_half_day'] = 'Half-day leave is not allowed by this employee\'s leave rule.';
            }
            $days = 0.5;
        } elseif ($errors === []) {
            $days = (float) $this->workingDays($business, $start, $end);
            if ($days <= 0) {
                $errors['starts_on'] = 'There are no working days in the selected dates (weekend or holiday).';
            }
        }

        if ($errors === []) {
            if ($clash = $this->overlapping($employee, $start, $end, $isShort, $halfDay, $data['half_day_period'] ?? null)) {
                $errors['starts_on'] = sprintf(
                    'Overlaps existing %s %s leave (%s%s).',
                    $clash->status,
                    $clash->leave_type,
                    $clash->starts_on->toDateString(),
                    $clash->ends_on->equalTo($clash->starts_on) ? '' : ' → '.$clash->ends_on->toDateString()
                );
            }
        }

        if (! $isShort && $type !== 'unpaid') {
            $eligibleFrom = $this->balances->eligibleFrom($employee, $policy);
            if ($eligibleFrom !== null && $start->lt($eligibleFrom)) {
                $warnings['probation'] = "Employee is on probation until {$eligibleFrom->toDateString()}.";
            }
        }

        if (! $isShort && $days > 0) {
            $summary   = $this->balances->yearlySummary($business, $employee, $start->year)['types'][$type] ?? null;
            $remaining = $summary['remaining'] ?? null;
            if ($remaining !== null && $days > $remaining) {
                $warnings['balance'] = sprintf('Not enough %s leave: %s day(s) requested, %s left.', $type, $this->num($days), $this->num($remaining));
            }
        }

        return [
            'days'        => $days,
            'remaining'   => $remaining,
            'short_usage' => $shortUsage,
            'errors'      => $errors,
            'warnings'    => $warnings,
            'attributes'  => [
                'leave_type'          => $type,
                'starts_on'           => $start->toDateString(),
                'ends_on'             => $end->toDateString(),
                'days'                => $isShort ? 0 : $days,
                'is_half_day'         => $halfDay,
                'half_day_period'     => $halfDay ? ($data['half_day_period'] ?? 'am') : null,
                'short_leave_start'   => $isShort ? ($data['short_leave_start'] ?? null) : null,
                'short_leave_minutes' => $isShort ? (int) ($data['short_leave_minutes'] ?? 0) : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  shape-validated input (+ status, note, allow_override)
     *
     * @throws ValidationException
     */
    public function create(Business $business, Employee $employee, array $data, User $by): LeaveRequest
    {
        $eval = $this->evaluate($business, $employee, $data);
        $override = ! empty($data['allow_override']);

        $messages = $eval['errors'] + ($override ? [] : $eval['warnings']);
        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        $status = ($data['status'] ?? LeaveRequest::STATUS_APPROVED) === LeaveRequest::STATUS_PENDING
            ? LeaveRequest::STATUS_PENDING
            : LeaveRequest::STATUS_APPROVED;

        $leave = LeaveRequest::create($eval['attributes'] + [
            'business_id'         => $business->id,
            'employee_id'         => $employee->id,
            'note'                => isset($data['note']) && trim((string) $data['note']) !== '' ? trim((string) $data['note']) : null,
            'status'              => $status,
            'recorded_by_user_id' => $by->id,
            'decided_by_user_id'  => $status === LeaveRequest::STATUS_APPROVED ? $by->id : null,
            'decided_at'          => $status === LeaveRequest::STATUS_APPROVED ? now() : null,
        ]);

        if ($leave->status === LeaveRequest::STATUS_APPROVED) {
            $this->syncAttendance($business, $leave);
        }

        return $leave;
    }

    /** @throws ValidationException */
    public function decide(LeaveRequest $leave, string $status, User $by): LeaveRequest
    {
        if (! $leave->isPending()) {
            throw ValidationException::withMessages(['status' => 'Only pending leave can be approved or rejected.']);
        }

        $leave->update([
            'status'             => $status,
            'decided_by_user_id' => $by->id,
            'decided_at'         => now(),
        ]);

        if ($status === LeaveRequest::STATUS_APPROVED) {
            $this->syncAttendance($leave->business, $leave);
        }

        return $leave;
    }

    /**
     * Write a paid_leave / unpaid_leave attendance row for each working day of an approved full-day leave,
     * so attendance and payroll see it. Days that already have a record (e.g. CSV check-in) are left alone.
     * The rows are linked by leave_request_id and are removed automatically when the leave is deleted.
     */
    public function syncAttendance(Business $business, LeaveRequest $leave): void
    {
        if ($leave->isShort() || $leave->is_half_day) {
            return;
        }

        $status = $leave->leave_type === 'unpaid' ? AttendanceRecord::STATUS_UNPAID_LEAVE : AttendanceRecord::STATUS_PAID_LEAVE;
        $start  = CarbonImmutable::parse($leave->starts_on);
        $end    = CarbonImmutable::parse($leave->ends_on);
        $taken  = AttendanceRecord::where('employee_id', $leave->employee_id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->pluck('work_date')->map(fn ($d) => $d->toDateString())->flip()->all();

        foreach ($this->workingDates($business, $start, $end) as $date) {
            if (isset($taken[$date])) {
                continue;
            }
            AttendanceRecord::create([
                'business_id'         => $business->id,
                'employee_id'         => $leave->employee_id,
                'leave_request_id'    => $leave->id,
                'work_date'           => $date,
                'status'              => $status,
                'source'              => 'leave',
                'notes'               => ucfirst($leave->leave_type).' leave',
                'recorded_by_user_id' => $leave->decided_by_user_id,
            ]);
        }
    }

    public function delete(LeaveRequest $leave): void
    {
        $leave->delete();
    }

    /**
     * Leave requests across the business for Attendance → Leave, newest first.
     *
     * @param  array{status?: ?string, q?: ?string, from?: ?string, to?: ?string}  $filters
     * @return Collection<int, LeaveRequest>
     */
    public function list(Business $business, array $filters, int $limit = 300): Collection
    {
        $q = trim((string) ($filters['q'] ?? ''));

        return LeaveRequest::query()
            ->where('business_id', $business->id)
            ->with(['employee:id,full_name,employee_id', 'recordedBy:id,name', 'decidedBy:id,name'])
            ->when($filters['status'] ?? null, fn ($w, $s) => $w->where('status', $s))
            ->when($filters['from'] ?? null, fn ($w, $d) => $w->whereDate('ends_on', '>=', $d))
            ->when($filters['to'] ?? null, fn ($w, $d) => $w->whereDate('starts_on', '<=', $d))
            ->when($q !== '', fn ($w) => $w->whereHas('employee', fn ($e) => $e
                ->where('full_name', 'like', "%{$q}%")
                ->orWhere('employee_id', 'like', "%{$q}%")))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** @return array{pending:int, on_leave_today:int} */
    public function counts(Business $business): array
    {
        $today = now()->toDateString();

        return [
            'pending' => LeaveRequest::where('business_id', $business->id)->where('status', LeaveRequest::STATUS_PENDING)->count(),
            'on_leave_today' => LeaveRequest::where('business_id', $business->id)
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->where('leave_type', '!=', LeaveRequest::TYPE_SHORT)
                ->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)
                ->distinct('employee_id')->count('employee_id'),
        ];
    }

    /** Number of days in [start, end] that are working days in the HR schedule and not business holidays. */
    public function workingDays(Business $business, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return count($this->workingDates($business, $start, $end));
    }

    /** @return list<string> Y-m-d dates in [start, end] that are working days and not business holidays */
    public function workingDates(Business $business, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $schedule = $this->workSettings->get($business)['work_schedule'];
        $holidays = Schema::hasTable('hr_business_holidays')
            ? HrBusinessHoliday::where('business_id', $business->id)
                ->whereDate('holiday_date', '>=', $start->toDateString())
                ->whereDate('holiday_date', '<=', $end->toDateString())
                ->pluck('holiday_date')->map(fn ($d) => $d->toDateString())->flip()->all()
            : [];

        $dates = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $key = strtolower($d->format('D'));
            if (($schedule[$key]['enabled'] ?? false) && ! isset($holidays[$d->toDateString()])) {
                $dates[] = $d->toDateString();
            }
        }

        return $dates;
    }

    /**
     * An approved/pending request that conflicts with the new one. Short leaves only clash with full/half days;
     * two half days on the same date are fine if they're different halves.
     */
    private function overlapping(Employee $employee, CarbonImmutable $start, CarbonImmutable $end, bool $isShort, bool $halfDay, ?string $period): ?LeaveRequest
    {
        $rows = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING])
            ->where('leave_type', '!=', LeaveRequest::TYPE_SHORT)
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->get();

        foreach ($rows as $row) {
            if ($halfDay && $row->is_half_day && $row->half_day_period !== ($period ?? 'am')) {
                continue;
            }
            if ($isShort && $row->is_half_day) {
                continue;
            }

            return $row;
        }

        return null;
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }
}
