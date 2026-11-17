<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\AttendanceRecord;

/**
 * Attendance report for the desktop Attendance tab: records in a date range enriched with
 * late / early-leave / overtime minutes (measured against the business work schedule),
 * plus range totals, per-status counts and a per-employee summary.
 */
final class AttendanceReportService
{
    /** Minutes after the scheduled start before a check-in counts as late. */
    public const LATE_GRACE_MINUTES = 5;

    public const MAX_RANGE_DAYS = 366;

    public const MAX_ROWS = 500;

    /** Status filters the desktop can send — real statuses plus computed "issue" filters. */
    public const FILTERS = ['present', 'half_day', 'leave', 'paid_leave', 'unpaid_leave', 'absent', 'holiday', 'weekend', 'late', 'early', 'missing_out', 'overtime'];

    public function __construct(
        private readonly HrWorkSettingsService $workSettings,
    ) {}

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, q?: string, status?: string, department_id?: int|null, employee?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function report(Business $business, array $filters): array
    {
        $schedule = $this->workSettings->get($business)['work_schedule'];

        $query = AttendanceRecord::query()
            ->where('business_id', $business->id)
            ->whereBetween('work_date', [$filters['from']->toDateString(), $filters['to']->toDateString()])
            ->with([
                'employee:id,full_name,employee_id,department_id,job_title_id',
                'employee.department:id,name',
                'employee.jobTitle:id,name',
                'leaveRequest:id,leave_type,note',
                'recordedBy:id,name',
            ]);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->whereHas('employee', function ($q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }
        if (! empty($filters['employee'])) {
            $query->where('employee_id', $filters['employee']);
        }
        if (! empty($filters['department_id'])) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        $rows = $query->orderByDesc('work_date')->orderByDesc('id')->get()
            ->map(fn (AttendanceRecord $r) => $this->formatRow($r, $schedule));

        $status   = (string) ($filters['status'] ?? '');
        $filtered = $status === '' ? $rows : $rows->filter(fn (array $r) => $this->matches($r, $status))->values();

        $latest = AttendanceRecord::query()->where('business_id', $business->id)->max('work_date');

        return [
            'data'          => $filtered->take(self::MAX_ROWS)->values(),
            'total_count'   => $filtered->count(),
            'summary'       => $this->summary($rows),
            'status_counts' => $this->statusCounts($rows),
            'employees'     => $this->employeeSummary($rows),
            'range'         => ['from' => $filters['from']->toDateString(), 'to' => $filters['to']->toDateString()],
            'latest_date'   => $latest ? CarbonImmutable::parse($latest)->toDateString() : null,
            'late_grace_minutes' => self::LATE_GRACE_MINUTES,
            'work_days'     => array_keys(array_filter($schedule, fn (array $d) => (bool) $d['enabled'])),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $schedule
     * @return array<string, mixed>
     */
    private function formatRow(AttendanceRecord $r, array $schedule): array
    {
        $date = CarbonImmutable::parse($r->work_date)->startOfDay();
        $day  = strtolower($date->format('D'));
        $sch  = $schedule[$day] ?? ['enabled' => false, 'start' => null, 'end' => null];

        $in  = $r->check_in_at ? CarbonImmutable::parse($r->check_in_at) : null;
        $out = $r->check_out_at ? CarbonImmutable::parse($r->check_out_at) : null;

        $expected = null;
        $late = $early = $overtime = 0;
        $worksToday = in_array($r->status, [AttendanceRecord::STATUS_PRESENT, AttendanceRecord::STATUS_HALF_DAY], true);

        if ($sch['enabled'] && $sch['start'] && $sch['end']) {
            $start = $date->setTimeFromTimeString($sch['start']);
            $end   = $date->setTimeFromTimeString($sch['end']);
            // worked_minutes is the raw check-in → check-out span (breaks included), so compare it to the full shift.
            $expected = (int) $start->diffInMinutes($end);

            if ($worksToday && $in && $in->greaterThan($start->addMinutes(self::LATE_GRACE_MINUTES))) {
                $late = (int) $start->diffInMinutes($in);
            }
            if ($worksToday && $out && $out->lessThan($end) && $r->status === AttendanceRecord::STATUS_PRESENT) {
                $early = (int) $out->diffInMinutes($end);
            }
            if ($worksToday && $r->worked_minutes !== null && $r->worked_minutes > $expected) {
                $overtime = $r->worked_minutes - $expected;
            }
        } elseif ($worksToday && $r->worked_minutes) {
            // Worked on a non-working day: all of it is overtime.
            $overtime = (int) $r->worked_minutes;
        }

        $employee = $r->employee;

        return [
            'id'                  => $r->id,
            'employee_key'        => $r->employee_id,
            'employee_id'         => $employee?->employee_id,
            'employee_name'       => $employee?->full_name,
            'department'          => $employee?->department?->name,
            'job_title'           => $employee?->jobTitle?->name,
            'work_date'           => $date->toDateString(),
            'weekday'             => $date->format('l'),
            'status'              => $r->status,
            'check_in_at'         => $in?->format('Y-m-d H:i'),
            'check_out_at'        => $out?->format('Y-m-d H:i'),
            'worked_minutes'      => $r->worked_minutes,
            'scheduled_start'     => $sch['enabled'] ? $sch['start'] : null,
            'scheduled_end'       => $sch['enabled'] ? $sch['end'] : null,
            'expected_minutes'    => $expected,
            'late_minutes'        => $late,
            'early_leave_minutes' => $early,
            'overtime_minutes'    => $overtime,
            'missing_checkout'    => $worksToday && $in !== null && $out === null,
            'missing_checkin'     => $worksToday && $in === null && $out !== null,
            'leave_type'          => $r->leaveRequest?->leave_type,
            'leave_note'          => $r->leaveRequest?->note,
            'source'              => $r->source,
            'notes'               => $r->notes,
            'recorded_by'         => $r->recordedBy?->name,
            'updated_at'          => $r->updated_at?->format('Y-m-d H:i'),
        ];
    }

    /** @param  array<string, mixed>  $r */
    private function matches(array $r, string $filter): bool
    {
        return match ($filter) {
            'leave'       => in_array($r['status'], [AttendanceRecord::STATUS_PAID_LEAVE, AttendanceRecord::STATUS_UNPAID_LEAVE], true),
            'late'        => $r['late_minutes'] > 0,
            'early'       => $r['early_leave_minutes'] > 0,
            'missing_out' => $r['missing_checkout'],
            'overtime'    => $r['overtime_minutes'] > 0,
            default       => $r['status'] === $filter,
        };
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    private function statusCounts(Collection $rows): array
    {
        $out = [];
        foreach (self::FILTERS as $f) {
            $out[$f] = $rows->filter(fn (array $r) => $this->matches($r, $f))->count();
        }
        $out['all'] = $rows->count();

        return $out;
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    private function summary(Collection $rows): array
    {
        $worked = $rows->filter(fn (array $r) => (int) $r['worked_minutes'] > 0);

        return [
            'records'            => $rows->count(),
            'employees'          => $rows->pluck('employee_key')->unique()->count(),
            'days'               => $rows->pluck('work_date')->unique()->count(),
            'worked_minutes'     => (int) $worked->sum('worked_minutes'),
            'avg_worked_minutes' => $worked->isEmpty() ? 0 : (int) round($worked->avg('worked_minutes')),
            'overtime_minutes'   => (int) $rows->sum('overtime_minutes'),
            'late_minutes'       => (int) $rows->sum('late_minutes'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function employeeSummary(Collection $rows): array
    {
        return $rows->groupBy('employee_key')->map(function (Collection $g): array {
            $first   = $g->first();
            $present = $g->where('status', AttendanceRecord::STATUS_PRESENT)->count();
            $half    = $g->where('status', AttendanceRecord::STATUS_HALF_DAY)->count();
            $paid    = $g->where('status', AttendanceRecord::STATUS_PAID_LEAVE)->count();
            $unpaid  = $g->where('status', AttendanceRecord::STATUS_UNPAID_LEAVE)->count();
            $absent  = $g->where('status', AttendanceRecord::STATUS_ABSENT)->count();
            $worked  = $g->filter(fn (array $r) => (int) $r['worked_minutes'] > 0);
            $tracked = $present + $half + $paid + $unpaid + $absent;

            return [
                'employee_key'       => $first['employee_key'],
                'employee_id'        => $first['employee_id'],
                'employee_name'      => $first['employee_name'],
                'department'         => $first['department'],
                'job_title'          => $first['job_title'],
                'present_days'       => $present + $half * 0.5,
                'half_days'          => $half,
                'paid_leave_days'    => $paid,
                'unpaid_leave_days'  => $unpaid,
                'absent_days'        => $absent,
                'late_count'         => $g->filter(fn (array $r) => $r['late_minutes'] > 0)->count(),
                'late_minutes'       => (int) $g->sum('late_minutes'),
                'early_count'        => $g->filter(fn (array $r) => $r['early_leave_minutes'] > 0)->count(),
                'missing_checkout'   => $g->where('missing_checkout', true)->count(),
                'worked_minutes'     => (int) $worked->sum('worked_minutes'),
                'avg_worked_minutes' => $worked->isEmpty() ? 0 : (int) round($worked->avg('worked_minutes')),
                'overtime_minutes'   => (int) $g->sum('overtime_minutes'),
                'attendance_rate'    => $tracked > 0 ? round(($present + $half * 0.5 + $paid) / $tracked * 100, 1) : null,
                'last_date'          => $g->max('work_date'),
            ];
        })->sortBy('employee_name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }
}
