<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Carbon\CarbonImmutable;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\LeaveRequest;

/** Policy entitlements vs approved/pending usage (calendar overlap) for employee leave UI. */
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

    /**
     * @return array{
     *   year:int,
     *   types: array<string, array{
     *     entitlement: int|null,
     *     approved_days: int,
     *     pending_days: int,
     *     remaining: int|null,
     *     accent: string
     *   }>,
     * }
     */
    public function yearlySummary(Business $business, Employee $employee, ?int $year = null): array
    {
        $year = $year ?? (int) now()->year;
        $startBound = CarbonImmutable::createFromDate($year, 1, 1)->toDateString();
        $endBound = CarbonImmutable::createFromDate($year, 12, 31)->toDateString();

        $counts = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('business_id', $business->id)
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING])
            ->whereDate('starts_on', '<=', $endBound)
            ->whereDate('ends_on', '>=', $startBound)
            ->get(['leave_type', 'status', 'starts_on', 'ends_on']);

        $approved = [];
        $pending = [];
        foreach (LeaveRequest::LEAVE_TYPES as $t) {
            $approved[$t] = 0;
            $pending[$t] = 0;
        }

        foreach ($counts as $lr) {
            $days = $this->overlapCalendarDaysInclusive(
                CarbonImmutable::parse((string) $lr->starts_on)->startOfDay(),
                CarbonImmutable::parse((string) $lr->ends_on)->startOfDay(),
                $year
            );
            if ($days < 1) {
                continue;
            }

            $type = $lr->leave_type;
            if (! isset($approved[$type])) {
                continue;
            }

            if ($lr->status === LeaveRequest::STATUS_PENDING) {
                $pending[$type] += $days;
            } else {
                $approved[$type] += $days;
            }
        }

        $types = [];
        foreach (LeaveRequest::LEAVE_TYPES as $lt) {
            $entitlement = $this->entitlementForType($business, $lt);
            $ap = (int) ($approved[$lt] ?? 0);
            $pe = (int) ($pending[$lt] ?? 0);
            $remaining = null;
            if ($entitlement !== null) {
                $remaining = max(0, $entitlement - $ap - $pe);
            }

            $types[$lt] = [
                'entitlement' => $entitlement,
                'approved_days' => $ap,
                'pending_days' => $pe,
                'remaining' => $remaining,
                'accent' => self::ACCENT_BY_TYPE[$lt] ?? 'var(--primary)',
            ];
        }

        return ['year' => $year, 'types' => $types];
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

    private function entitlementForType(Business $business, string $type): ?int
    {
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
}
