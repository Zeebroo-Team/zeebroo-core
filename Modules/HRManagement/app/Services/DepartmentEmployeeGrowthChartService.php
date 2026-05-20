<?php

namespace Modules\HRManagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;

class DepartmentEmployeeGrowthChartService
{
    private const MAX_MONTHS = 60;

    /**
     * Cumulative headcount per department at each month-end (by hire date and current assignment).
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>, hasData: bool, note: string}
     */
    public function build(Business $business): array
    {
        $note = __('Each line is cumulative staff in that department today, counted from their hire date through each month. Department moves are not history-tracked—only the current assignment is used.');

        $departments = Department::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        $employees = Employee::query()
            ->where('business_id', $business->id)
            ->whereNotNull('date_of_joining')
            ->get(['id', 'department_id', 'date_of_joining']);

        if ($employees->isEmpty()) {
            return [
                'labels' => [],
                'datasets' => [],
                'hasData' => false,
                'note' => $note,
            ];
        }

        $minJoin = $employees->min('date_of_joining');
        if (! $minJoin instanceof Carbon) {
            return [
                'labels' => [],
                'datasets' => [],
                'hasData' => false,
                'note' => $note,
            ];
        }

        $months = $this->monthsGridFromEarliestJoin($minJoin);

        $labels = array_map(static fn (Carbon $m) => $m->format('M Y'), $months);

        $datasets = [];
        $idx = 0;

        foreach ($departments as $dept) {
            $data = $this->cumulativeSeriesForDepartment($employees, (int) $dept->id, $months);
            if (array_sum($data) === 0) {
                continue;
            }
            $color = $this->colorForIndex($idx);
            $idx++;
            $datasets[] = array_merge([
                'label' => $dept->name,
                'data' => $data,
                'fill' => false,
                'tension' => 0.3,
                'borderWidth' => 2,
                'pointRadius' => 3,
                'pointHoverRadius' => 5,
            ], $color);
        }

        $unassigned = $this->cumulativeSeriesUnassigned($employees, $months);
        if (array_sum($unassigned) > 0) {
            $color = $this->colorForIndex($idx);
            $datasets[] = array_merge([
                'label' => __('Unassigned'),
                'data' => $unassigned,
                'fill' => false,
                'borderDash' => [6, 4],
                'tension' => 0.3,
                'borderWidth' => 2,
                'pointRadius' => 3,
                'pointHoverRadius' => 5,
            ], $color);
        }

        $hasChart = $datasets !== [];

        if (! $hasChart && $employees->isNotEmpty()) {
            $totalLine = $this->cumulativeTotalHeadcount($employees, $months);
            $datasets[] = array_merge([
                'label' => __('All employees (no department breakdown)'),
                'data' => $totalLine,
                'fill' => false,
                'tension' => 0.3,
                'borderWidth' => 2,
            ], $this->colorForIndex(0));
            $hasChart = true;
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
            'hasData' => $hasChart,
            'note' => $note,
        ];
    }

    /**
     * Single line: cumulative company headcount by month-end (hire date only).
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>, hasData: bool, note: string}
     */
    public function buildCompanyHeadcountSeries(Business $business): array
    {
        $note = __('Cumulative headcount: each point is how many people had joined on or before that month-end (by hire date).');

        $employees = Employee::query()
            ->where('business_id', $business->id)
            ->whereNotNull('date_of_joining')
            ->get(['id', 'date_of_joining']);

        if ($employees->isEmpty()) {
            return [
                'labels' => [],
                'datasets' => [],
                'hasData' => false,
                'note' => $note,
            ];
        }

        $minJoin = $employees->min('date_of_joining');
        if (! $minJoin instanceof Carbon) {
            return [
                'labels' => [],
                'datasets' => [],
                'hasData' => false,
                'note' => $note,
            ];
        }

        $months = $this->monthsGridFromEarliestJoin($minJoin);
        $labels = array_map(static fn (Carbon $m) => $m->format('M Y'), $months);
        $data = $this->cumulativeTotalHeadcount($employees, $months);

        if (max($data) === 0) {
            return [
                'labels' => $labels,
                'datasets' => [],
                'hasData' => false,
                'note' => $note,
            ];
        }

        $color = $this->colorForIndex(0);

        return [
            'labels' => $labels,
            'datasets' => [
                array_merge([
                    'label' => __('Total employees'),
                    'data' => $data,
                    'fill' => true,
                    'tension' => 0.35,
                    'borderWidth' => 2.5,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                ], $color),
            ],
            'hasData' => true,
            'note' => $note,
        ];
    }

