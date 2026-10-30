<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\DepartmentSetting;
use Modules\CRM\Models\Division;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\CrmPermissionService;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Http\Controllers\Api\PosCrmOrgApiController;

/** The CRM migrations under test, in the order they run. */
function crmOrgMigrations(): array
{
    $dir = dirname(__DIR__, 2) . '/database/migrations/';

    return [
        $dir . '2026_10_31_100000_create_crm_divisions_table.php',
        $dir . '2026_10_31_100100_add_department_id_to_crm_projects_table.php',
        $dir . '2026_10_31_100200_create_crm_department_settings_table.php',
    ];
}

// Builds the tables the org structure touches; the CRM tables come from the real migrations
// so they are proven to run on SQLite.
beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password')->nullable();
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
        $t->json('permissions')->nullable();
        $t->timestamps();
    });
    Schema::create('business_members', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('user_id');
        $t->string('role');
        $t->json('permissions')->nullable();
        $t->string('status')->default('active');
        $t->timestamps();
    });
    Schema::create('hr_employees', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('user_id')->nullable();
        $t->unsignedBigInteger('department_id')->nullable();
        $t->string('full_name');
        $t->timestamps();
    });
    Schema::create('hr_departments', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->unsignedBigInteger('head_employee_id')->nullable();
        $t->unsignedBigInteger('co_head_employee_id')->nullable();
        $t->timestamps();
    });
    (require dirname(__DIR__, 2) . '/database/migrations/2026_07_10_099800_create_crm_projects_table.php')->up();
    (require dirname(__DIR__, 2) . '/database/migrations/2026_10_28_100000_create_crm_user_permissions_table.php')->up();
    foreach (crmOrgMigrations() as $file) {
        (require $file)->up();
    }

    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Owner',  'email' => 'owner@example.com'],
        ['id' => 2, 'name' => 'Nimal',  'email' => 'nimal@example.com'],
        ['id' => 3, 'name' => 'Sunil',  'email' => 'sunil@example.com'],
        ['id' => 4, 'name' => 'Kamala', 'email' => 'kamala@example.com'],
        ['id' => 9, 'name' => 'Other owner', 'email' => 'other@example.com'],
    ]);
    DB::table('businesses')->insert([
        ['id' => 7, 'user_id' => 1, 'name' => 'ABC Solar'],
        ['id' => 8, 'user_id' => 9, 'name' => 'Other Co'],
    ]);
    DB::table('business_roles')->insert(['business_id' => 7, 'name' => 'Staff', 'slug' => 'staff', 'permissions' => json_encode(['pos_checkout'])]);
    DB::table('business_members')->insert([
        ['business_id' => 7, 'user_id' => 2, 'role' => 'staff', 'status' => 'active'],
        ['business_id' => 7, 'user_id' => 3, 'role' => 'admin', 'status' => 'active'],
        ['business_id' => 7, 'user_id' => 4, 'role' => 'staff', 'status' => 'active'],
    ]);

    // Sales: head Nimal (user 2), co-head Sunil (user 3), one staff without a login
    DB::table('hr_departments')->insert([
        ['id' => 10, 'business_id' => 7, 'name' => 'Sales', 'head_employee_id' => 100, 'co_head_employee_id' => 101],
        ['id' => 11, 'business_id' => 7, 'name' => 'Marketing', 'head_employee_id' => 103, 'co_head_employee_id' => 101],
        ['id' => 20, 'business_id' => 8, 'name' => 'Sales', 'head_employee_id' => null, 'co_head_employee_id' => null],
    ]);
    DB::table('hr_employees')->insert([
        ['id' => 100, 'business_id' => 7, 'user_id' => 2,    'department_id' => 10, 'full_name' => 'Nimal Perera'],
        ['id' => 101, 'business_id' => 7, 'user_id' => 3,    'department_id' => 10, 'full_name' => 'Sunil Silva'],
        ['id' => 102, 'business_id' => 7, 'user_id' => null, 'department_id' => 10, 'full_name' => 'Ruwan (no login)'],
        ['id' => 103, 'business_id' => 7, 'user_id' => null, 'department_id' => 11, 'full_name' => 'Amali (no login)'],
        ['id' => 104, 'business_id' => 7, 'user_id' => 4,    'department_id' => 11, 'full_name' => 'Kamala Fernando'],
    ]);

    $this->org      = app(CrmOrgService::class);
    $this->business = Business::find(7);
    $this->other    = Business::find(8);
    $this->sales    = Department::find(10);
    $this->mkt      = Department::find(11);
});

test('the migrations roll back and run again on SQLite', function () {
    foreach (array_reverse(crmOrgMigrations()) as $file) {
        (require $file)->down();
    }

    expect(Schema::hasTable('crm_divisions'))->toBeFalse()
        ->and(Schema::hasTable('crm_department_settings'))->toBeFalse()
        ->and(Schema::hasColumn('crm_projects', 'department_id'))->toBeFalse();

    foreach (crmOrgMigrations() as $file) {
        (require $file)->up();
    }

    expect(Schema::hasTable('crm_divisions'))->toBeTrue()
        ->and(Schema::hasColumn('crm_projects', 'department_id'))->toBeTrue();
});

