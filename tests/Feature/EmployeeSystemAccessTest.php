<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessInvitation;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Services\BusinessInvitationService;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Services\EmployeeSystemAccessService;
use Modules\Mail\Mail\AdminMarketingMail;
use Modules\Mail\Services\AutomatedEmailService;

// Builds only the tables the employee → team-member flow touches.
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
        $t->string('full_name');
        $t->string('employee_id', 64);
        $t->string('personal_email');
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

    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('x')],
        ['id' => 2, 'name' => 'Nimal Admin', 'email' => 'nimal@example.com', 'password' => bcrypt('x')],
        ['id' => 3, 'name' => 'Sunil Staff', 'email' => 'sunil@example.com', 'password' => bcrypt('x')],
    ]);
    DB::table('businesses')->insert(['id' => 7, 'user_id' => 1, 'name' => 'ABC Solution']);
    DB::table('business_members')->insert([
        ['business_id' => 7, 'user_id' => 2, 'role' => 'admin', 'status' => 'active'],
        ['business_id' => 7, 'user_id' => 3, 'role' => 'staff', 'status' => 'active'],
    ]);
    DB::table('hr_employees')->insert([
        'id' => 50, 'business_id' => 7, 'full_name' => 'Arjun Silva', 'employee_id' => 'EMP-1002', 'personal_email' => 'Arjun.Silva@Example.com',
    ]);

    Mail::fake();
    $this->automated = Mockery::mock(AutomatedEmailService::class);
    $this->automated->shouldReceive('queueWelcome');
    $this->invitations = new BusinessInvitationService($this->automated);
    $this->service = new EmployeeSystemAccessService($this->invitations);
    $this->business = Business::find(7);
    $this->owner = User::find(1);
    $this->employee = Employee::find(50);
    $this->service->roleSlugs($this->business); // seeds admin / manager / staff
});

test('giving access emails a staff invitation to the employee and adds no member yet', function () {
    $result = $this->service->grant($this->employee, $this->business, 'staff', $this->owner);

    expect($result['email_sent'])->toBeTrue()
        ->and(BusinessMember::where('user_id', '>', 3)->count())->toBe(0);

    $status = $this->service->status($this->employee);
    expect($status['status'])->toBe('invited')
        ->and($status['email'])->toBe('arjun.silva@example.com')
        ->and($status['role'])->toBe('staff')
        ->and($status['role_name'])->toBe('Staff');

    Mail::assertSent(AdminMarketingMail::class, fn ($m) => $m->hasTo('arjun.silva@example.com'));
});

test('accepting the invitation creates the member and links the employee profile', function () {
    $this->service->grant($this->employee, $this->business, 'staff', $this->owner);

    $user = $this->invitations->accept(BusinessInvitation::first(), ['name' => 'Arjun Silva', 'password' => 'secret-pass-123']);

    expect($this->employee->fresh()->user_id)->toBe($user->id)
        ->and(BusinessMember::where('business_id', 7)->where('user_id', $user->id)->value('role'))->toBe('staff');

    $status = $this->service->status($this->employee->fresh());
    expect($status['status'])->toBe('active')
        ->and($status['member_id'])->not->toBeNull();
});

test('granting again to an active employee changes the role instead of re-inviting', function () {
    $this->service->grant($this->employee, $this->business, 'staff', $this->owner);
    $this->invitations->accept(BusinessInvitation::first(), ['name' => 'Arjun Silva', 'password' => 'secret-pass-123']);
    Mail::fake();

    $result = $this->service->grant($this->employee->fresh(), $this->business, 'manager', $this->owner);

    expect($result['email_sent'])->toBeFalse()
        ->and($this->service->status($this->employee->fresh())['role'])->toBe('manager');
    Mail::assertNothingSent();
});

test('revoking removes the membership but keeps the HR record linked', function () {
    $this->service->grant($this->employee, $this->business, 'staff', $this->owner);
    $user = $this->invitations->accept(BusinessInvitation::first(), ['name' => 'Arjun Silva', 'password' => 'secret-pass-123']);

    $this->service->revoke($this->employee->fresh());

    expect(BusinessMember::where('user_id', $user->id)->exists())->toBeFalse()
        ->and($this->employee->fresh()->user_id)->toBe($user->id)
        ->and($this->service->status($this->employee->fresh())['status'])->toBe('none');
});

