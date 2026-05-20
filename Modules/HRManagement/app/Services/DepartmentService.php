<?php

namespace Modules\HRManagement\Services;

use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;

class DepartmentService
{
    public function create(Business $business, string $name): Department
    {
        return $business->departments()->create([
            'name' => trim($name),
        ]);
    }

    public function rename(Business $business, Department $department, string $name): void
    {
        abort_unless((int) $department->business_id === (int) $business->id, 403);

        $department->fill(['name' => trim($name)])->save();
    }

    /**
     * @param  array<int, int|string>  $employeeIds
     */
    public function attachEmployees(Business $business, Department $department, array $employeeIds): int
    {
        abort_unless((int) $department->business_id === (int) $business->id, 403);

        $ids = collect($employeeIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();

        if ($ids === []) {
            return 0;
        }

        return Employee::query()
            ->where('business_id', $business->id)
            ->whereIn('id', $ids)
            ->where(fn ($query) => $query->whereNull('department_id')->orWhere('department_id', '!=', $department->id))
            ->update(['department_id' => $department->id]);
    }

    public function updateLeadership(Business $business, Department $department, ?int $headEmployeeId, ?int $coHeadEmployeeId): void
    {
        abort_unless((int) $department->business_id === (int) $business->id, 403);

        $department->fill([
            'head_employee_id' => $headEmployeeId,
            'co_head_employee_id' => $coHeadEmployeeId,
        ])->save();
    }

    public function updateSalaryRange(Business $business, Department $department, ?float $salaryRangeMin, ?float $salaryRangeMax): void
    {
        abort_unless((int) $department->business_id === (int) $business->id, 403);

        $department->fill([
            'salary_range_min' => $salaryRangeMin,
            'salary_range_max' => $salaryRangeMax,
        ])->save();
    }
}
