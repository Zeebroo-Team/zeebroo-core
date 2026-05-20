<?php

namespace Modules\HRManagement\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\EmployeeAllowance;

class EmployeeService
{
    /** @return Collection<int, Employee> */
    public function listForBusiness(Business $business): Collection
    {
        return $business->employees()->with(['bank', 'department', 'jobTitle'])->get();
    }

    /** @param  array<string, mixed>  $data */
    public function create(Business $business, array $data): Employee
    {
        foreach (['epf_number', 'etf_number', 'tax_tin'] as $key) {
            if (($data[$key] ?? null) === '') {
                $data[$key] = null;
            }
        }

        $allowances = $data['allowances'] ?? [];
        unset($data['allowances']);

        foreach (['basic_salary', 'salary'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = round((float) $data[$key], 2);
            }
        }

        $employee = $business->employees()->create($data);

        if (is_array($allowances)) {
            $typeIds = $business->allowanceTypes()->pluck('id')->all();
            foreach ($typeIds as $typeId) {
                $raw = $allowances[(string) $typeId] ?? $allowances[$typeId] ?? null;
                if ($raw === null || $raw === '') {
                    continue;
                }
                $amount = round(max(0, (float) $raw), 2);
                if ($amount <= 0) {
                    continue;
                }
                $employee->employeeAllowances()->create([
                    'allowance_type_id' => $typeId,
                    'amount' => $amount,
                ]);
            }
        }

        return $employee;
    }

    /** Recompute monthly gross from basic salary plus allowance rows. */
    public function recalculateMonthlyGross(Employee $employee): void
    {
        $employee->refresh();
        $basic = round((float) ($employee->basic_salary ?? 0), 2);
        $allowSum = round((float) $employee->employeeAllowances()->sum('amount'), 2);
        $employee->forceFill(['salary' => round($basic + $allowSum, 2)])->save();
    }

    public function updateAllowanceAmount(Employee $employee, int $employeeAllowanceId, float $amount): void
    {
        $row = EmployeeAllowance::query()
            ->where('employee_id', $employee->id)
            ->whereKey($employeeAllowanceId)
            ->firstOrFail();

        $row->forceFill(['amount' => round(max(0, $amount), 2)])->save();

        $this->recalculateMonthlyGross($employee->fresh());
    }
}
