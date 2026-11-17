<?php

namespace Modules\HRManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Account\Models\Bank;
use Modules\HRManagement\Models\AllowanceType;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\EmployeeAllowance;
use Modules\HRManagement\Models\JobTitle;
use Modules\HRManagement\Models\LeavePolicy;

/**
 * Demo HR data (departments, positions, leave rules, allowance types, 60 employees) for one business.
 *
 * Safe to re-run: rows are matched on their natural keys and updated in place.
 *
 *   php artisan db:seed --class="Modules\HRManagement\Database\Seeders\HrDemoDataSeeder"
 *   HR_DEMO_BUSINESS_ID=3 php artisan db:seed --class=...   (defaults to business 1)
 */
class HrDemoDataSeeder extends Seeder
{
    private const EMPLOYEE_COUNT = 60;

    private int $businessId;

    public function run(): void
    {
        $this->businessId = (int) (env('HR_DEMO_BUSINESS_ID') ?: 1);

        if (! DB::table('businesses')->where('id', $this->businessId)->exists()) {
            $this->command?->error("Business #{$this->businessId} not found.");

            return;
        }

        mt_srand(20261022);

        DB::transaction(function (): void {
            $policies = $this->seedLeavePolicies();
            $allowanceTypes = $this->seedAllowanceTypes();
            $departments = $this->seedDepartments();
            $positions = $this->seedPositions($policies);
            $this->seedEmployees($departments, $positions, $allowanceTypes);
        });

        $this->command?->info('HR demo data seeded for business #'.$this->businessId.'.');
    }

    /** @return array<string, LeavePolicy> */
    private function seedLeavePolicies(): array
    {
        $rows = [
            'Permanent Staff' => [14, 7, 7, true, 2, 90, true, 5, 0],
            'Management' => [21, 7, 14, true, 3, 120, true, 10, 0],
            'Probation' => [0, 3, 3, false, null, null, true, null, 0],
            'Part-Time Staff' => [7, 3, 3, false, null, null, false, null, 3],
            'Contract Staff' => [10, 5, 5, true, 1, 60, true, null, 6],
        ];

        // Keep the user's existing default rule as default; only fall back to ours if there is none.
        $hasDefault = LeavePolicy::where('business_id', $this->businessId)->where('is_default', true)->exists();

        $out = [];
        foreach ($rows as $name => [$annual, $casual, $sick, $short, $perMonth, $maxMin, $halfDay, $carry, $eligible]) {
            $policy = LeavePolicy::firstOrNew(['business_id' => $this->businessId, 'name' => $name]);
            $policy->fill([
                'annual_leave_days' => $annual,
                'casual_leave_days' => $casual,
                'sick_leave_days' => $sick,
                'short_leave_enabled' => $short,
                'short_leaves_per_month' => $perMonth,
                'short_leave_max_minutes' => $maxMin,
                'half_day_allowed' => $halfDay,
                'carry_forward_max_days' => $carry,
                'eligible_after_months' => $eligible,
            ]);
            if (! $policy->exists) {
                $policy->is_default = ! $hasDefault && $name === 'Permanent Staff';
            }
            $policy->save();
            $out[$name] = $policy;
        }

        return $out;
    }

    /** @return array<string, AllowanceType> */
    private function seedAllowanceTypes(): array
    {
        $names = ['Transport', 'Meal', 'Mobile', 'Housing', 'Attendance Bonus', 'Fuel', 'Medical'];

        $out = [];
        foreach ($names as $i => $name) {
            $type = AllowanceType::firstOrNew(['business_id' => $this->businessId, 'name' => $name]);
            $type->forceFill(['business_id' => $this->businessId, 'sort_order' => $i + 1])->save();
            $out[$name] = $type;
        }

        return $out;
    }

    /** @return array<string, Department> */
    private function seedDepartments(): array
    {
        $rows = [
            'Financial' => [60000, 350000],
            'Sales' => [45000, 250000],
            'Operations' => [40000, 220000],
            'Human Resources' => [50000, 250000],
            'IT' => [70000, 450000],
            'Marketing' => [50000, 280000],
            'Customer Service' => [40000, 150000],
            'Warehouse' => [35000, 140000],
        ];

        $out = [];
        foreach ($rows as $name => [$min, $max]) {
            $dept = Department::firstOrNew(['business_id' => $this->businessId, 'name' => $name]);
            $dept->forceFill([
                'business_id' => $this->businessId,
                'salary_range_min' => $dept->salary_range_min ?? $min,
                'salary_range_max' => $dept->salary_range_max ?? $max,
            ])->save();
            $out[$name] = $dept;
        }

        return $out;
    }

