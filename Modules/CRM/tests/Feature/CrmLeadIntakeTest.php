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
use Modules\Business\Models\BusinessRole;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAssignmentLog;
use Modules\CRM\Models\LeadAuditLog;
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Models\Project;
use Modules\CRM\Models\Task;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\CrmSettingsService;
use Modules\CRM\Services\DuplicateLeadService;
use Modules\CRM\Services\LeadFormService;
use Modules\CRM\Services\LeadIntakeService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\MarketingInboxService;
use Modules\CRM\Services\ProjectService;
use Modules\CRM\Support\PhoneNormalizer;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Http\Controllers\Api\PosCrmApiController;
use Modules\Pos\Http\Controllers\Api\PosCrmInboxApiController;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** The migrations added for the marketing intake, in the order they run. */
function crmIntakeMigrations(): array
{
    $dir = dirname(__DIR__, 2) . '/database/migrations/';

    return [
        $dir . '2026_11_02_100000_create_crm_settings_table.php',
        $dir . '2026_11_02_100100_add_intake_fields_to_crm_leads_table.php',
        $dir . '2026_11_02_100200_create_crm_lead_assignment_logs_table.php',
    ];
}

/*
 * Business 7 "ABC Solar" (owner user 1):
 *   Sales (10)     head Nimal (2), staff Sunil (3), division Solar (1)  → pipeline "Sales"
 *   Marketing (11) Kamala (4) — the marketing intake                    → pipeline "Marketing"
 *   Support (12)   no pipeline
 *   Chen (5) is the CEO.
 * Business 8 (owner user 9): Sales (20) with its own pipeline and its own marketing intake (21).
 * Every CRM table comes from the real migrations.
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
    Schema::create('hr_departments', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->unsignedBigInteger('head_employee_id')->nullable();
        $t->unsignedBigInteger('co_head_employee_id')->nullable();
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
    Schema::create('pos_customers', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->string('phone')->nullable();
        $t->string('normalized_phone', 32)->nullable();
        $t->string('email')->nullable();
        $t->text('notes')->nullable();
        $t->timestamps();
    });
    Schema::create('products', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->string('sku')->nullable();
        $t->boolean('is_active')->default(true);
        $t->timestamps();
    });

    foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.php') as $file) {
        (require $file)->up();
    }

    DB::table('users')->insert(collect([1 => 'Owner', 2 => 'Nimal', 3 => 'Sunil', 4 => 'Kamala', 5 => 'Chen', 9 => 'Other owner'])
        ->map(fn ($name, $id) => ['id' => $id, 'name' => $name, 'email' => strtolower($name) . '@example.com'])->values()->all());
    DB::table('businesses')->insert([
        ['id' => 7, 'user_id' => 1, 'name' => 'ABC Solar'],
        ['id' => 8, 'user_id' => 9, 'name' => 'Other Co'],
    ]);
    BusinessRole::seedForBusiness(7);
    $crmKeys = json_encode(['crm_pipeline', 'crm_contacts', 'crm_tasks', 'crm_forms']);
    DB::table('business_members')->insert([
        ['business_id' => 7, 'user_id' => 2, 'role' => 'crm_department_head', 'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 3, 'role' => 'crm_staff',           'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 4, 'role' => 'crm_marketing',       'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 5, 'role' => 'crm_ceo',             'permissions' => $crmKeys],
    ]);
    DB::table('hr_departments')->insert([
        ['id' => 10, 'business_id' => 7, 'name' => 'Sales',     'head_employee_id' => 100],
        ['id' => 11, 'business_id' => 7, 'name' => 'Marketing', 'head_employee_id' => null],
        ['id' => 12, 'business_id' => 7, 'name' => 'Support',   'head_employee_id' => null],
        ['id' => 20, 'business_id' => 8, 'name' => 'Sales',     'head_employee_id' => null],
        ['id' => 21, 'business_id' => 8, 'name' => 'Marketing', 'head_employee_id' => null],
    ]);
    DB::table('hr_employees')->insert([
        ['id' => 100, 'business_id' => 7, 'user_id' => 2, 'department_id' => 10, 'full_name' => 'Nimal Perera'],
        ['id' => 101, 'business_id' => 7, 'user_id' => 3, 'department_id' => 10, 'full_name' => 'Sunil Silva'],
        ['id' => 103, 'business_id' => 7, 'user_id' => 4, 'department_id' => 11, 'full_name' => 'Kamala Fernando'],
    ]);
    DB::table('crm_divisions')->insert([
        ['id' => 1, 'business_id' => 7, 'department_id' => 10, 'name' => 'Solar',    'is_active' => true,  'sort_order' => 1],
        ['id' => 2, 'business_id' => 7, 'department_id' => 10, 'name' => 'Retired',  'is_active' => false, 'sort_order' => 2],
        ['id' => 3, 'business_id' => 8, 'department_id' => 20, 'name' => 'Other',    'is_active' => true,  'sort_order' => 1],
    ]);

    $this->business = Business::find(7);
    $this->other    = Business::find(8);
    $org            = app(CrmOrgService::class);

    $this->sales     = $org->linkPipeline($this->business, Department::find(10), null);
    $this->marketing = $org->linkPipeline($this->business, Department::find(11), null);
    $org->saveSettings($this->business, Department::find(11), ['is_marketing_intake' => true]);

    $this->otherSales     = $org->linkPipeline($this->other, Department::find(20), null);
    $this->otherMarketing = $org->linkPipeline($this->other, Department::find(21), null);
    $org->saveSettings($this->other, Department::find(21), ['is_marketing_intake' => true]);

    $this->intake  = app(LeadIntakeService::class);
    $this->inbox   = app(MarketingInboxService::class);
    $this->leads   = app(LeadService::class);
    $this->policy  = app(LeadPolicy::class);
    $this->access  = fn (int $userId) => app(CrmPermissionService::class)->accessFor($this->business, User::find($userId));
    $this->capture = fn (array $data, ?Project $into = null, string $source = 'manual', ?int $userId = 1) =>
        $this->intake->capture($data + ['name' => 'Kasun'], $source, $into ?? $this->sales, $userId);
});

// ── Phone normaliser ─────────────────────────────────────────────────────

test('phone numbers are normalised to one form', function (?string $input, ?string $country, ?string $expected) {
    expect(PhoneNormalizer::normalize($input, $country))->toBe($expected);
})->with([
    'spaces and dash'           => ['077 123-4567',      '94', '94771234567'],
    'local leading zero'        => ['0771234567',        '94', '94771234567'],
    'plus and country code'     => ['+94 77 123 4567',   '94', '94771234567'],
    '00 international prefix'   => ['0094771234567',     '94', '94771234567'],
    'no leading zero'           => ['771234567',         '94', '94771234567'],
    'country code without plus' => ['94771234567',       '94', '94771234567'],
    'brackets and dots'         => ['(077) 123.4567',    '94', '94771234567'],
    'another country with plus' => ['+44 20 7946 0958',  '94', '442079460958'],
    'no country code set'       => ['0771234567',        null, '771234567'],
    'too short'                 => ['12345',             '94', null],
    'empty'                     => ['',                  '94', null],
    'null'                      => [null,                '94', null],
]);

test('the country code comes from the CRM settings, falling back to the default', function () {
    $settings = app(CrmSettingsService::class);

    expect($settings->phoneCountryCode($this->business))->toBe('94');

    $settings->save($this->business, ['phone_country_code' => '+44']);

    expect($settings->phoneCountryCode($this->business))->toBe('44')
        ->and($settings->phoneCountryCode($this->other))->toBe('94')
        ->and($settings->normalizePhone($this->business, '07700 900123'))->toBe('447700900123');
});

// ── Intake routing ───────────────────────────────────────────────────────

test('a new lead lands in the marketing intake pipeline at status new', function () {
    $result = ($this->capture)(['phone' => '0771234567', 'email' => 'Kasun@Example.com', 'stage_id' => 999, 'assigned_to' => 3, 'division_id' => 1], $this->sales, 'manual', 2);
    $lead   = $result->lead->fresh(['stage']);

    expect($result->created)->toBeTrue()
        ->and($result->routedToIntake)->toBeTrue()
        ->and($lead->project_id)->toBe($this->marketing->id)
        ->and($lead->department_id)->toBe(11)
        ->and($lead->stage->system_key)->toBe('new')
        ->and($lead->assigned_to)->toBeNull()
        ->and($lead->division_id)->toBeNull()
        ->and($lead->requested_department_id)->toBe(10)
        ->and($lead->source)->toBe('manual')
        ->and($lead->source_id)->not->toBeNull()
        ->and($lead->created_by)->toBe(2)
        ->and($lead->email)->toBe('kasun@example.com')
        ->and($lead->normalized_phone)->toBe('94771234567');
});

test('the source key is stored as the lead source', function (string $key, string $leadSource) {
    expect(($this->capture)(['phone' => '0770000001'], null, $key)->lead->source)->toBe($leadSource);
})->with([
    ['web_form', 'public-form'],
    ['csv', 'csv'],
    ['google_form', 'google-form'],
    ['facebook', 'facebook'],
    ['whatsapp', 'whatsapp'],
]);

test('without an intake department the lead stays in the pipeline it was entered in', function () {
    app(CrmOrgService::class)->saveSettings($this->business, Department::find(11), ['is_marketing_intake' => false]);

    $result = ($this->capture)(['phone' => '0771234567', 'division_id' => 1]);

    expect($result->routedToIntake)->toBeFalse()
        ->and($result->lead->project_id)->toBe($this->sales->id)
        ->and($result->lead->division_id)->toBe(1);
});

test('a pipeline no department owns keeps its leads', function () {
    $loose  = app(ProjectService::class)->create($this->business, ['name' => 'Events']);
    $result = ($this->capture)(['phone' => '0771234567'], $loose);

    expect($result->lead->project_id)->toBe($loose->id)
        ->and($result->routedToIntake)->toBeFalse();
});

test('supplier and employee forms skip intake and the duplicate check', function () {
    $form = app(LeadFormService::class)->create($this->sales, ['name' => 'Suppliers', 'template' => 'supplier_registration']);
    expect($form->type)->toBe(LeadForm::TYPE_SUPPLIER);

    $first  = ($this->capture)(['phone' => '0771234567', 'form_id' => $form->id]);
    $second = ($this->capture)(['phone' => '0771234567', 'form_id' => $form->id]);
    $sales  = ($this->capture)(['phone' => '0771234567']);

    expect($first->lead->project_id)->toBe($this->sales->id)
        ->and($second->created)->toBeTrue()
        ->and($second->lead->id)->not->toBe($first->lead->id)
        // A supplier record is not an earlier enquiry of the same contact
        ->and($sales->created)->toBeTrue()
        ->and($sales->lead->project_id)->toBe($this->marketing->id);
});

test('the public web form and the desktop form flow go through intake', function () {
    $form   = app(LeadFormService::class)->create($this->sales, ['name' => 'Contact', 'template' => 'contact']);
    $paths  = collect($form->fieldBlocksWithPaths())->mapWithKeys(fn ($b, $path) => [$b['field'] => $path]);
    $result = app(LeadFormService::class)->submit($form, [
        $paths['name']  => 'Web visitor',
        $paths['email'] => 'visitor@example.com',
        $paths['phone'] => '077 765 4321',
    ]);

    expect($result->routedToIntake)->toBeTrue()
        ->and($result->lead->project_id)->toBe($this->marketing->id)
        ->and($result->lead->source)->toBe('public-form')
        ->and($result->lead->form_id)->toBe($form->id);
});

// ── Duplicates ───────────────────────────────────────────────────────────

test('a repeat enquiry on any phone variant is logged on the existing lead instead of a new lead', function (string $variant) {
    $first  = ($this->capture)(['phone' => '0771234567']);
    $repeat = ($this->capture)(['name' => 'Kasun P', 'phone' => $variant, 'notes' => 'Wants 10 panels'], null, 'whatsapp');

    $activity = Activity::where('subject_id', $first->lead->id)->where('type', Activity::TYPE_ENQUIRY)->first();

    expect($repeat->created)->toBeFalse()
        ->and($repeat->lead->id)->toBe($first->lead->id)
        ->and(Lead::where('business_id', 7)->count())->toBe(1)
        ->and($first->lead->fresh()->needs_review)->toBeTrue()
        ->and($first->lead->fresh()->review_reason)->toBe(Lead::REVIEW_DUPLICATE_ENQUIRY)
        ->and($activity->body)->toContain('WhatsApp')->toContain('Wants 10 panels')->toContain('Kasun P')
        ->and(LeadAuditLog::where('lead_id', $first->lead->id)->where('event', LeadAuditLog::EVENT_ENQUIRY)->exists())->toBeTrue();
})->with(['077 123 4567', '+94 77 123-4567', '0094771234567', '771234567']);

test('emails match whatever their case', function () {
    $first  = ($this->capture)(['email' => 'Kasun@Example.com']);
    $repeat = ($this->capture)(['email' => '  kasun@EXAMPLE.com ']);

    expect($repeat->created)->toBeFalse()
        ->and($repeat->lead->id)->toBe($first->lead->id);
});

test('a phone match counts even when the email differs, and the other way round', function () {
    $first = ($this->capture)(['phone' => '0771234567', 'email' => 'a@example.com']);

    expect(($this->capture)(['phone' => '+94771234567', 'email' => 'b@example.com'])->lead->id)->toBe($first->lead->id)
        ->and(($this->capture)(['phone' => '0711111111', 'email' => 'A@example.com'])->lead->id)->toBe($first->lead->id)
        ->and(($this->capture)(['phone' => '0712222222', 'email' => 'c@example.com'])->created)->toBeTrue();
});

test('a lead matching only a closed lead is created and flagged as a possible duplicate', function () {
    $old = ($this->capture)(['phone' => '0771234567'])->lead;
    $this->leads->markLost($old, app(\Modules\CRM\Services\LostReasonService::class)->listForBusiness($this->business)->first()->id, null, 1);

    $new = ($this->capture)(['phone' => '0771234567']);

    expect($new->created)->toBeTrue()
        ->and($new->lead->needs_review)->toBeTrue()
        ->and($new->lead->review_reason)->toBe(Lead::REVIEW_POSSIBLE_DUPLICATE)
        ->and($new->lead->duplicate_of_lead_id)->toBe($old->id);
});

test('a lead matching an existing customer is linked to it and flagged', function () {
    DB::table('pos_customers')->insert([
        ['id' => 50, 'business_id' => 7, 'name' => 'Kasun Customer', 'phone' => '077-123 4567', 'email' => null],
        ['id' => 51, 'business_id' => 8, 'name' => 'Elsewhere',      'phone' => null,           'email' => 'x@example.com'],
    ]);

    $byPhone = ($this->capture)(['phone' => '+94 77 1234567'])->lead;
    $byEmail = ($this->capture)(['email' => 'X@example.com'])->lead;

    expect($byPhone->customer_id)->toBe(50)
        ->and($byPhone->review_reason)->toBe(Lead::REVIEW_EXISTING_CUSTOMER)
        // A customer of another business is not a match
        ->and($byEmail->customer_id)->toBeNull()
        ->and($byEmail->needs_review)->toBeFalse();
});

test('the duplicate check never looks at another business', function () {
    $theirs = $this->intake->capture(['name' => 'Theirs', 'phone' => '0771234567', 'email' => 'k@example.com'], 'manual', $this->otherSales, 9);
    $ours   = ($this->capture)(['phone' => '0771234567', 'email' => 'k@example.com']);

    expect($ours->created)->toBeTrue()
        ->and($ours->lead->business_id)->toBe(7)
        ->and($theirs->lead->fresh()->needs_review)->toBeFalse()
        ->and(app(DuplicateLeadService::class)->matchingLeads(7, '0771234567', null)->pluck('id')->all())->toBe([$ours->lead->id]);
});

// ── Desktop create endpoints ─────────────────────────────────────────────

class CrmLeadIntakeTestApi extends PosCrmApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

class CrmLeadIntakeTestInboxApi extends PosCrmInboxApiController
{
    public static int $businessId = 7;

    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(self::$businessId);
    }
}

function crmIntakeRequest(int $userId, string $method = 'GET', array $body = []): Request
{
    $request = Request::create('/api/v1/pos/crm', $method, $body);
    $request->setUserResolver(fn () => User::find($userId));

    return $request;
}

/** Status code of a controller call, including the 403/404/422 it throws. */
function crmIntakeStatus(callable $call): int
{
    try {
        return $call()->getStatusCode();
    } catch (HttpResponseException $e) {
        return $e->getResponse()->getStatusCode();
    } catch (HttpException $e) {
        return $e->getStatusCode();
    } catch (ModelNotFoundException) {
        return 404;
    } catch (ValidationException) {
        return 422;
    }
}

