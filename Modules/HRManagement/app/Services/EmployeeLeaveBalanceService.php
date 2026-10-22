<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Carbon\CarbonImmutable;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\LeavePolicy;
use Modules\HRManagement\Models\LeaveRequest;

/**
 * Policy entitlements vs approved/pending usage for employee leave UI.
 * Usage is the request's stored working-day count (`days`, 0.5 for half days); legacy rows without it
 * fall back to their calendar-day overlap with the year.
 */
final class EmployeeLeaveBalanceService
{
    /** Accent CSS values for dashboard cards keyed by leave type. */
    private const ACCENT_BY_TYPE = [
        'annual' => '#0d9488',
        'casual' => '#0284c7',
        'sick' => '#e11d48',
        'unpaid' => '#64748b',
        'other' => '#7c3aed',
    ];

    public function __construct(private readonly LeavePolicyService $leavePolicies) {}

    /**
     * @return array{
     *   year:int,
     *   policy: ?LeavePolicy,
     *   policy_source: 'position'|'default'|'none',
     *   types: array<string, array{
     *     entitlement: float|int|null,
     *     base_entitlement: int|null,
     *     carried_forward: float|int,
     *     approved_days: float,
     *     pending_days: float,
     *     remaining: float|null,
     *     accent: string
     *   }>,
     * }
     */
    public function yearlySummary(Business $business, Employee $employee, ?int $year = null): array
    {
        $year = $year ?? (int) now()->year;
        ['policy' => $policy, 'source' => $policySource] = $this->leavePolicies->resolveFor($business, $employee);

        [$approved, $pending] = $this->usedDaysByType($business, $employee, $year);
        $carried = $this->carriedForwardAnnual($business, $employee, $policy, $year);

        $types = [];
        foreach (LeaveRequest::LEAVE_TYPES as $lt) {
            $base = $this->entitlementForType($business, $policy, $lt);
            $carry = $lt === 'annual' ? $carried : 0;
            $entitlement = $base === null ? null : $base + $carry;
            $ap = $approved[$lt];
            $pe = $pending[$lt];

            $types[$lt] = [
                'entitlement' => $entitlement,
                'base_entitlement' => $base,
                'carried_forward' => $carry,
                'approved_days' => $ap,
                'pending_days' => $pe,
                'remaining' => $entitlement === null ? null : max(0, $entitlement - $ap - $pe),
                'accent' => self::ACCENT_BY_TYPE[$lt] ?? 'var(--primary)',
            ];
        }

        return ['year' => $year, 'policy' => $policy, 'policy_source' => $policySource, 'types' => $types];
    }

    /**
     * Unused annual leave from last year moved into $year, capped by the rule's carry_forward_max_days.
     * Uses the employee's current rule for last year's entitlement; nothing carries if they joined after last year.
     */
    public function carriedForwardAnnual(Business $business, Employee $employee, ?LeavePolicy $policy, int $year): float
    {
        $cap = (int) ($policy?->carry_forward_max_days ?? 0);
        if ($cap < 1) {
            return 0;
        }
        if ($employee->date_of_joining !== null
            && CarbonImmutable::parse($employee->date_of_joining)->year >= $year) {
            return 0;
        }

        [$approvedPrev] = $this->usedDaysByType($business, $employee, $year - 1);
        $unused = $policy->annual_leave_days - $approvedPrev['annual'];

        return (float) min($cap, max(0, $unused));
    }

