<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessRole;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Project;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmPermissionService;
use Modules\Pos\Http\Controllers\Api\PosCrmApiController;
use Modules\Pos\Http\Controllers\Api\PosCrmPermissionApiController;
use Modules\Pos\Models\PosCashier;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * Business 7 "ABC Solar":
 *   Sales (10)     head Nimal (2), staff Sunil (3)            → pipeline 1: leads 1 (assigned to Sunil), 2
 *   Marketing (11) Kamala (4), the marketing intake          → pipeline 2: lead 3
 *   Support (12)   staff Priya (6)                           → pipeline 3: lead 4
 *   no department                                            → pipeline 4: lead 5
 *   Chen (5) is the CEO, Admin (7) an admin member, Ravi (8) a manager with "All Departments".
 * Business 8 belongs to user 9                               → pipeline 5: lead 6
 */
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
        $t->string('color')->nullable();
        $t->string('description')->nullable();
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
    Schema::create('crm_leads', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('project_id');
        $t->unsignedBigInteger('form_id')->nullable();
        $t->string('name');
        $t->unsignedBigInteger('stage_id')->nullable();
        $t->unsignedBigInteger('assigned_to')->nullable();
        $t->unsignedBigInteger('created_by')->nullable();
        $t->timestamps();
    });
    Schema::create('crm_tasks', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->nullableMorphs('subject');
        $t->string('title');
        $t->dateTime('due_at')->nullable();
        $t->string('status')->default('pending');
        $t->unsignedBigInteger('assigned_to')->nullable();
        $t->timestamps();
    });
    $dir = dirname(__DIR__, 2) . '/database/migrations/';
    foreach ([
        '2026_07_10_099800_create_crm_projects_table.php',
        '2026_07_10_099900_create_crm_lead_stages_table.php',
        '2026_10_28_100000_create_crm_user_permissions_table.php',
        '2026_10_31_100000_create_crm_divisions_table.php',
        '2026_10_31_100100_add_department_id_to_crm_projects_table.php',
        '2026_10_31_100200_create_crm_department_settings_table.php',
    ] as $file) {
        (require $dir . $file)->up();
    }

    DB::table('users')->insert(collect([
        1 => 'Owner', 2 => 'Nimal', 3 => 'Sunil', 4 => 'Kamala', 5 => 'Chen',
        6 => 'Priya', 7 => 'Admin', 8 => 'Ravi', 9 => 'Other owner',
    ])->map(fn ($name, $id) => ['id' => $id, 'name' => $name, 'email' => strtolower($name) . '@example.com'])->values()->all());

    DB::table('businesses')->insert([
        ['id' => 7, 'user_id' => 1, 'name' => 'ABC Solar'],
        ['id' => 8, 'user_id' => 9, 'name' => 'Other Co'],
    ]);
    BusinessRole::seedForBusiness(7);
    // Members carry their role's module keys, as the user management screens copy them
    $crmKeys = json_encode(['crm_pipeline', 'crm_contacts', 'crm_tasks', 'crm_forms']);
    DB::table('business_members')->insert([
        ['business_id' => 7, 'user_id' => 2, 'role' => 'crm_department_head', 'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 3, 'role' => 'crm_staff',           'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 4, 'role' => 'crm_marketing',       'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 5, 'role' => 'crm_ceo',             'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 6, 'role' => 'crm_staff',           'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 7, 'role' => 'admin',               'permissions' => null],
        ['business_id' => 7, 'user_id' => 8, 'role' => 'manager',             'permissions' => json_encode(['crm_pipeline', 'crm_all_leads'])],
    ]);

    DB::table('hr_departments')->insert([
        ['id' => 10, 'business_id' => 7, 'name' => 'Sales',     'head_employee_id' => 100],
        ['id' => 11, 'business_id' => 7, 'name' => 'Marketing', 'head_employee_id' => null],
        ['id' => 12, 'business_id' => 7, 'name' => 'Support',   'head_employee_id' => null],
        ['id' => 20, 'business_id' => 8, 'name' => 'Sales',     'head_employee_id' => null],
    ]);
    DB::table('hr_employees')->insert([
        ['id' => 100, 'business_id' => 7, 'user_id' => 2, 'department_id' => 10, 'full_name' => 'Nimal Perera'],
        ['id' => 101, 'business_id' => 7, 'user_id' => 3, 'department_id' => 10, 'full_name' => 'Sunil Silva'],
        ['id' => 103, 'business_id' => 7, 'user_id' => 4, 'department_id' => 11, 'full_name' => 'Kamala Fernando'],
        ['id' => 104, 'business_id' => 7, 'user_id' => 6, 'department_id' => 12, 'full_name' => 'Priya Jay'],
    ]);
    DB::table('crm_department_settings')->insert(['business_id' => 7, 'department_id' => 11, 'is_marketing_intake' => true]);

    foreach ([
        [1, 7, 'Sales', 10], [2, 7, 'Marketing', 11], [3, 7, 'Support', 12], [4, 7, 'Unlinked', null], [5, 8, 'Other', 20],
    ] as [$id, $businessId, $name, $departmentId]) {
        DB::table('crm_projects')->insert(['id' => $id, 'business_id' => $businessId, 'name' => $name, 'status' => 'active', 'department_id' => $departmentId]);
        DB::table('crm_lead_stages')->insert(['id' => $id, 'project_id' => $id, 'name' => 'New']);
    }
    DB::table('crm_leads')->insert([
        ['id' => 1, 'business_id' => 7, 'project_id' => 1, 'stage_id' => 1, 'name' => 'Sales assigned', 'assigned_to' => 3],
        ['id' => 2, 'business_id' => 7, 'project_id' => 1, 'stage_id' => 1, 'name' => 'Sales open',     'assigned_to' => null],
        ['id' => 3, 'business_id' => 7, 'project_id' => 2, 'stage_id' => 2, 'name' => 'Inbox lead',     'assigned_to' => null],
        ['id' => 4, 'business_id' => 7, 'project_id' => 3, 'stage_id' => 3, 'name' => 'Support lead',   'assigned_to' => 6],
        ['id' => 5, 'business_id' => 7, 'project_id' => 4, 'stage_id' => 4, 'name' => 'No department',  'assigned_to' => null],
        ['id' => 6, 'business_id' => 8, 'project_id' => 5, 'stage_id' => 5, 'name' => 'Other business', 'assigned_to' => null],
    ]);

    $this->business = Business::find(7);
    $this->policy   = app(LeadPolicy::class);
    $this->access   = fn (int $userId, ?Business $business = null) => app(CrmPermissionService::class)
        ->accessFor($business ?? $this->business, User::find($userId));
});