test('the desktop create-lead endpoint goes through intake and says what happened', function () {
    // Controllers cache the caller's CRM access, so each user gets a fresh one
    $api = fn () => app(CrmLeadIntakeTestApi::class);

    $created = $api()->createLead(crmIntakeRequest(2, 'POST', ['name' => 'Nadeesha', 'phone' => '0779999999']), $this->sales->id);
    $body    = $created->getData(true);

    expect($created->getStatusCode())->toBe(201)
        ->and($body['intake']['routed_to_intake'])->toBeTrue()
        ->and($body['intake']['created'])->toBeTrue()
        // The Sales head cannot see the marketing inbox lead
        ->and($body['data'])->toBeNull()
        ->and(Lead::where('name', 'Nadeesha')->value('project_id'))->toBe($this->marketing->id);

    $repeat = $api()->createLead(crmIntakeRequest(1, 'POST', ['name' => 'Nadeesha again', 'phone' => '+94 77 999 9999']), $this->sales->id)->getData(true);

    expect($repeat['intake']['duplicate'])->toBeTrue()
        ->and($repeat['data']['name'])->toBe('Nadeesha')
        ->and(Lead::where('business_id', 7)->count())->toBe(1);
});

test('the desktop form flow returns the intake result', function () {
    $form  = app(LeadFormService::class)->create($this->sales, ['name' => 'Contact', 'template' => 'contact']);
    $paths = collect($form->fieldBlocksWithPaths())->mapWithKeys(fn ($b, $path) => [$b['field'] => $path]);

    $res = app(CrmLeadIntakeTestApi::class)->submitFormLead(crmIntakeRequest(1, 'POST', [
        $paths['name'] => 'Desk', $paths['email'] => 'desk@example.com', 'source' => 'phone',
    ]), $this->sales->id, $form->id);

    expect($res->getStatusCode())->toBe(201)
        ->and($res->getData(true)['intake']['routed_to_intake'])->toBeTrue()
        ->and(Lead::where('name', 'Desk')->value('source'))->toBe('phone');
});

