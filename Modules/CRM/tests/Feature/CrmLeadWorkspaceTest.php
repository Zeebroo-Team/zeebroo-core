<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessRole;
use Modules\CRM\Models\CrmAuditLog;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAssignee;
use Modules\CRM\Models\LeadAssignmentLog;
use Modules\CRM\Models\LeadAttachment;
use Modules\CRM\Models\LeadMessage;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\CRM\Services\LeadAttachmentService;
use Modules\CRM\Services\LeadMessageService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\MarketingInboxService;
use Modules\CRM\Services\Timeline\TimelineEntry;
use Modules\CRM\Services\Timeline\TimelineService;
use Modules\CRM\Services\Timeline\TimelineSource;
use Modules\FileManager\Http\Controllers\FileManagerFileController;
use Modules\FileManager\Models\FileManagerFile;
use Modules\FileManager\Models\FileManagerFolder;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Http\Controllers\Api\PosCrmApiController;
use Modules\Pos\Http\Controllers\Api\PosCrmLeadWorkspaceApiController;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** The migrations added for assignees and the lead workspace, in the order they run. */
function crmWorkspaceMigrations(): array
{
    $dir = dirname(__DIR__, 2) . '/database/migrations/';

    return [
        $dir . '2026_11_04_100000_create_crm_lead_assignees_table.php',
        $dir . '2026_11_04_100100_add_from_user_ids_to_crm_lead_assignment_logs_table.php',
        $dir . '2026_11_04_110000_create_crm_lead_messages_table.php',
        $dir . '2026_11_04_110100_create_crm_lead_attachments_table.php',
        $dir . '2026_11_04_110200_create_crm_audit_logs_table.php',
        $dir . '2026_11_04_110300_add_message_id_to_crm_lead_attachments_table.php',
    ];
}

/*
 * Business 7 "ABC Solar" (owner user 1):
 *   Sales (10)     head Nimal (2), staff Sunil (3) and Priya (6)  → pipeline "Sales"
 *   Marketing (11) Kamala (4) — the marketing intake               → pipeline "Marketing"
 *   Support (12)   head Dinesh (8)                                 → pipeline "Support"
 *   Chen (5) is the CEO.
 * Business 8 (owner user 9): Sales (20) with its own pipeline and lead.
 * Every CRM and FileManager table comes from the real migrations.
 */
beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password')->nullable();
        $t->string('remember_token')->nullable();
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

    foreach (glob(dirname(__DIR__, 3) . '/FileManager/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.php') as $file) {
        (require $file)->up();
    }

    DB::table('users')->insert(collect([1 => 'Owner', 2 => 'Nimal', 3 => 'Sunil', 4 => 'Kamala', 5 => 'Chen', 6 => 'Priya', 8 => 'Dinesh', 9 => 'Other owner'])
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
        ['business_id' => 7, 'user_id' => 6, 'role' => 'crm_staff',           'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 8, 'role' => 'crm_department_head', 'permissions' => $crmKeys],
    ]);
    DB::table('hr_departments')->insert([
        ['id' => 10, 'business_id' => 7, 'name' => 'Sales',     'head_employee_id' => 100],
        ['id' => 11, 'business_id' => 7, 'name' => 'Marketing', 'head_employee_id' => null],
        ['id' => 12, 'business_id' => 7, 'name' => 'Support',   'head_employee_id' => 108],
        ['id' => 20, 'business_id' => 8, 'name' => 'Sales',     'head_employee_id' => null],
    ]);
    DB::table('hr_employees')->insert([
        ['id' => 100, 'business_id' => 7, 'user_id' => 2, 'department_id' => 10, 'full_name' => 'Nimal Perera'],
        ['id' => 101, 'business_id' => 7, 'user_id' => 3, 'department_id' => 10, 'full_name' => 'Sunil Silva'],
        ['id' => 106, 'business_id' => 7, 'user_id' => 6, 'department_id' => 10, 'full_name' => 'Priya Jay'],
        ['id' => 103, 'business_id' => 7, 'user_id' => 4, 'department_id' => 11, 'full_name' => 'Kamala Fernando'],
        ['id' => 108, 'business_id' => 7, 'user_id' => 8, 'department_id' => 12, 'full_name' => 'Dinesh Kumar'],
    ]);

    $this->business = Business::find(7);
    $org = app(CrmOrgService::class);

    $this->sales     = $org->linkPipeline($this->business, Department::find(10), null);
    $this->marketing = $org->linkPipeline($this->business, Department::find(11), null);
    $this->support   = $org->linkPipeline($this->business, Department::find(12), null);
    $org->saveSettings($this->business, Department::find(11), ['is_marketing_intake' => true]);
    $this->otherSales = $org->linkPipeline(Business::find(8), Department::find(20), null);

    $this->leads       = app(LeadService::class);
    $this->assignments = app(LeadAssignmentService::class);
    $this->policy      = app(LeadPolicy::class);
    $this->access      = fn (int $userId) => app(CrmPermissionService::class)->accessFor($this->business, User::find($userId));

    $this->lead      = $this->leads->create($this->sales, ['name' => 'Kasun'], 1);
    $this->otherLead = $this->leads->create($this->otherSales, ['name' => 'Elsewhere'], 9);

    config(['crm.attachments.disk' => 'local']);
    Storage::fake('local');
    Storage::fake('public');
    CrmWorkspaceTestApi::$businessId = 7;
});