// ── Role matrix ──────────────────────────────────────────────────────────

test('each role may view, edit, assign, approve and delete only what its rules allow', function (int $userId, int $leadId, array $expected) {
    $access = ($this->access)($userId);
    $lead   = Lead::find($leadId);

    expect([
        'view'    => $this->policy->canView($access, $lead),
        'edit'    => $this->policy->canEdit($access, $lead),
        'assign'  => $this->policy->canAssign($access, $lead),
        'approve' => $this->policy->canApprove($access, $lead),
        'delete'  => $this->policy->canDelete($access, $lead),
    ])->toBe(array_combine(['view', 'edit', 'assign', 'approve', 'delete'], $expected));
})->with([
    //                                       view   edit   assign approve delete
    'owner, any lead'                => [1, 4, [true,  true,  true,  true,  true]],
    'admin member, any lead'         => [7, 5, [true,  true,  true,  true,  true]],
    'CEO, a department lead'         => [5, 1, [true,  false, false, false, false]],
    'CEO, a lead with no department' => [5, 5, [true,  false, false, false, false]],
    'head, own department'           => [2, 2, [true,  true,  true,  true,  true]],
    'head, another department'       => [2, 4, [false, false, false, false, false]],
    'staff, lead assigned to them'   => [3, 1, [true,  true,  false, false, false]],
    'staff, department lead'         => [3, 2, [true,  false, false, false, false]],
    'staff, another department'      => [6, 1, [false, false, false, false, false]],
    'staff, no-department pipeline'  => [3, 5, [false, false, false, false, false]],
    'marketing, intake lead'         => [4, 3, [true,  true,  false, false, false]],
    'marketing, a department lead'   => [4, 1, [false, false, false, false, false]],
    'all-departments role'           => [8, 4, [true,  true,  true,  false, true]],
]);