test('divisions can be added, renamed and deleted', function () {
    $division = $this->org->createDivision($this->business, $this->sales, ['name' => ' Solar ', 'head_user_id' => 4]);

    expect($division->name)->toBe('Solar')
        ->and($division->business_id)->toBe(7)
        ->and($division->head_user_id)->toBe(4)
        ->and($division->is_active)->toBeTrue();

    $updated = $this->org->updateDivision($this->business, $division, ['name' => 'Solar PV', 'is_active' => false]);
    expect($updated->name)->toBe('Solar PV')
        ->and($updated->is_active)->toBeFalse()
        ->and($updated->head_user_id)->toBe(4); // left out → kept

    $this->org->deleteDivision($updated);
    expect(Division::count())->toBe(0);
});

test('a division name is unique within its department only', function () {
    $this->org->createDivision($this->business, $this->sales, ['name' => 'Solar']);
    $this->org->createDivision($this->business, $this->mkt, ['name' => 'Solar']);

    expect(fn () => $this->org->createDivision($this->business, $this->sales, ['name' => 'Solar']))
        ->toThrow(ValidationException::class);
});

test('a division head must be a user of the business', function () {
    expect(fn () => $this->org->createDivision($this->business, $this->sales, ['name' => 'Solar', 'head_user_id' => 9]))
        ->toThrow(ValidationException::class);

    // the owner is a valid head even without a member row
    expect($this->org->createDivision($this->business, $this->sales, ['name' => 'Solar', 'head_user_id' => 1])->head_user_id)->toBe(1);
});

test('the department head user is the HR head, then the co-head', function () {
    expect($this->org->headUserId($this->sales))->toBe(2);

    // Marketing's head has no login, so the co-head steps in
    expect($this->org->headUserId($this->mkt))->toBe(3);

    DB::table('hr_departments')->where('id', 11)->update(['co_head_employee_id' => null]);
    expect($this->org->headUserId(Department::find(11)))->toBeNull();
});

test('a division head overrides the department head for that division only', function () {
    $solar   = $this->org->createDivision($this->business, $this->sales, ['name' => 'Solar', 'head_user_id' => 4]);
    $battery = $this->org->createDivision($this->business, $this->sales, ['name' => 'Battery']);
    $mktDiv  = $this->org->createDivision($this->business, $this->mkt, ['name' => 'Digital', 'head_user_id' => 1]);

    expect($this->org->headUserId($this->sales, $solar))->toBe(4)
        ->and($this->org->headUserId($this->sales, $battery))->toBe(2)
        // a division from another department is ignored
        ->and($this->org->headUserId($this->sales, $mktDiv))->toBe(2);
});

test('department members are the HR staff with a login', function () {
    expect($this->org->memberUserIds($this->sales))->toEqualCanonicalizing([2, 3])
        ->and($this->org->memberUserIds($this->mkt))->toBe([4]);
});

test('only one department per business can be the marketing intake', function () {
    $this->org->saveSettings($this->business, $this->sales, ['is_marketing_intake' => true]);
    expect($this->org->marketingIntakeDepartment($this->business)->id)->toBe(10);

    $this->org->saveSettings($this->business, $this->mkt, ['is_marketing_intake' => true]);

    expect($this->org->marketingIntakeDepartment($this->business)->id)->toBe(11)
        ->and(DepartmentSetting::where('is_marketing_intake', true)->count())->toBe(1);

    // another business marking its own department does not touch this one
    $this->org->saveSettings($this->other, Department::find(20), ['is_marketing_intake' => true]);
    expect($this->org->marketingIntakeDepartment($this->business)->id)->toBe(11)
        ->and($this->org->marketingIntakeDepartment($this->other)->id)->toBe(20);
});

test('settings keep a discount limit and clean follow-up days, defaulting to 2 and 5', function () {
    $setting = $this->org->saveSettings($this->business, $this->sales, [
        'discount_approval_limit_percent' => '12.5',
        'followup_days'                   => '7, 3,3, 0',
    ]);

    expect((float) $setting->discount_approval_limit_percent)->toBe(12.5)
        ->and($setting->followupDays())->toBe([3, 7]);

    $setting = $this->org->saveSettings($this->business, $this->sales, ['followup_days' => []]);
    expect($setting->followup_days)->toBeNull()
        ->and($setting->followupDays())->toBe([2, 5])
        ->and($setting->discount_approval_limit_percent)->toBeNull();
});

test('linking with no pipeline creates one named after the department', function () {
    DB::table('crm_projects')->insert(['business_id' => 7, 'name' => 'Sales', 'status' => 'active']);

    $project = $this->org->linkPipeline($this->business, $this->sales, null);

    expect($project->name)->toBe('Sales (2)')
        ->and($project->department_id)->toBe(10)
        ->and($project->business_id)->toBe(7);
});