    /**
     * @param  array<string, LeavePolicy>  $policies
     * @return array<string, JobTitle>
     */
    private function seedPositions(array $policies): array
    {
        // name => [leave rule (null = business default), portal features (null = all)]
        $rows = [
            'Senior Accountant' => [null, null],
            'Senior Manager' => [null, null],
            'Accountant' => [null, null],
            'Accounts Assistant' => [null, ['leaves', 'complaints', 'salary']],
            'Sales Manager' => ['Management', null],
            'Sales Executive' => [null, ['leaves', 'complaints', 'salary', 'pos_online']],
            'Cashier' => [null, ['leaves', 'complaints', 'salary', 'pos_online']],
            'Operations Manager' => ['Management', null],
            'Operations Officer' => [null, ['leaves', 'complaints', 'salary']],
            'HR Manager' => ['Management', null],
            'HR Executive' => [null, null],
            'Software Engineer' => [null, ['leaves', 'complaints', 'salary']],
            'IT Support Technician' => ['Contract Staff', ['leaves', 'complaints', 'salary']],
            'Marketing Executive' => [null, ['leaves', 'complaints', 'salary']],
            'Graphic Designer' => ['Part-Time Staff', ['leaves', 'salary']],
            'Customer Service Agent' => [null, ['leaves', 'complaints', 'salary', 'pos_online']],
            'Store Keeper' => [null, ['leaves', 'complaints', 'salary']],
            'Driver' => ['Contract Staff', ['leaves', 'salary']],
            'Trainee' => ['Probation', ['leaves', 'salary']],
        ];

        $out = [];
        foreach ($rows as $name => [$policyName, $features]) {
            $title = JobTitle::firstOrNew(['business_id' => $this->businessId, 'name' => $name]);
            $title->forceFill([
                'business_id' => $this->businessId,
                'leave_policy_id' => $title->exists ? $title->leave_policy_id : ($policyName ? $policies[$policyName]->id : null),
                'portal_features' => $title->exists ? $title->portal_features : $features,
            ])->save();
            $out[$name] = $title;
        }

        return $out;
    }