test('only marketing (on intake leads) and admins may forward', function () {
    expect($this->policy->canForward(($this->access)(4), Lead::find(3)))->toBeTrue()
        ->and($this->policy->canForward(($this->access)(1), Lead::find(1)))->toBeTrue()
        ->and($this->policy->canForward(($this->access)(2), Lead::find(1)))->toBeFalse()
        ->and($this->policy->canForward(($this->access)(5), Lead::find(3)))->toBeFalse();
});

test('the CEO stays view-only even when HR makes them a department head', function () {
    DB::table('hr_employees')->insert(['id' => 105, 'business_id' => 7, 'user_id' => 5, 'department_id' => 12, 'full_name' => 'Chen']);
    DB::table('hr_departments')->where('id', 12)->update(['head_employee_id' => 105]);

    $access = ($this->access)(5);

    expect($access->isHead())->toBeTrue()
        ->and($this->policy->canEdit($access, Lead::find(4)))->toBeFalse()
        ->and($this->policy->canAssign($access, Lead::find(4)))->toBeFalse()
        ->and($this->policy->canApprove($access, Lead::find(4)))->toBeFalse()
        ->and($this->policy->canManageProject($access, Project::find(3)))->toBeFalse()
        ->and($access->can('leads_edit'))->toBeFalse()
        ->and($access->can('leads_view'))->toBeTrue();
});

test('the HR co-head runs the department when the head has no login', function () {
    DB::table('hr_employees')->where('id', 100)->update(['user_id' => null]);
    DB::table('hr_departments')->where('id', 10)->update(['co_head_employee_id' => 101]);

    expect($this->policy->canAssign(($this->access)(3), Lead::find(2)))->toBeTrue()
        ->and($this->policy->canView(($this->access)(2), Lead::find(2)))->toBeFalse();
});

// ── Pipelines ────────────────────────────────────────────────────────────

test('department heads and marketing manage their own pipeline only', function () {
    $can = fn (int $userId, int $projectId) => $this->policy->canManageProject(($this->access)($userId), Project::find($projectId));

    expect($can(2, 1))->toBeTrue()
        ->and($can(2, 3))->toBeFalse()
        ->and($can(3, 1))->toBeFalse()
        ->and($can(4, 2))->toBeTrue()
        ->and($can(4, 1))->toBeFalse()
        ->and($can(5, 1))->toBeFalse()
        ->and($can(1, 4))->toBeTrue();
});

test('only admins and all-department roles create or delete relations', function () {
    expect(($this->access)(1)->can('relations_create'))->toBeTrue()
        ->and(($this->access)(8)->can('relations_delete'))->toBeTrue()
        ->and(($this->access)(2)->can('relations_create'))->toBeFalse()
        ->and(($this->access)(3)->can('relations_delete'))->toBeFalse();
});

// ── Query scope, Gate and the per-user toggles ───────────────────────────

test('Lead::visibleTo returns the same leads canView allows', function (int $userId, array $leadIds) {
    $ids = Lead::query()->visibleTo(User::find($userId), $this->business)->orderBy('id')->pluck('id')->all();

    expect($ids)->toBe($leadIds);

    $access = ($this->access)($userId);
    $byPolicy = Lead::where('business_id', 7)->get()->filter(fn ($l) => $this->policy->canView($access, $l))->pluck('id')->values()->all();
    expect($byPolicy)->toBe($leadIds);
})->with([
    'owner'      => [1, [1, 2, 3, 4, 5]],
    'CEO'        => [5, [1, 2, 3, 4, 5]],
    'head'       => [2, [1, 2]],
    'staff'      => [3, [1, 2]],
    'marketing'  => [4, [3]],
    'support'    => [6, [4]],
]);

// Gate::allows() itself is not called: spatie/laravel-permission's Gate hook needs its own tables.
test('the lead policy is registered with the Gate and answers per user', function () {
    $gate = Gate::getPolicyFor(Lead::class);

    expect($gate)->toBeInstanceOf(LeadPolicy::class)
        ->and($gate->update(User::find(3), Lead::find(1)))->toBeTrue()
        ->and($gate->update(User::find(3), Lead::find(2)))->toBeFalse()
        ->and($gate->assign(User::find(2), Lead::find(2)))->toBeTrue()
        ->and($gate->view(User::find(5), Lead::find(4)))->toBeTrue()
        ->and($gate->delete(User::find(5), Lead::find(4)))->toBeFalse();
});

