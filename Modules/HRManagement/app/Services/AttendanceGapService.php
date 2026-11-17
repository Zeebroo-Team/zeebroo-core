<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\AttendanceRecord;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\LeaveRequest;

/**
 * Attendance → "Check Missing Attendance": working days where an employee has no attendance record
 * (e.g. not in the uploaded CSV) and no leave. HR decides per day: deduct casual / annual / sick leave,
 * record unpaid leave, mark absent (no pay), or skip.
 */
final class AttendanceGapService
{
    public const MAX_RANGE_DAYS = 62;

    /** Actions that create an approved leave request (and a linked attendance row). */
    public const LEAVE_ACTIONS = ['casual', 'annual', 'sick', 'other', 'unpaid'];

    public const ACTIONS = [...self::LEAVE_ACTIONS, 'absent', 'skip'];

    public function __construct(
        private readonly LeaveRequestService $leaveRequests,
        private readonly EmployeeLeaveBalanceService $balances,
        private readonly LeavePolicyService $leavePolicies,
    ) {}

    /**
     * Employees with missing working days in [from, to] (to is capped at today), each day with a suggested action:
     * casual while casual balance lasts, then annual, then unpaid; unpaid while on probation.
     *
     * @return list<array<string, mixed>>
     */
    public function find(Business $business, CarbonImmutable $from, CarbonImmutable $to, string $search = ''): array
    {
        $to = $to->min(CarbonImmutable::today());
        if ($to->lt($from)) {
            return [];
        }

        $workingDates = $this->leaveRequests->workingDates($business, $from, $to);
        if ($workingDates === []) {
            return [];
        }

        $search = trim($search);
        $employees = Employee::query()
            ->where('business_id', $business->id)
            ->with('jobTitle.leavePolicy')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")))
            ->orderBy('full_name')
            ->get();

        $recorded = AttendanceRecord::query()
            ->where('business_id', $business->id)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->get(['employee_id', 'work_date'])
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => $r->work_date->toDateString())->flip()->all());

        $leaves = LeaveRequest::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING])
            ->where('leave_type', '!=', LeaveRequest::TYPE_SHORT)
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->get(['employee_id', 'starts_on', 'ends_on'])
            ->groupBy('employee_id');

        $out = [];
        foreach ($employees as $employee) {
            $joined = $employee->date_of_joining ? CarbonImmutable::parse($employee->date_of_joining)->toDateString() : null;
            $hasRecord = $recorded->get($employee->id, []);
            $empLeaves = $leaves->get($employee->id, collect());

            $missing = array_values(array_filter($workingDates, function (string $date) use ($joined, $hasRecord, $empLeaves) {
                if ($joined !== null && $date < $joined) {
                    return false;
                }
                if (isset($hasRecord[$date])) {
                    return false;
                }

                return ! $empLeaves->contains(fn ($l) => $l->starts_on->toDateString() <= $date && $l->ends_on->toDateString() >= $date);
            }));

            if ($missing === []) {
                continue;
            }

            $out[] = $this->withSuggestions($business, $employee, $missing);
        }

        return $out;
    }

    /**
     * Apply HR's choice for each missing day, in date order so balance checks see earlier deductions.
     *
     * @param  list<array{employee_id:int, date:string, action:string}>  $items
     * @return array{applied:int, skipped:int, errors:list<array{employee_id:int, employee_name:?string, date:string, message:string}>}
     */
    public function resolve(Business $business, array $items, User $by, bool $allowOverride): array
    {
        usort($items, fn ($a, $b) => [$a['date'], $a['employee_id']] <=> [$b['date'], $b['employee_id']]);

        $employees = Employee::where('business_id', $business->id)
            ->whereIn('id', array_unique(array_column($items, 'employee_id')))
            ->get()->keyBy('id');

        $applied = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($items as $item) {
            $employee = $employees->get((int) $item['employee_id']);
            if ($employee === null || $item['action'] === 'skip') {
                $skipped++;
                continue;
            }

            try {
                if ($item['action'] === 'absent') {
                    $this->markAbsent($business, $employee, $item['date'], $by);
                } else {
                    $this->leaveRequests->create($business, $employee, [
                        'leave_type'     => $item['action'],
                        'starts_on'      => $item['date'],
                        'ends_on'        => $item['date'],
                        'status'         => LeaveRequest::STATUS_APPROVED,
                        'allow_override' => $allowOverride,
                        'note'           => 'No attendance record — marked from missing attendance check',
                    ], $by);
                }
                $applied++;
            } catch (ValidationException $e) {
                $errors[] = [
                    'employee_id'   => $employee->id,
                    'employee_name' => $employee->full_name,
                    'date'          => $item['date'],
                    'message'       => collect($e->errors())->flatten()->first() ?? 'Could not apply.',
                ];
            }
        }

        return ['applied' => $applied, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  list<string>  $missing  Y-m-d, ascending
     * @return array<string, mixed>
     */
    private function withSuggestions(Business $business, Employee $employee, array $missing): array
    {
        $policy       = $this->leavePolicies->resolveFor($business, $employee)['policy'];
        $eligibleFrom = $this->balances->eligibleFrom($employee, $policy)?->toDateString();
        $left         = [];   // year => [casual, annual] remaining, decremented as days are suggested

        $days = [];
        foreach ($missing as $date) {
            $year = (int) substr($date, 0, 4);
            if (! isset($left[$year])) {
                $types = $this->balances->yearlySummary($business, $employee, $year)['types'];
                $left[$year] = [
                    'casual' => $types['casual']['remaining'],
                    'annual' => $types['annual']['remaining'],
                ];
            }

            $suggest = 'unpaid';
            if ($eligibleFrom === null || $date >= $eligibleFrom) {
                foreach (['casual', 'annual'] as $type) {
                    // null remaining = no limit configured for this type
                    if ($left[$year][$type] === null || $left[$year][$type] >= 1) {
                        $suggest = $type;
                        if ($left[$year][$type] !== null) {
                            $left[$year][$type]--;
                        }
                        break;
                    }
                }
            }

            $days[] = ['date' => $date, 'suggested' => $suggest];
        }

        $summary = $this->balances->yearlySummary($business, $employee, (int) substr($missing[0], 0, 4))['types'];

        return [
            'employee_id'   => $employee->id,
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_id,
            'policy_name'   => $policy?->name,
            'eligible_from' => $eligibleFrom,
            'casual_left'   => $summary['casual']['remaining'],
            'annual_left'   => $summary['annual']['remaining'],
            'days'          => $days,
        ];
    }

    private function markAbsent(Business $business, Employee $employee, string $date, User $by): void
    {
        $exists = AttendanceRecord::where('employee_id', $employee->id)->whereDate('work_date', $date)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['date' => 'Attendance is already recorded for this day.']);
        }

        AttendanceRecord::create([
            'business_id'         => $business->id,
            'employee_id'         => $employee->id,
            'work_date'           => $date,
            'status'              => AttendanceRecord::STATUS_ABSENT,
            'source'              => 'absence_check',
            'notes'               => 'No attendance record — marked absent (no pay)',
            'recorded_by_user_id' => $by->id,
        ]);
    }
}