// ── Desktop API helpers ──────────────────────────────────────────────────

class CrmWorkspaceTestApi extends PosCrmLeadWorkspaceApiController
{
    public static int $businessId = 7;

    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(self::$businessId);
    }
}

class CrmWorkspaceTestCrmApi extends PosCrmApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

function crmWsRequest(int $userId, string $method = 'GET', array $body = [], array $files = []): Request
{
    $request = Request::create('/api/v1/pos/crm', $method, $body, [], $files);
    $request->setUserResolver(fn () => User::find($userId));

    return $request;
}

/** Calls the workspace API as a user — a fresh controller each time, as each request gets. */
function crmWs(string $method, int $userId, array $args = [], string $http = 'GET', array $body = [], array $files = [])
{
    return app(CrmWorkspaceTestApi::class)->{$method}(crmWsRequest($userId, $http, $body, $files), ...$args);
}

/** Status code of a controller call, including the 403/404/422 it throws. */
function crmWsStatus(callable $call): int
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

// ── Multiple assignees ───────────────────────────────────────────────────

test('the migration copies each lead\'s assigned_to in as its primary assignee', function () {
    DB::table('crm_leads')->where('id', $this->lead->id)->update(['assigned_to' => 3]);
    (require crmWorkspaceMigrations()[0])->down();
    (require crmWorkspaceMigrations()[0])->up();

    $row = LeadAssignee::where('lead_id', $this->lead->id)->sole();

    expect($row->user_id)->toBe(3)
        ->and($row->is_primary)->toBeTrue()
        ->and($row->business_id)->toBe(7)
        ->and(LeadAssignee::count())->toBe(1);
});

test('the head assigns several staff: rows, primary, assigned_to, history and status', function () {
    $lead = $this->assignments->sync($this->lead, [3, 6], 6, 2, 'Big roof job');

    $rows = LeadAssignee::where('lead_id', $lead->id)->orderByDesc('is_primary')->get();
    $log  = LeadAssignmentLog::where('lead_id', $lead->id)->sole();

    expect($rows->pluck('user_id')->all())->toBe([6, 3])
        ->and($rows->first()->is_primary)->toBeTrue()
        ->and($rows->first()->assigned_by)->toBe(2)
        ->and($lead->assigned_to)->toBe(6)
        ->and($lead->statusKey())->toBe(LeadStage::STATUS_ASSIGNED)
        ->and($log->action)->toBe(LeadAssignmentLog::ACTION_ASSIGNED)
        ->and($log->user_ids)->toBe([6, 3])
        ->and($log->from_user_ids)->toBe([])
        ->and($log->by_user_id)->toBe(2)
        ->and($log->note)->toBe('Big roof job');
});

