<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessInvitation;
use Modules\Business\Services\BusinessInvitationService;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Services\UserEmployeeLinkService;
use Modules\Mail\Services\AutomatedEmailService;

// Settings → Users placing a member in a department without the HR module.
beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password')->nullable();
        $t->string('google_id')->nullable();
        $t->timestamp('email_verified_at')->nullable();
        $t->rememberToken();
        $t->timestamps();
    });
    Schema::create('businesses', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('business_roles', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->string('slug');
        $t->string('color')->nullable();
        $t->text('description')->nullable();
        $t->json('permissions')->nullable();
        $t->boolean('is_system')->default(false);
        $t->integer('sort_order')->default(0);
        $t->timestamps();
    });
    Schema::create('business_members', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('user_id');
        $t->string('role');
        $t->json('permissions')->nullable();
        $t->string('status')->default('active');
        $t->unsignedBigInteger('invited_by')->nullable();
        $t->timestamps();
    });
    Schema::create('business_invitations', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('email');
        $t->string('role', 60);
        $t->json('permissions')->nullable();
        $t->string('token_hash', 64)->unique();
        $t->string('status', 20)->default('pending');
        $t->unsignedBigInteger('invited_by')->nullable();
        $t->unsignedBigInteger('accepted_by')->nullable();
        $t->timestamp('expires_at');
        $t->timestamp('last_sent_at')->nullable();
        $t->timestamp('accepted_at')->nullable();
        $t->timestamps();
    });
    Schema::create('hr_employees', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('user_id')->nullable();
        $t->boolean('profile_complete')->default(true);
        $t->string('full_name');
        $t->string('employee_id', 64);
        $t->string('personal_email');
        $t->unsignedBigInteger('department_id')->nullable();
        $t->unsignedBigInteger('job_title_id')->nullable();
        $t->date('date_of_joining')->nullable();
        $t->timestamps();
    });
    Schema::create('settings', function (Blueprint $t) { // new users get default UI settings
        $t->id();
        $t->string('scope_type');
        $t->unsignedBigInteger('scope_id');
        $t->string('key');
        $t->text('value')->nullable();
        $t->string('value_type')->default('string');
        $t->timestamps();
    });
    Schema::create('roles', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
    });

    DB::table('users')->insert(['id' => 1, 'name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('x')]);
    DB::table('businesses')->insert(['id' => 7, 'user_id' => 1, 'name' => 'ABC Solution']);
    DB::table('hr_employees')->insert([
        'id' => 50, 'business_id' => 7, 'full_name' => 'Arjun Silva', 'employee_id' => 'EMP-1002',
        'personal_email' => 'arjun@example.com', 'department_id' => 3, 'job_title_id' => 4,
    ]);

    Mail::fake();
    $this->service = new UserEmployeeLinkService();
    $this->business = Business::find(7);
});

test('a member placed in a department gets an incomplete profile with the next employee number', function () {
    $employee = $this->service->assign($this->business, 'Hasitha@Example.com', null, 'hasitha', 5, null);

    expect($employee->profile_complete)->toBeFalse()
        ->and($employee->employee_id)->toBe('EMP-1003')
        ->and($employee->personal_email)->toBe('hasitha@example.com')
        ->and($employee->department_id)->toBe(5)
        ->and($employee->business_id)->toBe(7);
});

test('a member placed nowhere gets no profile', function () {
    expect($this->service->assign($this->business, 'nobody@example.com', null, 'nobody', null, null))->toBeNull()
        ->and(Employee::count())->toBe(1);
});

test('an existing HR profile is moved, not duplicated, and stays complete', function () {
    $employee = $this->service->assign($this->business, 'ARJUN@example.com', null, 'ignored', 9, null);

    expect($employee->id)->toBe(50)
        ->and($employee->department_id)->toBe(9)
        ->and($employee->job_title_id)->toBeNull()
        ->and($employee->full_name)->toBe('Arjun Silva')
        ->and($employee->profile_complete)->toBeTrue()
        ->and(Employee::count())->toBe(1);
});

test('the profile linked to the login wins over one with the same email', function () {
    DB::table('hr_employees')->where('id', 50)->update(['user_id' => 1]);

    $employee = $this->service->assign($this->business, 'other@example.com', 1, 'Owner', 2, null);

    expect($employee->id)->toBe(50)->and($employee->department_id)->toBe(2);
});

test('accepting the invitation links the incomplete profile and names it after the account', function () {
    $automated = Mockery::mock(AutomatedEmailService::class);
    $automated->shouldReceive('queueWelcome');
    $invitations = new BusinessInvitationService($automated);

    [$invitation] = $invitations->invite($this->business, 'hasitha@example.com', 'staff', null, \App\Models\User::find(1));
    $employee = $this->service->assign($this->business, 'hasitha@example.com', null, 'hasitha', 5, null);

    $user = $invitations->accept(BusinessInvitation::find($invitation->id), ['name' => 'Hasitha Mendis', 'password' => 'secret-pass-123']);

    $employee->refresh();
    expect($employee->user_id)->toBe($user->id)
        ->and($employee->full_name)->toBe('Hasitha Mendis')
        ->and(Employee::find(50)->full_name)->toBe('Arjun Silva');
});

test('removing a member deletes their incomplete profile but keeps an HR-completed one', function () {
    $this->service->assign($this->business, 'hasitha@example.com', 2, 'Hasitha', 5, null);
    DB::table('hr_employees')->where('id', 50)->update(['user_id' => 3]);

    $this->service->removeIncomplete($this->business, 2);
    $this->service->removeIncomplete($this->business, 3);

    expect(Employee::where('user_id', 2)->exists())->toBeFalse()
        ->and(Employee::find(50))->not->toBeNull();
});

test('cancelling an invitation deletes only the incomplete profile waiting for that email', function () {
    $this->service->assign($this->business, 'hasitha@example.com', null, 'hasitha', 5, null);

    $this->service->removeIncompleteInvite($this->business, 'Hasitha@Example.com');
    $this->service->removeIncompleteInvite($this->business, 'arjun@example.com');

    expect(Employee::pluck('id')->all())->toBe([50]);
});
