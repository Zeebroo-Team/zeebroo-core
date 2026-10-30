<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Services\EmployeeService;

// Single-field employee edits shared by the web profile page and PATCH /api/v1/pos/hr/employees/{id}.
beforeEach(function () {
    Schema::create('businesses', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('hr_employees', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('full_name');
        $t->string('employee_id', 64);
        $t->string('epf_number')->nullable();
        $t->decimal('basic_salary', 12, 2)->nullable();
        $t->decimal('salary', 12, 2)->nullable();
        $t->timestamps();
    });
    Schema::create('hr_employee_allowances', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('employee_id');
        $t->unsignedBigInteger('allowance_type_id');
        $t->decimal('amount', 12, 2);
        $t->timestamps();
    });

    DB::table('businesses')->insert([
        ['id' => 7, 'user_id' => 1, 'name' => 'ABC Solution'],
        ['id' => 8, 'user_id' => 1, 'name' => 'Other Co'],
    ]);
    DB::table('hr_employees')->insert([
        ['id' => 50, 'business_id' => 7, 'full_name' => 'Arjun Silva', 'employee_id' => 'EMP-1002', 'epf_number' => '123', 'basic_salary' => 90500, 'salary' => 100500],
        ['id' => 51, 'business_id' => 7, 'full_name' => 'Kamal Perera', 'employee_id' => 'EMP-1003', 'epf_number' => null, 'basic_salary' => 50000, 'salary' => 50000],
        ['id' => 60, 'business_id' => 8, 'full_name' => 'Elsewhere', 'employee_id' => 'EMP-2000', 'epf_number' => null, 'basic_salary' => 1, 'salary' => 1],
    ]);
    DB::table('hr_employee_allowances')->insert(['employee_id' => 50, 'allowance_type_id' => 1, 'amount' => 10000]);

    $this->service  = new EmployeeService;
    $this->business = Business::find(7);
    $this->employee = Employee::find(50);
});

function validateEmployeeField($test, string $field, array $input): \Illuminate\Validation\Validator
{
    return Validator::make($input, $test->service->patchFieldRules($test->business, $test->employee, $field));
}

test('changing basic salary recalculates monthly gross with allowances', function () {
    $v = validateEmployeeField($this, 'basic_salary', ['basic_salary' => '95000.456']);
    expect($v->fails())->toBeFalse();

    $this->service->updateField($this->employee, 'basic_salary', $v->validated());

    $fresh = $this->employee->fresh();
    expect((float) $fresh->basic_salary)->toBe(95000.46)
        ->and((float) $fresh->salary)->toBe(105000.46);
});

test('blank statutory numbers are stored as null', function () {
    $v = validateEmployeeField($this, 'epf_number', ['epf_number' => '']);

    $this->service->updateField($this->employee, 'epf_number', $v->validated());

    expect($this->employee->fresh()->epf_number)->toBeNull();
});

test('employee code must be unique within the business only', function () {
    expect(validateEmployeeField($this, 'employee_id', ['employee_id' => 'EMP-1003'])->fails())->toBeTrue()
        ->and(validateEmployeeField($this, 'employee_id', ['employee_id' => 'EMP-1002'])->fails())->toBeFalse()
        ->and(validateEmployeeField($this, 'employee_id', ['employee_id' => 'EMP-2000'])->fails())->toBeFalse();
});

test('only the requested field is written', function () {
    $v = validateEmployeeField($this, 'full_name', ['full_name' => 'Arjun K. Silva', 'basic_salary' => 1]);

    $this->service->updateField($this->employee, 'full_name', $v->validated());

    $fresh = $this->employee->fresh();
    expect($fresh->full_name)->toBe('Arjun K. Silva')
        ->and((float) $fresh->basic_salary)->toBe(90500.0);
});

test('unknown fields are not patchable', function () {
    expect($this->service->patchableFields())->not->toContain('business_id')
        ->and($this->service->patchableFields())->toContain('bank_account_number');
});