test('the per-user CRM toggles still narrow a role (AND, not OR)', function () {
    app(CrmPermissionService::class)->saveForUser($this->business, 2, ['leads_edit' => false, 'leads_delete' => false], [1 => []]);

    $access = ($this->access)(2);

    expect($this->policy->canEdit($access, Lead::find(2)))->toBeFalse()
        ->and($this->policy->canDelete($access, Lead::find(2)))->toBeFalse()
        ->and($this->policy->canView($access, Lead::find(2)))->toBeFalse()   // every stage of pipeline 1 hidden
        ->and($this->policy->canViewProject($access, Project::find(1)))->toBeFalse()
        ->and(Lead::query()->visibleWith($access)->count())->toBe(0);
});

test('editableLeadIds matches canEdit lead by lead', function (int $userId) {
    $access = ($this->access)($userId);
    $leads  = Lead::where('business_id', 7)->get();

    $expected = $leads->filter(fn ($l) => $this->policy->canEdit($access, $l))->pluck('id')->sort()->values()->all();

    expect(collect(array_keys($this->policy->editableLeadIds($access, $leads)))->sort()->values()->all())->toBe($expected);
})->with([1, 2, 3, 4, 5, 6, 8]);

// ── Cashiers and tenant isolation ────────────────────────────────────────

test('a POS cashier token gets no CRM access', function () {
    $access = app(CrmPermissionService::class)->accessFor($this->business, new PosCashier(['business_id' => 7]));

    expect($access->hasCrm())->toBeFalse()
        ->and($access->can('leads_view'))->toBeFalse()
        ->and($access->can('contacts_view'))->toBeFalse()
        ->and($this->policy->canView($access, Lead::find(1)))->toBeFalse()
        ->and(Lead::query()->visibleWith($access)->count())->toBe(0);
});

test('nobody reaches another business\'s leads', function () {
    $other = Business::find(8);

    expect(Lead::query()->visibleTo(User::find(1), $this->business)->pluck('id')->all())->not->toContain(6)
        ->and(Lead::query()->visibleTo(User::find(5), $this->business)->pluck('id')->all())->not->toContain(6)
        ->and(Lead::query()->visibleTo(User::find(2), $other)->count())->toBe(0)
        ->and(($this->access)(2, $other)->hasCrm())->toBeFalse();
});

test('tasks on hidden leads are left out of the task list', function () {
    DB::table('crm_tasks')->insert([
        ['business_id' => 7, 'subject_type' => Lead::class, 'subject_id' => 1, 'title' => 'Call sales lead'],
        ['business_id' => 7, 'subject_type' => Lead::class, 'subject_id' => 4, 'title' => 'Call support lead'],
        ['business_id' => 7, 'subject_type' => null,        'subject_id' => null, 'title' => 'General'],
    ]);

    $titles = app(\Modules\CRM\Services\TaskService::class)
        ->listForBusiness($this->business, 'open', ($this->access)(3))
        ->pluck('title')->sort()->values()->all();

    expect($titles)->toBe(['Call sales lead', 'General']);
});

// ── Desktop API ──────────────────────────────────────────────────────────

class CrmLeadAccessTestApi extends PosCrmApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

class CrmLeadAccessTestPermissionApi extends PosCrmPermissionApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

function crmLeadRequest(int $userId, string $method = 'GET', array $body = []): Request
{
    $request = Request::create('/api/v1/pos/crm', $method, $body);
    $request->setUserResolver(fn () => User::find($userId));

    return $request;
}

/** Status code of a controller call, including the 403/404 it throws. */
function crmLeadStatus(callable $call): int
{
    try {
        return $call()->getStatusCode();
    } catch (HttpResponseException $e) {
        return $e->getResponse()->getStatusCode();
    } catch (HttpException $e) {
        return $e->getStatusCode();
    } catch (ModelNotFoundException) {
        return 404;
    }
}

test('/crm/permissions/me returns role, departments and the action flags', function () {
    $me = fn (int $userId) => app(CrmLeadAccessTestPermissionApi::class)->me(crmLeadRequest($userId))->getData(true)['data'];

    $head = $me(2);
    expect($head['role'])->toBe(CrmAccess::ROLE_HEAD)
        ->and($head['department_ids'])->toBe([10])
        ->and($head['head_department_ids'])->toBe([10])
        ->and($head['is_head'])->toBeTrue()
        ->and($head['can_assign'])->toBeTrue()
        ->and($head['can_approve'])->toBeTrue()
        ->and($head['permissions']['relations_create'])->toBeFalse();

    $ceo = $me(5);
    expect($ceo['read_only'])->toBeTrue()
        ->and($ceo['can_view_all_leads'])->toBeTrue()
        ->and($ceo['permissions']['leads_edit'])->toBeFalse()
        ->and($ceo['permissions']['leads_view'])->toBeTrue();

    $marketing = $me(4);
    expect($marketing['intake_department_id'])->toBe(11)
        ->and($marketing['can_forward'])->toBeTrue()
        ->and($marketing['can_assign'])->toBeFalse();
});

