<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;

/** Employees with a next calendar birthday within a rolling window from today. */
final class HrUpcomingBirthdaysService
{
    public const DEFAULT_WINDOW_DAYS = 30;

    /**
     * @return list<array{employee: Employee, next_on: Carbon, days_until: int}>
     */
    public function upcomingWithinDays(Business $business, int $withinDays = self::DEFAULT_WINDOW_DAYS): array
    {
        if ($withinDays < 1) {
            $withinDays = self::DEFAULT_WINDOW_DAYS;
        }

        $today = Carbon::today();
        $end = $today->copy()->addDays($withinDays);

        $employees = Employee::query()
            ->where('business_id', $business->id)
            ->whereNotNull('date_of_birth')
            ->with('department')
            ->orderBy('full_name')
            ->get();

        $items = [];
        foreach ($employees as $employee) {
            $birth = $employee->date_of_birth;
            if ($birth === null) {
                continue;
            }
            $birth = Carbon::parse($birth)->startOfDay();
            $next = $this->nextBirthdayOnOrAfter($birth, $today);
            if ($next->greaterThan($end)) {
                continue;
            }
            $daysUntil = (int) $today->copy()->startOfDay()->diffInDays($next->copy()->startOfDay());
            $items[] = [
                'employee' => $employee,
                'next_on' => $next,
                'days_until' => $daysUntil,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return $a['next_on']->timestamp <=> $b['next_on']->timestamp;
        });

        return $items;
    }

    private function nextBirthdayOnOrAfter(CarbonInterface $birthDate, CarbonInterface $fromDay): Carbon
    {
        $year = (int) $fromDay->year;
        $month = (int) $birthDate->month;
        $day = (int) $birthDate->day;
        $next = $this->birthdayInYear($year, $month, $day);

        if ($next->lessThan($fromDay)) {
            $next = $this->birthdayInYear($year + 1, $month, $day);
        }

        return $next;
    }

    private function birthdayInYear(int $year, int $month, int $dayOfMonth): Carbon
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $safeDay = min($dayOfMonth, $daysInMonth);

        return Carbon::createFromDate($year, $month, $safeDay)->startOfDay();
    }
}