test('adding, re-assigning, changing the primary and removing each leave a history row', function () {
    $lead = $this->assignments->add($this->lead, [3], 2);
    $lead = $this->assignments->add($lead, [6], 2);
    $lead = $this->assignments->setPrimary($lead, 6, 2);
    $lead = $this->assignments->sync($lead, [2], null, 2);
    $lead = $this->assignments->remove($lead, 2, 2);

    $actions = LeadAssignmentLog::where('lead_id', $lead->id)->orderBy('id')->pluck('action')->all();

    expect($actions)->toBe(['assigned', 'assigned', 'reassigned', 'reassigned', 'unassigned'])
        ->and($lead->assigned_to)->toBeNull()
        ->and(LeadAssignee::where('lead_id', $lead->id)->count())->toBe(0);

    $titles = collect($this->assignments->presentHistory($this->assignments->history($lead)))->pluck('title')->all();
    expect($titles)->toBe([
        'Took off Nimal',
        'Assigned Nimal; took off Priya, Sunil; Nimal is primary',
        'Made Priya the primary assignee',
        'Assigned Priya',
        'Assigned Sunil',
    ]);
});

test('assigned_to always follows the primary; removing the primary promotes the next one', function () {
    $lead = $this->assignments->sync($this->lead, [3, 6], null, 2);
    expect($lead->assigned_to)->toBe(3);

    $lead = $this->assignments->remove($lead, 3, 2);
    expect($lead->assigned_to)->toBe(6)
        ->and(LeadAssignee::where('lead_id', $lead->id)->sole()->is_primary)->toBeTrue();

    $lead = $this->assignments->setPrimaryAssignee($lead, 3, 2);   // the old single "Assigned to" field
    expect($lead->assigned_to)->toBe(3)
        ->and($this->assignments->assigneeIds($lead))->toBe([3, 6]);
});

test('only members of the lead\'s department can be assigned', function () {
    expect(fn () => $this->assignments->sync($this->lead, [3, 8], null, 2))
        ->toThrow(ValidationException::class, 'Dinesh is not a member of the lead\'s department.');

    expect(LeadAssignee::count())->toBe(0)
        ->and(LeadAssignmentLog::count())->toBe(0)
        ->and($this->lead->fresh()->assigned_to)->toBeNull();
});

test('only the department head or an admin may change the assignees', function (int $userId, int $status) {
    expect(crmWsStatus(fn () => crmWs('syncAssignees', $userId, [$this->lead->id], 'PUT', ['user_ids' => [3]])))->toBe($status);
})->with([
    'owner'                  => [1, 200],
    'the head'               => [2, 200],
    'staff'                  => [3, 403],
    'marketing'              => [4, 404],
    'the CEO'                => [5, 403],
    'another department head' => [8, 404],
]);

test('staff see who works the lead but get nobody to pick', function () {
    $this->assignments->sync($this->lead, [6], null, 2);

    $data = crmWs('assignees', 3, [$this->lead->id])->getData(true)['data'];

    expect(collect($data['assignees'])->pluck('name')->all())->toBe(['Priya'])
        ->and($data['can_assign'])->toBeFalse()
        ->and($data['assignable_users'])->toBe([])
        ->and($data['history'][0]['title'])->toBe('Assigned Priya');

    $head = crmWs('assignees', 2, [$this->lead->id])->getData(true)['data'];
    expect(collect($head['assignable_users'])->pluck('id')->sort()->values()->all())->toBe([2, 3, 6]);
});

test('every assignee — not only the primary — may edit the lead, until taken off it', function () {
    $this->assignments->sync($this->lead, [3, 6], 3, 2);

    expect($this->policy->canEdit(($this->access)(6), $this->lead->fresh()))->toBeTrue()
        ->and($this->policy->canEdit(($this->access)(3), $this->lead->fresh()))->toBeTrue();

    $this->assignments->remove($this->lead, 6, 2);

    expect($this->policy->canEdit(($this->access)(6), $this->lead->fresh()))->toBeFalse()
        ->and(array_keys($this->policy->editableLeadIds(($this->access)(3), Lead::where('id', $this->lead->id)->get())))->toBe([$this->lead->id]);
});

test('the pipeline shows every assignee on each card', function () {
    $this->assignments->sync($this->lead, [3, 6], 6, 2);

    $columns = app(CrmWorkspaceTestCrmApi::class)->pipeline(crmWsRequest(3), $this->sales->id)->getData(true)['data']['columns'];
    $card    = collect($columns)->flatMap(fn ($c) => $c['leads'])->firstWhere('id', $this->lead->id);

    expect(collect($card['assignee_list'])->map(fn ($a) => [$a['name'], $a['is_primary']])->all())->toBe([['Priya', true], ['Sunil', false]])
        ->and($card['can']['contribute'])->toBeTrue();
});