test('the relations list shows only visible pipelines with their flags', function () {
    $projects = fn (int $userId) => collect(app(CrmLeadAccessTestApi::class)->projects(crmLeadRequest($userId))->getData(true)['data']);

    $staff = $projects(3);
    expect($staff->pluck('id')->all())->toBe([1])
        ->and($staff->first()['can'])->toBe(['manage' => false, 'create_leads' => false, 'work_all' => false]);

    $head = $projects(2);
    expect($head->first()['can'])->toBe(['manage' => true, 'create_leads' => true, 'work_all' => true]);

    expect($projects(5)->pluck('id')->sort()->values()->all())->toBe([1, 2, 3, 4]);
});

test('a department pipeline is assigned only to its staff and head', function () {
    $org = app(\Modules\CRM\Services\CrmOrgService::class);

    expect($org->assignableUsers($this->business, 10)->pluck('id')->sort()->values()->all())->toBe([2, 3])
        ->and($org->assignableUsers($this->business, 12)->pluck('id')->all())->toBe([6])
        ->and($org->assignableUsers($this->business, null)->pluck('id')->sort()->values()->all())->toBe([1, 2, 3, 4, 5, 6, 7, 8]);
});

test('only the head re-assigns, and only to someone in the department', function () {
    $update = fn (int $userId, ?int $assignee) => crmLeadStatus(fn () => app(CrmLeadAccessTestApi::class)
        ->updateLead(crmLeadRequest($userId, 'PUT', ['name' => 'Sales open', 'assigned_to' => $assignee]), 2));

    expect($update(3, 3))->toBe(403);                                     // staff cannot assign

    expect(fn () => app(CrmLeadAccessTestApi::class)
        ->updateLead(crmLeadRequest(2, 'PUT', ['name' => 'Sales open', 'assigned_to' => 6]), 2))
        ->toThrow(\Illuminate\Validation\ValidationException::class);      // Priya is in Support

    expect(Lead::find(2)->assigned_to)->toBeNull();
});

test('the API blocks lead and pipeline changes the policy does not allow', function () {
    // A controller caches the caller's access, so each call gets its own instance (as each request does)
    $api = new class {
        public function __call(string $method, array $args)
        {
            return app(CrmLeadAccessTestApi::class)->{$method}(...$args);
        }
    };

    expect(crmLeadStatus(fn () => $api->moveLead(crmLeadRequest(3, 'POST', ['stage_id' => 1]), 2)))->toBe(403)  // staff, not assigned
        ->and(crmLeadStatus(fn () => $api->deleteLead(crmLeadRequest(3, 'DELETE'), 1)))->toBe(403)                // staff cannot delete
        ->and(crmLeadStatus(fn () => $api->leadHistory(crmLeadRequest(6), 1)))->toBe(404)                         // other department
        ->and(crmLeadStatus(fn () => $api->leadHistory(crmLeadRequest(1), 6)))->toBe(404)                         // other business
        ->and(crmLeadStatus(fn () => $api->createLead(crmLeadRequest(3, 'POST', ['name' => 'X']), 1)))->toBe(403) // staff cannot add
        ->and(crmLeadStatus(fn () => $api->createLead(crmLeadRequest(5, 'POST', ['name' => 'X']), 1)))->toBe(403) // CEO cannot add
        ->and(crmLeadStatus(fn () => $api->createStage(crmLeadRequest(3, 'POST', ['name' => 'Won']), 1)))->toBe(403)
        ->and(crmLeadStatus(fn () => $api->createStage(crmLeadRequest(2, 'POST', ['name' => 'Won']), 3)))->toBe(404) // head, other dept
        ->and(crmLeadStatus(fn () => $api->tasksList(crmLeadRequest(1))))->toBe(200)
        ->and(Lead::count())->toBe(6);
});