// ── Forwarding ───────────────────────────────────────────────────────────

test('only marketing and admins may forward an intake lead', function (int $userId, bool $allowed) {
    $lead = ($this->capture)(['phone' => '0771234567'])->lead;

    expect($this->policy->canForward(($this->access)($userId), $lead))->toBe($allowed);

    $status = crmIntakeStatus(fn () => app(CrmLeadIntakeTestInboxApi::class)->forward(
        crmIntakeRequest($userId, 'POST', ['department_id' => 10]), $lead->id));

    // Heads and staff are stopped at the inbox itself; the CEO opens it but may not act
    expect($status)->toBe($allowed ? 200 : 403)
        ->and($lead->fresh()->project_id)->toBe($allowed ? $this->sales->id : $this->marketing->id);
})->with([
    'owner'           => [1, true],
    'marketing'       => [4, true],
    'department head' => [2, false],
    'staff'           => [3, false],
    'CEO'             => [5, false],
]);

test('forwarding moves the lead to the department pipeline and records the history', function () {
    $lead = ($this->capture)(['phone' => '0771234567'], null, 'manual', 4)->lead;
    $lead->update(['needs_review' => true, 'review_reason' => Lead::REVIEW_EXISTING_CUSTOMER]);

    $this->inbox->forward($lead, 10, 1, 'Wants a 5kW system', 4);
    $lead = $lead->fresh(['stage']);
    $log  = LeadAssignmentLog::where('lead_id', $lead->id)->sole();

    expect($lead->project_id)->toBe($this->sales->id)
        ->and($lead->department_id)->toBe(10)
        ->and($lead->division_id)->toBe(1)
        ->and($lead->stage->system_key)->toBe('new')
        ->and($lead->stage->project_id)->toBe($this->sales->id)
        ->and($lead->forwarded_by)->toBe(4)
        ->and($lead->verified_by)->toBe(4)
        ->and($lead->needs_review)->toBeFalse()
        ->and($log->action)->toBe(LeadAssignmentLog::ACTION_FORWARDED)
        ->and($log->business_id)->toBe(7)
        ->and([$log->from_department_id, $log->to_department_id, $log->to_division_id, $log->by_user_id])->toBe([11, 10, 1, 4])
        ->and($log->note)->toBe('Wants a 5kW system')
        ->and(LeadAuditLog::where('lead_id', $lead->id)->where('event', LeadAuditLog::EVENT_FORWARDED)->value('project_id'))->toBe($this->sales->id)
        ->and(\Modules\CRM\Models\LeadStageLog::where('lead_id', $lead->id)->latest('id')->value('project_id'))->toBe($this->sales->id);
});