test('revoking a pending invitation cancels it', function () {
    $this->service->grant($this->employee, $this->business, 'staff', $this->owner);

    $this->service->revoke($this->employee);

    expect(BusinessInvitation::first()->status)->toBe(BusinessInvitation::STATUS_CANCELLED)
        ->and($this->service->status($this->employee)['status'])->toBe('none');
});

test('an employee already added under Settings → Users is linked and shown as active', function () {
    DB::table('users')->insert(['id' => 9, 'name' => 'Arjun', 'email' => 'arjun.silva@example.com', 'password' => bcrypt('x')]);
    DB::table('business_members')->insert(['business_id' => 7, 'user_id' => 9, 'role' => 'manager', 'status' => 'active']);

    $status = $this->service->status($this->employee);

    expect($status['status'])->toBe('active')
        ->and($status['role'])->toBe('manager')
        ->and($this->employee->fresh()->user_id)->toBe(9);
});

test('the business owner cannot be invited through HR', function () {
    DB::table('hr_employees')->where('id', 50)->update(['personal_email' => 'owner@example.com']);

    $this->service->grant($this->employee->fresh(), $this->business, 'staff', $this->owner);
})->throws(ValidationException::class);

test('only the owner or a full-access member can manage access', function () {
    expect($this->service->canManageAccess($this->business, User::find(1)))->toBeTrue()
        ->and($this->service->canManageAccess($this->business, User::find(2)))->toBeTrue()
        ->and($this->service->canManageAccess($this->business, User::find(3)))->toBeFalse();
});

test('the list lookup badges employees without per-row queries', function () {
    $lookup = $this->service->accessLookup($this->business);
    expect($this->service->quickStatus($this->employee, $lookup))->toBe('none');

    $this->service->grant($this->employee, $this->business, 'staff', $this->owner);
    $lookup = $this->service->accessLookup($this->business);
    expect($this->service->quickStatus($this->employee, $lookup))->toBe('invited');
});

// ── HR email change → team login ─────────────────────────────────────────────

function linkArjunLogin(): void
{
    DB::table('users')->insert(['id' => 9, 'name' => 'CRM_Hr', 'email' => 'arjun.silva@example.com', 'password' => bcrypt('x')]);
    DB::table('business_members')->insert(['business_id' => 7, 'user_id' => 9, 'role' => 'manager', 'status' => 'active']);
    DB::table('hr_employees')->where('id', 50)->update(['user_id' => 9]);
}

test('changing a linked employee email changes their login email too', function () {
    linkArjunLogin();

    $result = $this->service->syncEmailChange($this->employee->fresh(), $this->business, 'Arjun.New@Example.com', $this->owner);

    expect($result)->toBe('login')
        ->and(User::find(9)->email)->toBe('arjun.new@example.com');
});

test('an employee without access only changes the HR record', function () {
    expect($this->service->syncEmailChange($this->employee, $this->business, 'arjun.new@example.com', $this->owner))->toBeNull();
});

test('a pending invitation follows the new email', function () {
    $this->service->grant($this->employee, $this->business, 'staff', $this->owner);

    $result = $this->service->syncEmailChange($this->employee, $this->business, 'arjun.new@example.com', $this->owner);

    expect($result)->toBe('invitation')
        ->and(BusinessInvitation::where('email', 'arjun.silva@example.com')->value('status'))->toBe(BusinessInvitation::STATUS_CANCELLED)
        ->and(BusinessInvitation::where('email', 'arjun.new@example.com')->where('status', BusinessInvitation::STATUS_PENDING)->value('role'))->toBe('staff');
});

test('a login shared with another business is not changed from HR', function () {
    linkArjunLogin();
    DB::table('business_members')->insert(['business_id' => 99, 'user_id' => 9, 'role' => 'staff', 'status' => 'active']);

    $this->service->syncEmailChange($this->employee->fresh(), $this->business, 'arjun.new@example.com', $this->owner);
})->throws(ValidationException::class, 'another business');

test('a login email cannot be moved onto another account', function () {
    linkArjunLogin();

    $this->service->syncEmailChange($this->employee->fresh(), $this->business, 'nimal@example.com', $this->owner);
})->throws(ValidationException::class, 'another account');

test('only the owner or an admin can change a linked employee email', function () {
    linkArjunLogin();

    $this->service->syncEmailChange($this->employee->fresh(), $this->business, 'arjun.new@example.com', User::find(3));
})->throws(ValidationException::class, 'owner or an admin');