    /**
     * @param  array<string, Department>  $departments
     * @param  array<string, JobTitle>  $positions
     * @param  array<string, AllowanceType>  $allowanceTypes
     */
    private function seedEmployees(array $departments, array $positions, array $allowanceTypes): void
    {
        // department => [[position, basic min, basic max, how many], ...]  (first row = department head)
        $plan = [
            'Financial' => [['Senior Manager', 250000, 320000, 1], ['Accountant', 90000, 140000, 3], ['Accounts Assistant', 55000, 75000, 3]],
            'Sales' => [['Sales Manager', 180000, 240000, 1], ['Sales Executive', 50000, 90000, 7], ['Cashier', 45000, 60000, 4]],
            'Operations' => [['Operations Manager', 170000, 210000, 1], ['Operations Officer', 55000, 95000, 5]],
            'Human Resources' => [['HR Manager', 180000, 230000, 1], ['HR Executive', 65000, 100000, 2]],
            'IT' => [['Software Engineer', 120000, 380000, 6], ['IT Support Technician', 70000, 110000, 2]],
            'Marketing' => [['Marketing Executive', 60000, 120000, 4], ['Graphic Designer', 50000, 90000, 2]],
            'Customer Service' => [['Customer Service Agent', 42000, 70000, 6]],
            'Warehouse' => [['Store Keeper', 40000, 65000, 4], ['Driver', 38000, 55000, 3], ['Trainee', 35000, 40000, 5]],
        ];

        $firstNames = ['Nuwan', 'Chamara', 'Dilshan', 'Kasun', 'Tharindu', 'Ruwan', 'Lahiru', 'Isuru', 'Pradeep', 'Supun',
            'Asanka', 'Mahesh', 'Sandun', 'Chathura', 'Dinesh', 'Janaka', 'Roshan', 'Buddhika', 'Hasitha', 'Gayan',
            'Nadeesha', 'Dilini', 'Sanduni', 'Chamodi', 'Hiruni', 'Ishara', 'Kavindi', 'Malsha', 'Nethmi', 'Piumi',
            'Rashmi', 'Sachini', 'Tharushi', 'Upeksha', 'Yasodha', 'Anjali', 'Dulani', 'Ishani', 'Madhavi', 'Shalini',
            'Mohamed', 'Fathima', 'Rizwan', 'Ayesha', 'Arjun', 'Priya', 'Suresh', 'Kavitha'];
        $lastNames = ['Perera', 'Fernando', 'Silva', 'Jayasinghe', 'Bandara', 'Wickramasinghe', 'Rathnayake', 'Herath',
            'Dissanayake', 'Gunawardena', 'Senanayake', 'Wijesinghe', 'Karunaratne', 'Rajapaksha', 'Amarasinghe',
            'Mendis', 'Liyanage', 'Ekanayake', 'Kumarasinghe', 'Abeysekara', 'Nawaz', 'Rahman', 'Pillai', 'Kumar'];
        $cities = ['Colombo 05', 'Nugegoda', 'Maharagama', 'Dehiwala', 'Moratuwa', 'Kandy', 'Gampaha', 'Negombo',
            'Kurunegala', 'Kelaniya', 'Battaramulla', 'Kottawa', 'Panadura', 'Kadawatha', 'Wattala', 'Galle'];
        $streets = ['Temple Road', 'Galle Road', 'High Level Road', 'Station Road', 'Lake Drive', 'Flower Road',
            'Kandy Road', 'School Lane', 'Old Road', 'Main Street', 'Park Avenue', 'Hospital Road'];
        $relations = ['Father', 'Mother', 'Spouse', 'Brother', 'Sister'];
        $branches = ['Colombo Fort', 'Nugegoda', 'Maharagama', 'Kandy', 'Gampaha', 'Negombo', 'Dehiwala', 'Kurunegala'];

        $bankIds = Bank::query()->pluck('id')->all();
        if ($bankIds === []) {
            $this->command?->error('No banks found; seed the banks table first.');

            return;
        }

        $usedNames = [];
        $seq = 0;

        foreach ($plan as $deptName => $roles) {
            $dept = $departments[$deptName];
            $deptEmployees = [];

            foreach ($roles as [$positionName, $minBasic, $maxBasic, $count]) {
                for ($i = 0; $i < $count; $i++) {
                    $seq++;

                    do {
                        $female = mt_rand(0, 1) === 1;
                        $first = $female ? $firstNames[mt_rand(20, 39)] : $firstNames[mt_rand(0, 19)];
                        if (mt_rand(1, 10) === 1) {
                            $first = $firstNames[mt_rand(40, count($firstNames) - 1)];
                        }
                        $fullName = $first.' '.$lastNames[mt_rand(0, count($lastNames) - 1)];
                    } while (isset($usedNames[$fullName]));
                    $usedNames[$fullName] = true;

                    $employmentType = match (true) {
                        in_array($positionName, ['Driver', 'IT Support Technician'], true) => Employee::EMPLOYMENT_CONTRACT,
                        $positionName === 'Graphic Designer', $positionName === 'Cashier' && $i >= 2 => Employee::EMPLOYMENT_PART_TIME,
                        mt_rand(1, 12) === 1 => Employee::EMPLOYMENT_CONTRACT,
                        default => Employee::EMPLOYMENT_FULL_TIME,
                    };

                    $basic = round(mt_rand($minBasic, $maxBasic) / 500) * 500;
                    if ($employmentType === Employee::EMPLOYMENT_PART_TIME) {
                        $basic = round($basic * 0.6 / 500) * 500;
                    }

                    $dobYear = mt_rand(1972, 2004);
                    $dob = sprintf('%04d-%02d-%02d', $dobYear, mt_rand(1, 12), mt_rand(1, 28));
                    $joined = $positionName === 'Trainee'
                        ? sprintf('2026-%02d-%02d', mt_rand(6, 10), mt_rand(1, 20))
                        : sprintf('%04d-%02d-%02d', mt_rand(2016, 2026), mt_rand(1, 9), mt_rand(1, 28));
                    $nic = $dobYear.str_pad((string) (mt_rand(1, 365) + ($female ? 500 : 0)), 3, '0', STR_PAD_LEFT)
                        .str_pad((string) (1000 + $seq), 5, '0', STR_PAD_LEFT);
                    $address = mt_rand(5, 450).', '.$streets[mt_rand(0, count($streets) - 1)].', '.$cities[mt_rand(0, count($cities) - 1)];
                    $emailName = strtolower(str_replace(' ', '.', $fullName));

                    $employee = Employee::firstOrNew([
                        'business_id' => $this->businessId,
                        'employee_id' => sprintf('EMP-%04d', 1000 + $seq),
                    ]);
                    $employee->forceFill([
                        'business_id' => $this->businessId,
                        'full_name' => $fullName,
                        'date_of_birth' => $dob,
                        'nic_passport_number' => $nic,
                        'permanent_address' => $address,
                        'current_address' => mt_rand(1, 4) === 1
                            ? mt_rand(5, 450).', '.$streets[mt_rand(0, count($streets) - 1)].', Colombo 0'.mt_rand(2, 8)
                            : $address,
                        'phone_number' => '07'.[0, 1, 2, 5, 6, 7, 8][mt_rand(0, 6)].mt_rand(1000000, 9999999),
                        'personal_email' => $emailName.mt_rand(1, 99).'@gmail.com',
                        'job_title_id' => $positions[$positionName]->id,
                        'department_id' => $dept->id,
                        'date_of_joining' => $joined,
                        'employment_type' => $employmentType,
                        'basic_salary' => $basic,
                        'emergency_contact_name' => $firstNames[mt_rand(0, 39)].' '.explode(' ', $fullName)[1],
                        'emergency_contact_relationship' => $relations[mt_rand(0, count($relations) - 1)],
                        'emergency_contact_phone' => '07'.mt_rand(10000000, 89999999),
                        'bank_account_holder_name' => strtoupper($fullName),
                        'bank_id' => $bankIds[mt_rand(0, count($bankIds) - 1)],
                        'bank_branch' => $branches[mt_rand(0, count($branches) - 1)],
                        'bank_account_number' => (string) mt_rand(1000000000, 2147483647),
                        'epf_number' => $employmentType === Employee::EMPLOYMENT_CONTRACT ? null : 'EPF'.(5000 + $seq),
                        'etf_number' => $employmentType === Employee::EMPLOYMENT_CONTRACT ? null : 'ETF'.(5000 + $seq),
                        'tax_tin' => $basic >= 150000 ? (string) mt_rand(100000000, 999999999) : null,
                    ])->save();

                    $allowanceTotal = $this->seedAllowances($employee, $allowanceTypes, $basic, $employmentType);
                    $employee->forceFill(['salary' => round($basic + $allowanceTotal, 2)])->save();

                    $deptEmployees[] = $employee;
                }
            }

            // First employee planned for the department is the head, second the co-head — unless already set.
            $dept->forceFill([
                'head_employee_id' => $dept->head_employee_id ?? ($deptEmployees[0]->id ?? null),
                'co_head_employee_id' => $dept->co_head_employee_id ?? ($deptEmployees[1]->id ?? null),
            ])->save();
        }
    }

    /** @param  array<string, AllowanceType>  $types */
    private function seedAllowances(Employee $employee, array $types, float $basic, string $employmentType): float
    {
        $amounts = [
            'Transport' => $employmentType === Employee::EMPLOYMENT_PART_TIME ? 3000 : 6000,
            'Meal' => 5000,
            'Attendance Bonus' => mt_rand(0, 1) ? 2500 : null,
            'Mobile' => $basic >= 90000 ? 3500 : (mt_rand(0, 2) === 0 ? 1500 : null),
            'Fuel' => $basic >= 170000 ? 25000 : null,
            'Housing' => $basic >= 200000 ? 30000 : null,
            'Medical' => $employmentType === Employee::EMPLOYMENT_FULL_TIME && mt_rand(0, 1) ? 4000 : null,
        ];

        $employee->employeeAllowances()->delete();

        $total = 0.0;
        foreach ($amounts as $name => $amount) {
            if ($amount === null) {
                continue;
            }
            $row = new EmployeeAllowance(['allowance_type_id' => $types[$name]->id, 'amount' => $amount]);
            $row->employee()->associate($employee);
            $row->save();
            $total += $amount;
        }

        return $total;
    }
}