test('the desktop edit form\'s single assignee sets the primary and keeps the others', function () {
    $this->assignments->sync($this->lead, [3], null, 2);

    app(CrmWorkspaceTestCrmApi::class)->updateLead(crmWsRequest(2, 'PUT', ['name' => 'Kasun', 'assigned_to' => 6]), $this->lead->id);

    expect($this->assignments->assigneeIds($this->lead))->toBe([6, 3])
        ->and($this->lead->fresh()->assigned_to)->toBe(6);
});

test('forwarding a lead takes its assignees off', function () {
    $intakeLead = $this->leads->create($this->marketing, ['name' => 'From the web'], 1);
    $this->assignments->sync($intakeLead, [4], null, 1);

    $lead = app(MarketingInboxService::class)->forward($intakeLead, 10, null, null, 4);

    expect($lead->fresh()->assigned_to)->toBeNull()
        ->and(LeadAssignee::where('lead_id', $lead->id)->count())->toBe(0)
        ->and(LeadAssignmentLog::where('lead_id', $lead->id)->latest('id')->first()->action)->toBe(LeadAssignmentLog::ACTION_FORWARDED);
});

// ── Files ────────────────────────────────────────────────────────────────

test('an upload is stored privately through the FileManager in CRM/Leads/<id>', function () {
    $res = crmWs('storeAttachments', 3, [$this->lead->id], 'POST', [], ['files' => [UploadedFile::fake()->create('roof.png', 20, 'image/png')]]);

    $attachment = LeadAttachment::sole();
    $file       = FileManagerFile::find($attachment->file_manager_file_id);
    $folder     = FileManagerFolder::find($file->folder_id);

    expect($res->getStatusCode())->toBe(201)
        ->and($attachment->is_image)->toBeTrue()
        ->and($attachment->uploaded_by)->toBe(3)
        ->and($file->disk)->toBe('local')
        ->and($file->managed_by)->toBe('crm')
        ->and($folder->name)->toBe((string) $this->lead->id)
        ->and($folder->parent->name)->toBe('Leads')
        ->and($folder->parent->parent->name)->toBe('CRM')
        ->and(Storage::disk('local')->exists($file->stored_path))->toBeTrue()
        ->and(Storage::disk('public')->exists($file->stored_path))->toBeFalse()
        ->and($file->publicUrl())->not->toContain('/storage/');
});

test('uploads are limited by type and size', function (UploadedFile $file) {
    expect(crmWsStatus(fn () => crmWs('storeAttachments', 3, [$this->lead->id], 'POST', [], ['files' => [$file]])))->toBe(422)
        ->and(LeadAttachment::count())->toBe(0);
})->with([
    'svg'      => fn () => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
    'exe'      => fn () => UploadedFile::fake()->create('setup.exe', 5, 'application/x-msdownload'),
    'too big'  => fn () => UploadedFile::fake()->create('huge.pdf', LeadAttachmentService::maxKb() + 1, 'application/pdf'),
]);

test('downloads are authorised per lead: same department yes, other departments and businesses no', function () {
    $attachment = app(LeadAttachmentService::class)->upload($this->lead, UploadedFile::fake()->create('quote.pdf', 10, 'application/pdf'), 3);
    $download   = fn (int $userId) => crmWsStatus(fn () => crmWs('downloadAttachment', $userId, [$this->lead->id, $attachment->id]));

    expect($download(3))->toBe(200)    // uploader
        ->and($download(6))->toBe(200)  // same department
        ->and($download(2))->toBe(200)  // the head
        ->and($download(5))->toBe(200)  // the CEO views everything
        ->and($download(1))->toBe(200)  // owner
        ->and($download(8))->toBe(404)  // another department's head
        ->and($download(4))->toBe(404); // marketing, not their lead

    // The lead of another business is not found, nor is this attachment under another lead
    expect(crmWsStatus(fn () => crmWs('downloadAttachment', 1, [$this->otherLead->id, $attachment->id])))->toBe(404)
        ->and(crmWsStatus(fn () => crmWs('attachments', 1, [$this->otherLead->id])))->toBe(404);

    CrmWorkspaceTestApi::$businessId = 8;
    expect(crmWsStatus(fn () => crmWs('downloadAttachment', 9, [$this->lead->id, $attachment->id])))->toBe(404);
});