test('after forwarding, marketing still sees the lead but no longer works it, and Sales does', function () {
    $lead = ($this->capture)(['phone' => '0771234567'], null, 'manual', 4)->lead;
    $this->inbox->forward($lead, 10, null, null, 4);
    $lead = $lead->fresh();

    $marketing = ($this->access)(4);
    $head      = ($this->access)(2);

    expect($this->policy->canView($marketing, $lead))->toBeTrue()
        ->and($this->policy->canEdit($marketing, $lead))->toBeFalse()
        ->and($this->policy->canForward($marketing, $lead))->toBeFalse()
        ->and(Lead::query()->visibleWith($marketing)->pluck('id')->all())->toContain($lead->id)
        ->and($this->policy->canEdit($head, $lead))->toBeTrue()
        ->and($this->policy->canAssign($head, $lead))->toBeTrue();
});

test('forwarding needs a department of this business with a pipeline and one of its active divisions', function (callable $target, string $field) {
    $lead = ($this->capture)(['phone' => '0771234567'])->lead;
    [$departmentId, $divisionId] = $target($this);

    try {
        $this->inbox->forward($lead, $departmentId, $divisionId, null, 1);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
})->with([
    'another business\'s department' => [fn () => [20, null], 'department_id'],
    'department without a pipeline'  => [fn () => [12, null], 'department_id'],
    'the intake department itself'   => [fn () => [11, null], 'department_id'],
    'division of another department' => [fn () => [10, 3], 'division_id'],
    'inactive division'              => [fn () => [10, 2], 'division_id'],
]);

test('a closed lead cannot be forwarded', function () {
    $lead = ($this->capture)(['phone' => '0771234567'])->lead;
    $this->leads->markLost($lead, app(\Modules\CRM\Services\LostReasonService::class)->listForBusiness($this->business)->first()->id, null, 1);

    expect(fn () => $this->inbox->forward($lead->fresh(), 10, null, null, 1))->toThrow(ValidationException::class);
});

// ── Inbox ────────────────────────────────────────────────────────────────

test('the inbox lists new intake leads and flagged leads the user can see', function () {
    $new      = ($this->capture)(['name' => 'Fresh', 'phone' => '0771111111'])->lead;
    $verified = ($this->capture)(['name' => 'Checked', 'phone' => '0772222222'])->lead;
    $this->inbox->verify($verified, 4);
    $moved    = ($this->capture)(['name' => 'Moved', 'phone' => '0773333333'])->lead;
    $this->inbox->forward($moved, 10, null, null, 4);
    ($this->capture)(['name' => 'Moved again', 'phone' => '0773333333']); // repeat enquiry flags "Moved"
    $this->intake->capture(['name' => 'Theirs', 'phone' => '0774444444'], 'manual', $this->otherSales, 9);

    $ids = fn (string $filter) => $this->inbox->list($this->business, ($this->access)(4), $filter)->pluck('name')->sort()->values()->all();

    expect($ids('all'))->toBe(['Checked', 'Fresh', 'Moved'])
        ->and($ids('review'))->toBe(['Moved'])
        ->and($ids('unverified'))->toBe(['Fresh'])
        ->and($ids('verified'))->toBe(['Checked', 'Moved'])
        ->and($this->inbox->counts($this->business, ($this->access)(4)))->toBe(['all' => 3, 'review' => 1, 'unverified' => 1, 'verified' => 2]);
});

test('only marketing and those who see every lead open the inbox', function (int $userId, int $status) {
    ($this->capture)(['phone' => '0771111111']);

    expect(crmIntakeStatus(fn () => app(CrmLeadIntakeTestInboxApi::class)->index(crmIntakeRequest($userId))))->toBe($status);
})->with([
    'owner'     => [1, 200],
    'marketing' => [4, 200],
    'CEO'       => [5, 200],
    'head'      => [2, 403],
    'staff'     => [3, 403],
]);

test('the inbox API returns the leads, counts and forward targets of the business only', function () {
    ($this->capture)(['name' => 'Ours', 'phone' => '0771111111']);
    $this->intake->capture(['name' => 'Theirs', 'phone' => '0772222222'], 'manual', $this->otherSales, 9);

    $data = app(CrmLeadIntakeTestInboxApi::class)->index(crmIntakeRequest(4))->getData(true)['data'];

    expect(collect($data['leads'])->pluck('name')->all())->toBe(['Ours'])
        ->and($data['leads'][0]['can'])->toBe(['edit' => true, 'forward' => true, 'review' => true])
        ->and($data['counts']['all'])->toBe(1)
        ->and($data['intake_project']['id'])->toBe($this->marketing->id)
        ->and(collect($data['forward_targets'])->pluck('id')->all())->toBe([10])
        ->and($data['forward_targets'][0]['divisions'])->toBe([['id' => 1, 'name' => 'Solar']]);
});

test('an inbox lead of another business is not found', function () {
    $theirs = $this->intake->capture(['name' => 'Theirs', 'phone' => '0772222222'], 'manual', $this->otherSales, 9)->lead;
    $api    = app(CrmLeadIntakeTestInboxApi::class);

    expect(crmIntakeStatus(fn () => $api->forward(crmIntakeRequest(1, 'POST', ['department_id' => 10]), $theirs->id)))->toBe(404)
        ->and(crmIntakeStatus(fn () => $api->verify(crmIntakeRequest(1, 'POST'), $theirs->id)))->toBe(404)
        ->and(crmIntakeStatus(fn () => $api->show(crmIntakeRequest(1), $theirs->id)))->toBe(404)
        ->and($theirs->fresh()->verified_at)->toBeNull();
});

test('verifying, cleaning up and clearing the review flag', function () {
    $lead = ($this->capture)(['phone' => '0771111111'])->lead;
    $api  = fn () => app(CrmLeadIntakeTestInboxApi::class);

    $api()->verify(crmIntakeRequest(4, 'POST'), $lead->id);
    $res = $api()->update(crmIntakeRequest(4, 'PUT', ['name' => 'Kasun Perera', 'email' => 'KP@Example.com', 'phone' => '077 111 1111']), $lead->id)->getData(true);

    expect($lead->fresh()->verified_by)->toBe(4)
        ->and($res['data']['name'])->toBe('Kasun Perera')
        ->and($res['data']['email'])->toBe('kp@example.com')
        ->and($lead->fresh()->normalized_phone)->toBe('94771111111');

    $lead->update(['needs_review' => true, 'review_reason' => Lead::REVIEW_DUPLICATE_ENQUIRY]);
    expect(crmIntakeStatus(fn () => $api()->review(crmIntakeRequest(2, 'POST'), $lead->id)))->toBe(403)
        ->and(crmIntakeStatus(fn () => $api()->review(crmIntakeRequest(5, 'POST'), $lead->id)))->toBe(403)
        ->and($lead->fresh()->needs_review)->toBeTrue()
        ->and(crmIntakeStatus(fn () => $api()->review(crmIntakeRequest(4, 'POST'), $lead->id)))->toBe(200)
        ->and($lead->fresh()->needs_review)->toBeFalse();
});

test('editing a lead to another lead\'s phone flags it as a possible duplicate', function () {
    $first  = ($this->capture)(['phone' => '0771111111'])->lead;
    $second = ($this->capture)(['phone' => '0772222222'])->lead;

    $this->inbox->update($second, ['name' => $second->name, 'phone' => '+94 77 111 1111'], 4);

    expect($second->fresh()->needs_review)->toBeTrue()
        ->and($second->fresh()->duplicate_of_lead_id)->toBe($first->id);
});

// ── Merge ────────────────────────────────────────────────────────────────

test('merging a duplicate keeps its activities and tasks and fills the gaps on the other lead', function () {
    DB::table('products')->insert(['id' => 1, 'business_id' => 7, 'name' => 'Panel']);
    $target = $this->leads->create($this->marketing, ['name' => 'Kasun', 'phone' => '0771111111', 'notes' => 'First contact'], 4);
    $source = $this->leads->create($this->marketing, ['name' => 'Kasun P', 'phone' => '0771111111', 'email' => 'kasun@example.com', 'notes' => 'Second form', 'products' => [['product_id' => 1, 'quantity' => 2]]], 4);
    $source->update(['needs_review' => true, 'review_reason' => Lead::REVIEW_POSSIBLE_DUPLICATE, 'duplicate_of_lead_id' => $target->id]);

    $activity = Activity::create(['business_id' => 7, 'subject_type' => $source->getMorphClass(), 'subject_id' => $source->id, 'type' => 'call', 'body' => 'Called', 'occurred_at' => now()]);
    $task     = Task::create(['business_id' => 7, 'subject_type' => $source->getMorphClass(), 'subject_id' => $source->id, 'title' => 'Call back', 'status' => Task::STATUS_PENDING]);

    $res = app(CrmLeadIntakeTestInboxApi::class)->merge(crmIntakeRequest(4, 'POST', ['target_lead_id' => $target->id]), $source->id);
    $target = $target->fresh();

    expect($res->getStatusCode())->toBe(200)
        ->and(Lead::find($source->id))->toBeNull()
        ->and($activity->fresh()->subject_id)->toBe($target->id)
        ->and($task->fresh()->subject_id)->toBe($target->id)
        ->and($target->email)->toBe('kasun@example.com')
        ->and($target->notes)->toContain('First contact')->toContain('Second form')
        ->and($target->products()->pluck('products.id')->all())->toBe([1])
        ->and(LeadAuditLog::where('lead_id', $target->id)->where('event', LeadAuditLog::EVENT_MERGED)->exists())->toBeTrue()
        ->and(LeadAuditLog::where('lead_id', $source->id)->where('event', LeadAuditLog::EVENT_DELETED)->value('meta'))->toMatchArray(['merged_into' => $target->id]);
});

test('only marketing and admins merge, and never across businesses', function () {
    $a      = $this->leads->create($this->marketing, ['name' => 'A', 'phone' => '0771111111'], 4);
    $b      = $this->leads->create($this->marketing, ['name' => 'B', 'phone' => '0771111111'], 4);
    $theirs = $this->leads->create($this->otherMarketing, ['name' => 'Theirs', 'phone' => '0771111111'], 9);
    $api    = fn () => app(CrmLeadIntakeTestInboxApi::class);

    expect(crmIntakeStatus(fn () => $api()->merge(crmIntakeRequest(5, 'POST', ['target_lead_id' => $b->id]), $a->id)))->toBe(403)
        ->and(crmIntakeStatus(fn () => $api()->merge(crmIntakeRequest(4, 'POST', ['target_lead_id' => $theirs->id]), $a->id)))->toBe(404)
        ->and(crmIntakeStatus(fn () => $api()->merge(crmIntakeRequest(4, 'POST', ['target_lead_id' => $a->id]), $a->id)))->toBe(422)
        ->and(fn () => $this->inbox->merge($a, $theirs, 1))->toThrow(ValidationException::class)
        ->and(Lead::find($a->id))->not->toBeNull();
});

// ── Settings & migrations ────────────────────────────────────────────────

test('only admins change the phone country code', function () {
    $api = new class(app(CrmOrgService::class), app(CrmPermissionService::class), app(CrmSettingsService::class)) extends \Modules\Pos\Http\Controllers\Api\PosCrmOrgApiController {
        protected function businessOrAbort(Request $request): Business
        {
            return Business::find(7);
        }
    };

    expect(crmIntakeStatus(fn () => $api->saveCrmSettings(crmIntakeRequest(4, 'PUT', ['phone_country_code' => '44']))))->toBe(403)
        ->and($api->saveCrmSettings(crmIntakeRequest(1, 'PUT', ['phone_country_code' => '+44']))->getData(true)['data']['effective_phone_country_code'])->toBe('44')
        ->and(app(CrmSettingsService::class)->phoneCountryCode(8))->toBe('94');
});

test('the backfill command fills the normalised phone of older leads', function () {
    DB::table('crm_leads')->insert(['business_id' => 7, 'project_id' => $this->sales->id, 'name' => 'Old', 'phone' => '077 123 4567']);

    $this->artisan('crm:normalize-lead-phones')->assertSuccessful();
    expect(Lead::where('name', 'Old')->value('normalized_phone'))->toBeNull();

    $this->artisan('crm:normalize-lead-phones', ['--apply' => true])->assertSuccessful();
    expect(Lead::where('name', 'Old')->value('normalized_phone'))->toBe('94771234567');
});

test('the intake migrations roll back and run again on SQLite', function () {
    foreach (array_reverse(crmIntakeMigrations()) as $file) {
        (require $file)->down();
    }

    expect(Schema::hasTable('crm_settings'))->toBeFalse()
        ->and(Schema::hasTable('crm_lead_assignment_logs'))->toBeFalse()
        ->and(Schema::hasColumn('crm_leads', 'normalized_phone'))->toBeFalse()
        ->and(Schema::hasColumn('crm_leads', 'verified_at'))->toBeTrue();

    foreach (crmIntakeMigrations() as $file) {
        (require $file)->up();
    }

    expect(Schema::hasColumns('crm_leads', ['normalized_phone', 'needs_review', 'review_reason', 'duplicate_of_lead_id', 'requested_department_id', 'forwarded_at', 'forwarded_by']))->toBeTrue();
});