test('a department has one pipeline and a pipeline serves one department', function () {
    $a = Project::create(['business_id' => 7, 'name' => 'A', 'status' => 'active']);
    $b = Project::create(['business_id' => 7, 'name' => 'B', 'status' => 'active']);

    $this->org->linkPipeline($this->business, $this->sales, $a->id);
    $this->org->linkPipeline($this->business, $this->sales, $b->id);

    expect($a->fresh()->department_id)->toBeNull()
        ->and($b->fresh()->department_id)->toBe(10);

    expect(fn () => $this->org->linkPipeline($this->business, $this->mkt, $b->id))
        ->toThrow(ValidationException::class);

    $this->org->unlinkPipeline($this->business, $this->sales);
    expect($b->fresh()->department_id)->toBeNull();
});

test('another business\'s departments, divisions and pipelines are out of reach', function () {
    $foreignProject  = Project::create(['business_id' => 8, 'name' => 'Theirs', 'status' => 'active']);
    $foreignDivision = $this->org->createDivision($this->other, Department::find(20), ['name' => 'Theirs']);

    expect(fn () => $this->org->departmentForBusiness($this->business, 20))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $this->org->divisionForBusiness($this->business, $foreignDivision->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $this->org->linkPipeline($this->business, $this->sales, $foreignProject->id))->toThrow(ValidationException::class);

    expect(collect($this->org->departments($this->business))->pluck('id')->all())->toBe([11, 10]);
});

test('the department list shows pipeline, settings, divisions and missing-user warnings', function () {
    $this->org->linkPipeline($this->business, $this->sales, null);
    $this->org->createDivision($this->business, $this->sales, ['name' => 'Solar', 'head_user_id' => 4]);
    $this->org->saveSettings($this->business, $this->sales, ['is_marketing_intake' => true]);

    $departments = collect($this->org->departments($this->business))->keyBy('id');
    $sales = $departments[10];
    $mkt   = $departments[11];

    expect($sales['pipeline']['name'])->toBe('Sales')
        ->and($sales['head_user_id'])->toBe(2)
        ->and($sales['settings']['is_marketing_intake'])->toBeTrue()
        ->and($sales['divisions'][0]['head_user_name'])->toBe('Kamala')
        ->and($sales['staff_count'])->toBe(3)
        ->and($sales['unlinked_staff_count'])->toBe(1)
        ->and($sales['warnings'])->toBe(['1 staff member has no linked Zeebroo user.']);

    expect($mkt['pipeline'])->toBeNull()
        ->and($mkt['settings']['followup_days'])->toBe([2, 5])
        ->and($mkt['warnings'])->toContain('Head Amali (no login) has no linked Zeebroo user.');
});

// ── Desktop API ──────────────────────────────────────────────────────────

function crmOrgApi(): PosCrmOrgApiController
{
    return new class(app(CrmOrgService::class), app(CrmPermissionService::class)) extends PosCrmOrgApiController {
        protected function businessOrAbort(Request $request): Business
        {
            return Business::find(7);
        }
    };
}

function crmOrgRequest(int $userId, string $method = 'GET', array $body = []): Request
{
    $request = Request::create('/api/v1/pos/crm/org', $method, $body);
    $request->setUserResolver(fn () => User::find($userId));

    return $request;
}

test('owners and admins get the org structure from the API', function (int $userId) {
    $response = crmOrgApi()->index(crmOrgRequest($userId));
    $data = $response->getData(true)['data'];

    expect($response->getStatusCode())->toBe(200)
        ->and(collect($data['departments'])->pluck('name')->all())->toBe(['Marketing', 'Sales'])
        ->and(collect($data['users'])->pluck('id')->sort()->values()->all())->toBe([1, 2, 3, 4]);
})->with([1, 3]);

test('staff members cannot read or change the org structure', function () {
    $api = crmOrgApi();

    $status = function (callable $call) {
        try {
            return $call()->getStatusCode();
        } catch (HttpResponseException $e) {
            return $e->getResponse()->getStatusCode();
        }
    };

    expect($status(fn () => $api->index(crmOrgRequest(2))))->toBe(403)
        ->and($status(fn () => $api->storeDivision(crmOrgRequest(2, 'POST', ['name' => 'X']), 10)))->toBe(403)
        ->and($status(fn () => $api->updateSettings(crmOrgRequest(2, 'PUT', ['is_marketing_intake' => true]), 10)))->toBe(403)
        ->and($status(fn () => $api->linkPipeline(crmOrgRequest(2, 'POST'), 10)))->toBe(403)
        ->and(Division::count())->toBe(0)
        ->and(Project::count())->toBe(0);
});

test('the API adds a division and links a new pipeline for an admin', function () {
    $api = crmOrgApi();

    $created = $api->storeDivision(crmOrgRequest(1, 'POST', ['name' => 'Solar', 'head_user_id' => 2]), 10);
    expect($created->getStatusCode())->toBe(201)
        ->and($created->getData(true)['data']['name'])->toBe('Solar');

    $linked = $api->linkPipeline(crmOrgRequest(1, 'POST'), 10);
    expect($linked->getData(true)['data']['department_id'])->toBe(10);

    expect(fn () => $api->storeDivision(crmOrgRequest(1, 'POST', ['name' => 'X']), 20))
        ->toThrow(ModelNotFoundException::class);
});
