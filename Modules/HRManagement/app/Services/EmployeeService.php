<?php

namespace Modules\HRManagement\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
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

    /**
     * Fields that can be edited one at a time from the employee profile (web + desktop).
     *
     * @return list<string>
     */
    public function patchableFields(): array
    {
        return [
            'full_name',
            'date_of_birth',
            'nic_passport_number',
            'permanent_address',
            'current_address',
            'phone_number',
            'personal_email',
            'employee_id',
            'job_title_id',
            'department_id',
            'date_of_joining',
            'employment_type',
            'emergency_contact_name',
            'emergency_contact_relationship',
            'emergency_contact_phone',
            'bank_account_holder_name',
            'bank_id',
            'bank_branch',
            'bank_account_number',
            'epf_number',
            'etf_number',
            'tax_tin',
            'basic_salary',
            'employee_allowance',
        ];
    }

    /** @return array<string, mixed> */
    public function patchFieldRules(Business $business, Employee $employee, string $field): array
    {
        $nicUnique = Rule::unique('hr_employees', 'nic_passport_number')
            ->where(fn ($query) => $query->where('business_id', $business->id))
            ->ignore($employee->id);

        $employeeCodeUnique = Rule::unique('hr_employees', 'employee_id')
            ->where(fn ($query) => $query->where('business_id', $business->id))
            ->ignore($employee->id);

        return match ($field) {
            'full_name' => ['full_name' => ['required', 'string', 'max:255']],
            'date_of_birth' => ['date_of_birth' => ['required', 'date', 'before:today']],
            'nic_passport_number' => ['nic_passport_number' => ['required', 'string', 'max:64', $nicUnique]],
            'permanent_address' => ['permanent_address' => ['required', 'string', 'max:5000']],
            'current_address' => ['current_address' => ['required', 'string', 'max:5000']],
            'phone_number' => ['phone_number' => ['required', 'string', 'max:40']],
            'personal_email' => ['personal_email' => ['required', 'email', 'max:255']],
            'employee_id' => ['employee_id' => ['required', 'string', 'max:64', $employeeCodeUnique]],
            'job_title_id' => [
                'job_title_id' => [
                    'required',
                    'integer',
                    Rule::exists('hr_job_titles', 'id')->where(fn ($query) => $query->where('business_id', $business->id)),
                ],
            ],
            'department_id' => [
                'department_id' => [
                    'required',
                    'integer',
                    Rule::exists('hr_departments', 'id')->where(fn ($query) => $query->where('business_id', $business->id)),
                ],
            ],
            'date_of_joining' => ['date_of_joining' => ['required', 'date']],
            'employment_type' => ['employment_type' => ['required', 'string', Rule::in(Employee::EMPLOYMENT_TYPES)]],
            'emergency_contact_name' => ['emergency_contact_name' => ['required', 'string', 'max:255']],
            'emergency_contact_relationship' => ['emergency_contact_relationship' => ['required', 'string', 'max:120']],
            'emergency_contact_phone' => ['emergency_contact_phone' => ['required', 'string', 'max:40']],
            'bank_account_holder_name' => ['bank_account_holder_name' => ['required', 'string', 'max:255']],
            'bank_id' => ['bank_id' => ['required', 'integer', 'exists:banks,id']],
            'bank_branch' => ['bank_branch' => ['required', 'string', 'max:255']],
            'bank_account_number' => ['bank_account_number' => ['required', 'string', 'max:64']],
            'epf_number' => ['epf_number' => ['nullable', 'string', 'max:80']],
            'etf_number' => ['etf_number' => ['nullable', 'string', 'max:80']],
            'tax_tin' => ['tax_tin' => ['nullable', 'string', 'max:80']],
            'basic_salary' => ['basic_salary' => ['required', 'numeric', 'min:0', 'max:999999999.99']],
            'employee_allowance' => [
                'employee_allowance_id' => [
                    'required',
                    'integer',
                    Rule::exists('hr_employee_allowances', 'id')->where(fn ($query) => $query->where('employee_id', $employee->id)),
                ],
                'allowance_amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            ],
            default => abort(422, 'Unsupported field.'),
        };
    }

    /**
     * Save one already-validated field (see patchFieldRules) and keep monthly gross in sync.
     *
     * @param  array<string, mixed>  $validated
     */
    public function updateField(Employee $employee, string $field, array $validated): void
    {
        if ($field === 'employee_allowance') {
            $this->updateAllowanceAmount(
                $employee,
                (int) $validated['employee_allowance_id'],
                (float) $validated['allowance_amount'],
            );

            return;
        }

        $payload = [];
        foreach ($validated as $key => $value) {
            $payload[$key] = match (true) {
                in_array($key, ['epf_number', 'etf_number', 'tax_tin'], true) => ($value === '' || $value === null) ? null : $value,
                in_array($key, ['bank_id', 'job_title_id', 'department_id'], true) => (int) $value,
                $key === 'basic_salary' => round((float) $value, 2),
                default => $value,
            };
        }

        $employee->fill($payload);
        $employee->save();

        if ($field === 'basic_salary') {
            $this->recalculateMonthlyGross($employee->fresh());
        }
    }
}