test('the file manager will not hand out a CRM-managed file', function () {
    $attachment = app(LeadAttachmentService::class)->upload($this->lead, UploadedFile::fake()->create('quote.pdf', 10, 'application/pdf'), 3);
    $controller = new class(app(\Modules\FileManager\Services\FileManagerService::class)) extends FileManagerFileController {
        protected function requireBusiness(Request $request): Business { return Business::find(7); }
    };

    expect(crmWsStatus(fn () => $controller->download(crmWsRequest(1), $attachment->file)))->toBe(403)
        ->and(crmWsStatus(fn () => $controller->destroy(crmWsRequest(1), $attachment->file)))->toBe(403)
        ->and(FileManagerFile::count())->toBe(1);
});

test('a file is deleted by its uploader, the head or an admin — and leaves the disk', function () {
    $upload = fn () => app(LeadAttachmentService::class)->upload($this->lead, UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'), 3);
    $delete = fn (int $userId, LeadAttachment $a) => crmWsStatus(fn () => crmWs('destroyAttachment', $userId, [$this->lead->id, $a->id], 'DELETE'));

    $a    = $upload();
    $path = $a->file->stored_path;
    expect($delete(6, $a))->toBe(403)   // other staff
        ->and($delete(5, $a))->toBe(403)  // CEO
        ->and($delete(3, $a))->toBe(200)  // uploader
        ->and(Storage::disk('local')->exists($path))->toBeFalse()
        ->and(FileManagerFile::count())->toBe(0);

    expect($delete(2, $upload()))->toBe(200)
        ->and($delete(1, $upload()))->toBe(200)
        ->and(LeadAttachment::count())->toBe(0);
});

test('deleting a lead removes its stored files', function () {
    $a = app(LeadAttachmentService::class)->upload($this->lead, UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'), 3);
    $path = $a->file->stored_path;

    $this->leads->delete($this->lead, 1);

    expect(Storage::disk('local')->exists($path))->toBeFalse()
        ->and(FileManagerFile::count())->toBe(0);
});

// ── Internal messages ────────────────────────────────────────────────────

test('department staff may post on a lead they only view; view-only and outside roles may not', function (int $userId, int $status) {
    expect(crmWsStatus(fn () => crmWs('storeMessage', $userId, [$this->lead->id], 'POST', ['body' => 'Called them'])))->toBe($status);
})->with([
    'staff, not assigned' => [6, 201],
    'the head'            => [2, 201],
    'owner'               => [1, 201],
    'the CEO'             => [5, 403],
    'marketing'           => [4, 404],
]);

test('everyone who can view the lead reads its messages', function () {
    app(LeadMessageService::class)->post($this->lead, 2, 'Please call before Friday');

    $read = crmWs('messages', 5, [$this->lead->id])->getData(true);

    expect($read['data'][0]['body'])->toBe('Please call before Friday')
        ->and($read['meta']['can_post'])->toBeFalse()
        ->and(crmWsStatus(fn () => crmWs('messages', 8, [$this->lead->id])))->toBe(404);
});

test('replies hang off the top-level message and mentions keep only business users', function () {
    $messages = app(LeadMessageService::class);
    $root  = $messages->post($this->lead, 2, 'Who can visit?', null, [3, 6, 9, 999]);
    $reply = $messages->post($this->lead, 3, 'I can', $root->id);
    $deep  = $messages->post($this->lead, 6, 'Me too', $reply->id);

    expect($root->mentions)->toBe([3, 6])
        ->and($reply->parent_id)->toBe($root->id)
        ->and($deep->parent_id)->toBe($root->id);
});

test('only the author edits a message; author, head or admin delete it', function () {
    $message = app(LeadMessageService::class)->post($this->lead, 3, 'Draft');

    expect(crmWsStatus(fn () => crmWs('updateMessage', 6, [$this->lead->id, $message->id], 'PUT', ['body' => 'Hijack'])))->toBe(403)
        ->and(crmWsStatus(fn () => crmWs('updateMessage', 2, [$this->lead->id, $message->id], 'PUT', ['body' => 'Hijack'])))->toBe(403)
        ->and(crmWsStatus(fn () => crmWs('updateMessage', 3, [$this->lead->id, $message->id], 'PUT', ['body' => 'Final'])))->toBe(200)
        ->and($message->fresh()->body)->toBe('Final')
        ->and($message->fresh()->edited_at)->not->toBeNull()
        ->and(crmWsStatus(fn () => crmWs('destroyMessage', 6, [$this->lead->id, $message->id], 'DELETE')))->toBe(403)
        ->and(crmWsStatus(fn () => crmWs('destroyMessage', 2, [$this->lead->id, $message->id], 'DELETE')))->toBe(200)
        ->and(LeadMessage::count())->toBe(0);
});

test('files and images can be shared inside a message — text optional', function () {
    $files = app(LeadAttachmentService::class);
    $photo = $files->upload($this->lead, UploadedFile::fake()->create('roof.png', 5, 'image/png'), 3);
    $quote = $files->upload($this->lead, UploadedFile::fake()->create('quote.pdf', 5, 'application/pdf'), 3);

    $res = crmWs('storeMessage', 3, [$this->lead->id], 'POST', ['attachment_ids' => [$photo->id, $quote->id]]);
    $msg = $res->getData(true)['data'];

    expect($res->getStatusCode())->toBe(201)
        ->and($msg['body'])->toBe('')
        ->and(collect($msg['attachments'])->map(fn ($a) => [$a['name'], $a['is_image']])->all())->toBe([['roof.png', true], ['quote.pdf', false]])
        ->and($photo->fresh()->message_id)->toBe($msg['id'])
        // Still lead files, still behind the same download check
        ->and(crmWsStatus(fn () => crmWs('downloadAttachment', 6, [$this->lead->id, $photo->id])))->toBe(200)
        ->and(crmWsStatus(fn () => crmWs('downloadAttachment', 8, [$this->lead->id, $photo->id])))->toBe(404);

    // An empty message with no files is refused
    expect(crmWsStatus(fn () => crmWs('storeMessage', 3, [$this->lead->id], 'POST', ['body' => ''])))->toBe(422);
});

test('only your own, not yet shared files of this lead can go in a message', function () {
    $files   = app(LeadAttachmentService::class);
    $theirs  = $files->upload($this->lead, UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'), 6);
    $other   = $this->leads->create($this->sales, ['name' => 'Another'], 1);
    $elsewhere = $files->upload($other, UploadedFile::fake()->create('b.pdf', 5, 'application/pdf'), 3);
    $mine    = $files->upload($this->lead, UploadedFile::fake()->create('c.pdf', 5, 'application/pdf'), 3);
    app(LeadMessageService::class)->post($this->lead, 3, 'First', null, [], [$mine->id]);

    foreach ([$theirs, $elsewhere, $mine] as $file) {
        expect(crmWsStatus(fn () => crmWs('storeMessage', 3, [$this->lead->id], 'POST', ['body' => 'See', 'attachment_ids' => [$file->id]])))->toBe(422);
    }
    expect(LeadMessage::count())->toBe(1);
});

test('deleting a message keeps its files on the lead', function () {
    $messages = app(LeadMessageService::class);
    $file  = app(LeadAttachmentService::class)->upload($this->lead, UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'), 3);
    $root  = $messages->post($this->lead, 2, 'Question');
    $reply = $messages->post($this->lead, 3, 'Here it is', $root->id, [], [$file->id]);

    $messages->delete($root);

    expect(LeadMessage::count())->toBe(0)
        ->and($file->fresh()->message_id)->toBeNull()
        ->and(LeadAttachment::count())->toBe(1);
});

test('the web form posts a message with uploaded files', function () {
    $request = crmWsRequest(3, 'POST', ['body' => 'Photos from the site'], ['files' => [UploadedFile::fake()->create('site.jpg', 5, 'image/jpeg')]]);
    $this->actingAs(User::find(3));

    app(CrmWorkspaceTestWorkspaceController::class)->storeMessage($request, $this->lead);

    $message = LeadMessage::sole();
    expect($message->body)->toBe('Photos from the site')
        ->and($message->attachments()->sole()->is_image)->toBeTrue();
});

class CrmWorkspaceTestWorkspaceController extends \Modules\CRM\Http\Controllers\LeadWorkspaceController
{
    protected function requireBusiness(Request $request): Business { return Business::find(7); }
}

// ── Timeline ─────────────────────────────────────────────────────────────

test('the timeline merges every source newest first and filters by type', function () {
    $this->travel(1)->minutes();
    $this->assignments->sync($this->lead, [3], null, 2);
    $this->travel(1)->minutes();
    app(LeadMessageService::class)->post($this->lead, 3, 'On it');
    $this->travel(1)->minutes();
    app(LeadAttachmentService::class)->upload($this->lead, UploadedFile::fake()->create('site.pdf', 5, 'application/pdf'), 3);

    $all = crmWs('timeline', 3, [$this->lead->id])->getData(true)['data'];

    expect(array_slice(array_column($all, 'type'), 0, 3))->toBe(['file', 'message', 'stage'])
        ->and(collect($all)->pluck('type')->unique()->sort()->values()->all())->toBe(['assignment', 'file', 'message', 'stage'])
        ->and(collect($all)->firstWhere('type', 'assignment')['title'])->toBe('Assigned Sunil')
        ->and(collect($all)->firstWhere('type', 'message')['user']['name'])->toBe('Sunil');

    $only = crmWs('timeline', 3, [$this->lead->id], 'GET', ['types' => 'message'])->getData(true)['data'];
    expect(array_column($only, 'type'))->toBe(['message']);
});

test('other modules can add a timeline source', function () {
    app(TimelineService::class)->extend(new class implements TimelineSource {
        public function key(): string { return 'quotation'; }
        public function label(): string { return 'Quotations'; }
        public function entries(Lead $lead): iterable
        {
            yield new TimelineEntry('quotation', 1, now()->addDay(), 'Quotation Q-1 sent');
        }
    });

    $timeline = app(TimelineService::class)->forLead($this->lead);

    expect($timeline[0]['title'])->toBe('Quotation Q-1 sent')
        ->and(collect(app(TimelineService::class)->types())->pluck('key'))->toContain('quotation');
});

// ── Audit log ────────────────────────────────────────────────────────────

test('updating a lead writes an audit row with old and new values, user and IP', function () {
    $this->actingAs(User::find(2));

    $this->leads->update($this->lead, ['name' => 'Kasun Perera', 'company' => 'Solar Ltd'], 2);

    $log = CrmAuditLog::where('subject_type', Lead::class)->where('subject_id', $this->lead->id)->where('event', 'updated')->latest('id')->first();

    expect($log->business_id)->toBe(7)
        ->and($log->user_id)->toBe(2)
        ->and($log->ip)->toBe('127.0.0.1')
        ->and($log->old)->toMatchArray(['name' => 'Kasun', 'company' => null])
        ->and($log->new)->toMatchArray(['name' => 'Kasun Perera', 'company' => 'Solar Ltd'])
        ->and($log->new)->not->toHaveKey('updated_at');
});

test('adding and removing assignees is audited too', function () {
    $this->assignments->sync($this->lead, [3], null, 2);
    $this->assignments->sync($this->lead, [6], null, 2);

    $rows = CrmAuditLog::where('subject_type', LeadAssignee::class)->orderBy('id')->get();

    expect($rows->pluck('event')->all())->toBe(['created', 'deleted', 'created'])
        ->and($rows[0]->new['user_id'])->toBe(3)
        ->and($rows[1]->old['user_id'])->toBe(3)
        ->and(CrmAuditLog::where('subject_type', Lead::class)->where('event', 'updated')->get()
            ->contains(fn ($l) => ($l->new['assigned_to'] ?? null) === 6))->toBeTrue();
});

test('the audit log is admin-only, filterable and scoped to the business', function () {
    $this->leads->update($this->lead, ['name' => 'Renamed'], 1);

    expect(crmWsStatus(fn () => crmWs('auditLogs', 2)))->toBe(403)
        ->and(crmWsStatus(fn () => crmWs('auditLogs', 5)))->toBe(403);

    $res = crmWs('auditLogs', 1, [], 'GET', ['subject' => 'lead', 'event' => 'updated'])->getData(true);

    expect($res['data'])->not->toBeEmpty()
        ->and(collect($res['data'])->pluck('subject')->unique()->all())->toBe(['lead'])
        ->and(collect($res['data'])->pluck('event')->unique()->all())->toBe(['updated'])
        ->and(collect($res['data'])->pluck('subject_id'))->not->toContain($this->otherLead->id);
});

// ── Web pages ────────────────────────────────────────────────────────────

class CrmWorkspaceTestLeadController extends \Modules\CRM\Http\Controllers\LeadController
{
    protected function requireBusiness(Request $request): Business { return Business::find(7); }
}

class CrmWorkspaceTestAuditController extends \Modules\CRM\Http\Controllers\CrmAuditLogController
{
    protected function requireBusiness(Request $request): Business { return Business::find(7); }
}

/**
 * The app theme needs tables this schema does not have — render the CRM pages inside a bare
 * layout. Every view still runs the Settings module's composer, which reads the settings table.
 */
function crmWsBareTheme(): void
{
    (require dirname(__DIR__, 3) . '/Settings/database/migrations/2026_04_30_181900_create_settings_table.php')->up();

    $dir = sys_get_temp_dir() . '/crm-ws-theme';
    @mkdir($dir . '/layouts', 0777, true);
    file_put_contents($dir . '/layouts/app.blade.php', "@yield('content')");
    \Illuminate\Support\Facades\View::replaceNamespace('theme', [$dir]);
    // Shared by the web middleware on a real request
    \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag());
}

test('the web lead page shows the five tabs, the assignees and the workspace content', function () {
    crmWsBareTheme();
    $this->actingAs(User::find(2));
    $this->assignments->sync($this->lead, [3, 6], 6, 2);
    app(LeadMessageService::class)->post($this->lead, 3, 'Site visit on Monday', null, [2]);
    app(LeadAttachmentService::class)->upload($this->lead, UploadedFile::fake()->create('roof.png', 5, 'image/png'), 3);

    $html = app(CrmWorkspaceTestLeadController::class)->show(crmWsRequest(2), $this->lead->fresh())->render();

    expect($html)->toContain('data-ld-tab="timeline"', 'data-ld-tab="messages"', 'data-ld-tab="files"', 'data-ld-tab="tasks"')
        ->toContain('Site visit on Monday', '@Nimal', 'roof.png', 'Save assignees', 'Assigned Priya, Sunil')
        ->toContain(route('crm.leads.attachments.download', [$this->lead, LeadAttachment::sole()->id, 'inline' => 1]));

    // Staff see who works it but get no assign button
    $this->actingAs(User::find(3));
    $staff = app(CrmWorkspaceTestLeadController::class)->show(crmWsRequest(3), $this->lead->fresh())->render();
    expect($staff)->toContain('Priya')->not->toContain('Save assignees');
});

test('the web audit page is for admins only', function () {
    crmWsBareTheme();
    $this->leads->update($this->lead, ['name' => 'Renamed'], 1);

    $this->actingAs(User::find(1));
    expect(app(CrmWorkspaceTestAuditController::class)->index(crmWsRequest(1))->render())->toContain('Lead #' . $this->lead->id, 'Renamed');

    $this->actingAs(User::find(2));
    expect(crmWsStatus(fn () => app(CrmWorkspaceTestAuditController::class)->index(crmWsRequest(2))))->toBe(403);
});

// ── Migrations ───────────────────────────────────────────────────────────

test('the workspace migrations roll back and run again on SQLite', function () {
    foreach (array_reverse(crmWorkspaceMigrations()) as $file) {
        (require $file)->down();
    }

    expect(Schema::hasTable('crm_lead_assignees'))->toBeFalse()
        ->and(Schema::hasTable('crm_lead_messages'))->toBeFalse()
        ->and(Schema::hasTable('crm_lead_attachments'))->toBeFalse()
        ->and(Schema::hasTable('crm_audit_logs'))->toBeFalse()
        ->and(Schema::hasColumn('crm_lead_assignment_logs', 'from_user_ids'))->toBeFalse();

    foreach (crmWorkspaceMigrations() as $file) {
        (require $file)->up();
    }

    expect(Schema::hasTable('crm_lead_assignees'))->toBeTrue()
        ->and(Schema::hasColumns('file_manager_files', ['disk', 'managed_by']))->toBeTrue();
});