    /**
     * Cumulative monthly headcount for one department roster (same rules as workspace chart).
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>, hasData: bool, note: string}
     */
    public function buildForDepartment(Business $business, Department $department): array
    {
        if ((int) $department->business_id !== (int) $business->id) {
            return [
                'labels' => [],
                'datasets' => [],
                'hasData' => false,
                'note' => '',
            ];
        }

        $note = __('Cumulative (:dept): staff who are assigned here today are counted back from hire date month by month. Department moves aren’t reconstructed historically.', [
            'dept' => $department->name,
        ]);

        /** @var Collection<int, Employee> $employees */
        $employees = Employee::query()
            ->where('business_id', $business->id)
            ->where('department_id', $department->id)
            ->whereNotNull('date_of_joining')
            ->get(['id', 'department_id', 'date_of_joining']);

        if ($employees->isEmpty()) {
            return [
                'labels' => [],
                'datasets' => [],
                'hasData' => false,
                'note' => $note,
            ];
        }

        $minJoin = $employees->min('date_of_joining');
        if (! $minJoin instanceof Carbon) {
            return [
                'labels' => [],
                'datasets' => [],
                'hasData' => false,
                'note' => $note,
            ];
        }

        $months = $this->monthsGridFromEarliestJoin($minJoin);
        $labels = array_map(static fn (Carbon $m) => $m->format('M Y'), $months);
        $data = $this->cumulativeSeriesForDepartment($employees, (int) $department->id, $months);

        if (array_sum($data) === 0) {
            return [
                'labels' => $labels,
                'datasets' => [],
                'hasData' => false,
                'note' => $note,
            ];
        }

        $color = $this->colorForIndex(0);

        return [
            'labels' => $labels,
            'datasets' => [
                array_merge([
                    'label' => $department->name,
                    'data' => $data,
                    'fill' => true,
                    'tension' => 0.3,
                    'borderWidth' => 2,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                ], $color),
            ],
            'hasData' => true,
            'note' => $note,
        ];
    }

    /** @return list<Carbon> */
    protected function monthsGridFromEarliestJoin(Carbon $minJoin): array
    {
        $start = $minJoin->copy()->startOfMonth();
        $endMonth = Carbon::now()->startOfMonth();

        if ($start->gt($endMonth)) {
            $start = $endMonth->copy();
        }

        $months = [];
        $cursor = $start->copy();
        while ($cursor->lte($endMonth)) {
            $months[] = $cursor->copy();
            $cursor->addMonthNoOverflow();
        }

        if (count($months) > self::MAX_MONTHS) {
            $months = array_slice($months, -self::MAX_MONTHS);
        }

        return $months;
    }

    /** @param  Collection<int, Employee>  $employees */
    /** @param  list<Carbon>  $months */
    /** @return list<int> */
    private function cumulativeSeriesForDepartment(Collection $employees, int $departmentId, array $months): array
    {
        $out = [];
        foreach ($months as $month) {
            $cutoff = $month->copy()->endOfMonth();
            $count = $employees->filter(static function (Employee $e) use ($departmentId, $cutoff): bool {
                if ((int) $e->department_id !== $departmentId) {
                    return false;
                }
                $joined = $e->date_of_joining;

                return $joined instanceof Carbon && $joined->lte($cutoff);
            })->count();
            $out[] = $count;
        }

        return $out;
    }

    /** @param  Collection<int, Employee>  $employees */
    /** @param  list<Carbon>  $months */
    /** @return list<int> */
    private function cumulativeSeriesUnassigned(Collection $employees, array $months): array
    {
        $out = [];
        foreach ($months as $month) {
            $cutoff = $month->copy()->endOfMonth();
            $count = $employees->filter(static function (Employee $e) use ($cutoff): bool {
                if ($e->department_id !== null) {
                    return false;
                }
                $joined = $e->date_of_joining;

                return $joined instanceof Carbon && $joined->lte($cutoff);
            })->count();
            $out[] = $count;
        }

        return $out;
    }

    /** @param  Collection<int, Employee>  $employees */
    /** @param  list<Carbon>  $months */
    /** @return list<int> */
    protected function cumulativeTotalHeadcount(Collection $employees, array $months): array
    {
        $out = [];
        foreach ($months as $month) {
            $cutoff = $month->copy()->endOfMonth();
            $count = $employees->filter(static function (Employee $e) use ($cutoff): bool {
                $joined = $e->date_of_joining;

                return $joined instanceof Carbon && $joined->lte($cutoff);
            })->count();
            $out[] = $count;
        }

        return $out;
    }

    /** @return array{borderColor: string, backgroundColor: string} */
    protected function colorForIndex(int $index): array
    {
        $hue = ($index * 53) % 360;

        return [
            'borderColor' => "hsla({$hue}, 72%, 58%, .95)",
            'backgroundColor' => "hsla({$hue}, 72%, 58%, .12)",
        ];
    }
}