    /**
     * Short leaves (approved + pending) taken in the given month.
     *
     * @return array{allowed:int, used:int, max_minutes:int}|null  null when the rule has no short leave
     */
    public function shortLeaveUsage(Employee $employee, ?LeavePolicy $policy, CarbonImmutable $month, ?int $ignoreId = null): ?array
    {
        if ($policy === null || ! $policy->short_leave_enabled) {
            return null;
        }

        $used = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type', LeaveRequest::TYPE_SHORT)
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING])
            ->whereDate('starts_on', '>=', $month->startOfMonth()->toDateString())
            ->whereDate('starts_on', '<=', $month->endOfMonth()->toDateString())
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->count();

        return [
            'allowed'     => (int) $policy->short_leaves_per_month,
            'used'        => $used,
            'max_minutes' => (int) $policy->short_leave_max_minutes,
        ];
    }

    /**
     * Most recent leave requests (any status), newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function history(Business $business, Employee $employee, int $limit = 100): array
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('business_id', $business->id)
            ->with(['recordedBy:id,name', 'decidedBy:id,name'])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (LeaveRequest $lr) => $this->formatRequest($lr))
            ->all();
    }

    /** @return array<string, mixed> */
    public function formatRequest(LeaveRequest $lr): array
    {
        return [
            'id'                  => $lr->id,
            'employee_id'         => $lr->employee_id,
            'leave_type'          => $lr->leave_type,
            'starts_on'           => $lr->starts_on->toDateString(),
            'ends_on'             => $lr->ends_on->toDateString(),
            'days'                => $lr->isShort() ? 0 : $this->requestDays($lr),
            'is_half_day'         => $lr->is_half_day,
            'half_day_period'     => $lr->half_day_period,
            'short_leave_start'   => $lr->short_leave_start ? substr((string) $lr->short_leave_start, 0, 5) : null,
            'short_leave_minutes' => $lr->short_leave_minutes,
            'status'              => $lr->status,
            'note'                => $lr->note,
            'recorded_by'         => $lr->recordedBy?->name,
            'decided_by'          => $lr->decidedBy?->name,
            'decided_at'          => $lr->decided_at?->toDateTimeString(),
            'created_at'          => $lr->created_at?->toDateTimeString(),
        ];
    }

    /** Date the employee may start taking leave under the rule's probation months; null when no wait applies. */
    public function eligibleFrom(Employee $employee, ?LeavePolicy $policy): ?CarbonImmutable
    {
        if ($policy === null || $policy->eligible_after_months < 1 || $employee->date_of_joining === null) {
            return null;
        }

        return CarbonImmutable::parse($employee->date_of_joining)->addMonthsNoOverflow($policy->eligible_after_months);
    }

    /** The employee's leave rule wins; legacy hr.leave.* settings are the fallback when the business has none. */
    public function entitlementForType(Business $business, ?LeavePolicy $policy, string $type): ?int
    {
        if ($policy !== null) {
            return match ($type) {
                'annual' => $policy->annual_leave_days,
                'casual' => $policy->casual_leave_days,
                'sick'   => $policy->sick_leave_days,
                default  => null,
            };
        }

        [$key, $default] = match ($type) {
            'annual' => ['hr.leave.annual_days', 14],
            'casual' => ['hr.leave.casual_days', 7],
            'sick' => ['hr.leave.sick_days', 14],
            default => [null, null],
        };

        if ($key === null) {
            return null;
        }

        $v = get_settings($key, $default, $business);
        if ($v !== null && $v !== '' && is_numeric($v)) {
            $n = (int) round((float) $v);

            return $n >= 0 ? $n : null;
        }

        return null;
    }

    /**
     * Approved and pending day usage per day-based leave type within a calendar year.
     *
     * @return array{0: array<string, float>, 1: array<string, float>}
     */
    private function usedDaysByType(Business $business, Employee $employee, int $year): array
    {
        $approved = array_fill_keys(LeaveRequest::LEAVE_TYPES, 0.0);
        $pending = $approved;

        $rows = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('business_id', $business->id)
            ->whereIn('leave_type', LeaveRequest::LEAVE_TYPES)
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING])
            ->whereDate('starts_on', '<=', "$year-12-31")
            ->whereDate('ends_on', '>=', "$year-01-01")
            ->get();

        foreach ($rows as $lr) {
            // New requests never cross a year boundary, so their stored days belong to their start year.
            $days = $lr->days !== null
                ? ((int) $lr->starts_on->year === $year ? (float) $lr->days : 0.0)
                : (float) $this->overlapCalendarDaysInclusive(
                    CarbonImmutable::parse($lr->starts_on)->startOfDay(),
                    CarbonImmutable::parse($lr->ends_on)->startOfDay(),
                    $year
                );
            if ($days <= 0) {
                continue;
            }

            if ($lr->status === LeaveRequest::STATUS_PENDING) {
                $pending[$lr->leave_type] += $days;
            } else {
                $approved[$lr->leave_type] += $days;
            }
        }

        return [$approved, $pending];
    }

    private function requestDays(LeaveRequest $lr): float
    {
        return $lr->days ?? (float) ($lr->starts_on->diffInDays($lr->ends_on) + 1);
    }

    /** Inclusive overlapping calendar-day count clipped to [$year‑01‑01 … $year‑12‑31]. */
    private function overlapCalendarDaysInclusive(
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
        int $year,
    ): int {
        if ($rangeEnd->lt($rangeStart)) {
            return 0;
        }

        $yearStart = CarbonImmutable::createFromDate($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::createFromDate($year, 12, 31)->startOfDay();
        $segStart = $rangeStart->greaterThan($yearStart) ? $rangeStart : $yearStart;
        $segEnd = $rangeEnd->lessThan($yearEnd) ? $rangeEnd : $yearEnd;
        if ($segStart->greaterThan($segEnd)) {
            return 0;
        }

        return (int) ($segStart->diffInDays($segEnd) + 1);
    }
}
