<?php

namespace Modules\HRManagement\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Account\Models\Bill;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Department;

class DepartmentCostCenterService
{
    /**
     * Bill amounts use {@see Bill::$recurring_cost}. Unassigned bills (no department_id) are split evenly across all departments for this business.
     *
     * @return array{
     *     available: bool,
     *     department_count: int,
     *     currency: string,
     *     unassigned_bills_total: float,
     *     share_per_department: float,
     *     rows: list<array{department: Department, assigned_total: float, unallocated_share: float, cost_center_total: float}>,
     *     totals: array{assigned_sum: float, unassigned_total: float, blended_sum: float}
     * }
     */
    public function build(Business $business): array
    {
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        if (! Schema::hasTable('bills') || ! Schema::hasColumn('bills', 'department_id')) {
            return [
                'available' => false,
                'department_count' => 0,
                'currency' => $currency,
                'unassigned_bills_total' => 0.0,
                'share_per_department' => 0.0,
                'rows' => [],
                'totals' => [
                    'assigned_sum' => 0.0,
                    'unassigned_total' => 0.0,
                    'blended_sum' => 0.0,
                ],
            ];
        }

        $departments = Department::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $departmentCount = $departments->count();

        $unassignedTotal = (float) Bill::query()
            ->where('business_id', $business->id)
            ->whereNull('department_id')
            ->sum('recurring_cost');

        $assignedSums = Bill::query()
            ->where('business_id', $business->id)
            ->whereNotNull('department_id')
            ->selectRaw('department_id, COALESCE(SUM(recurring_cost), 0) as total')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        $sharePerDept = $departmentCount > 0 ? $unassignedTotal / $departmentCount : 0.0;

        $rows = [];
        foreach ($departments as $dept) {
            $assigned = round((float) ($assignedSums[$dept->id] ?? 0), 2);
            $share = round($sharePerDept, 2);

            $rows[] = [
                'department' => $dept,
                'assigned_total' => $assigned,
                'unallocated_share' => $share,
                'cost_center_total' => round($assigned + $share, 2),
            ];
        }

        $assignedSumDb = round((float) Bill::query()
            ->where('business_id', $business->id)
            ->whereNotNull('department_id')
            ->sum('recurring_cost'), 2);

        return [
            'available' => true,
            'department_count' => $departmentCount,
            'currency' => $currency,
            'unassigned_bills_total' => round($unassignedTotal, 2),
            'share_per_department' => round($sharePerDept, 2),
            'rows' => $rows,
            'totals' => [
                'assigned_sum' => $assignedSumDb,
                'unassigned_total' => round($unassignedTotal, 2),
                'blended_sum' => round($assignedSumDb + $unassignedTotal, 2),
            ],
        ];
    }
}
