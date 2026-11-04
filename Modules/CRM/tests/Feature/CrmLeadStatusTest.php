<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadSource;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\LeadStageLog;
use Modules\CRM\Models\LostReason;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\LeadSourceService;
use Modules\CRM\Services\LeadStageService;
use Modules\CRM\Services\LostReasonService;
use Modules\CRM\Services\ProjectService;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Http\Controllers\Api\PosCrmApiController;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** The migrations added for the standard statuses, in the order they run. */
function crmStatusMigrations(): array
{
    $dir = dirname(__DIR__, 2) . '/database/migrations/';

    return [
        $dir . '2026_11_01_100000_add_system_key_to_crm_lead_stages_table.php',
        $dir . '2026_11_01_100100_create_crm_lead_sources_table.php',
        $dir . '2026_11_01_100200_create_crm_lost_reasons_table.php',
        $dir . '2026_11_01_100300_add_status_fields_to_crm_leads_table.php',
        $dir . '2026_11_01_100400_create_crm_lead_products_table.php',
        // Indexes columns added above — rolled back first, re-added last
        $dir . '2026_11_07_100200_add_report_indexes_to_crm_leads_table.php',
    ];
}

/*
 * Business 7 "ABC Solar" (owner user 1) has a Sales department (10) with a "Solar" division;
 * business 8 (owner user 9) is the other tenant. Every CRM table comes from the real
 * migrations, so they are proven to run on SQLite.
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
    Schema::create('settings', function (Blueprint $t) {
        $t->id();
        $t->string('scope_type');
        $t->unsignedBigInteger('scope_id');
        $t->string('key');
        $t->text('value')->nullable();
        $t->string('value_type')->default('string');
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
    Schema::create('product_stock_layers', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('branch_id')->nullable();
        $t->unsignedBigInteger('product_id');
        $t->decimal('quantity_remaining', 14, 3);
        $t->decimal('selling_unit_price', 14, 2)->nullable();
        $t->date('received_at')->nullable();
        $t->timestamps();
    });

    foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.php') as $file) {
        (require $file)->up();
    }

    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Owner', 'email' => 'owner@example.com'],
        ['id' => 9, 'name' => 'Other owner', 'email' => 'other@example.com'],
    ]);
    DB::table('businesses')->insert([
        ['id' => 7, 'user_id' => 1, 'name' => 'ABC Solar'],
        ['id' => 8, 'user_id' => 9, 'name' => 'Other Co'],
    ]);
    DB::table('hr_departments')->insert([
        ['id' => 10, 'business_id' => 7, 'name' => 'Sales'],
        ['id' => 20, 'business_id' => 8, 'name' => 'Sales'],
    ]);
    DB::table('crm_divisions')->insert([
        ['id' => 1, 'business_id' => 7, 'department_id' => 10, 'name' => 'Solar', 'is_active' => true, 'sort_order' => 1],
        ['id' => 2, 'business_id' => 8, 'department_id' => 20, 'name' => 'Other', 'is_active' => true, 'sort_order' => 1],
    ]);
    DB::table('products')->insert([
        ['id' => 1, 'business_id' => 7, 'name' => 'Solar panel 450W'],
        ['id' => 2, 'business_id' => 7, 'name' => 'Inverter 5kW'],
        ['id' => 3, 'business_id' => 8, 'name' => 'Other business product'],
    ]);

    $this->business = Business::find(7);
    $this->other    = Business::find(8);
    $this->leads    = app(LeadService::class);
    $this->stages   = app(LeadStageService::class);
    $this->project  = app(ProjectService::class)->create($this->business, ['name' => 'Sales pipeline']);
    $this->stage    = fn (string $key, ?Project $project = null) => $this->stages->stageForStatus($project ?? $this->project, $key);
    $this->reason   = fn (?Business $business = null) => app(LostReasonService::class)->listForBusiness($business ?? $this->business)->first();
    $this->newLead  = fn (array $data = []) => $this->leads->create($this->project, $data + ['name' => 'Kasun'], 1);
});

test('the migrations roll back and run again on SQLite', function () {
    foreach (array_reverse(crmStatusMigrations()) as $file) {
        (require $file)->down();
    }

    expect(Schema::hasColumn('crm_lead_stages', 'system_key'))->toBeFalse()
        ->and(Schema::hasColumn('crm_leads', 'lost_reason_id'))->toBeFalse()
        ->and(Schema::hasColumn('crm_leads', 'created_by'))->toBeTrue()
        ->and(Schema::hasTable('crm_lead_sources'))->toBeFalse()
        ->and(Schema::hasTable('crm_lost_reasons'))->toBeFalse()
        ->and(Schema::hasTable('crm_lead_products'))->toBeFalse();

    foreach (crmStatusMigrations() as $file) {
        (require $file)->up();
    }

    expect(Schema::hasColumns('crm_leads', [
        'department_id', 'division_id', 'source_id', 'external_id', 'score', 'lost_reason_id', 'product_interest_note',
        'verified_at', 'verified_by', 'first_response_at', 'closed_at', 'on_hold_from_stage_id',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('crm_lead_stages', ['system_key', 'requires_reason']))->toBeTrue();
});

// ── Standard stages ──────────────────────────────────────────────────────

test('a new pipeline gets the 11 standard stages in order', function () {
    $stages = $this->stages->listForProject($this->project);

    expect($stages->pluck('system_key')->all())->toBe(array_keys(LeadStage::STATUSES))
        ->and($stages->pluck('sort_order')->all())->toBe(range(1, 11))
        ->and($stages->firstWhere('system_key', 'won')->is_won)->toBeTrue()
        ->and($stages->firstWhere('system_key', 'lost')->is_lost)->toBeTrue()
        ->and($stages->firstWhere('system_key', 'lost')->requires_reason)->toBeTrue()
        ->and($stages->where('is_won', true))->toHaveCount(1)
        ->and($stages->filter->isReserved()->pluck('system_key')->values()->all())->toBe(['pending_approval', 'quotation_sent']);
});

test('linking a legacy pipeline to a department maps its stages by name and adds the missing ones', function () {
    $legacy = Project::create(['business_id' => 7, 'name' => 'Legacy', 'status' => 'active']);
    foreach (['New', 'Contacted', 'Qualified', 'Negotiation', 'Won', 'Lost'] as $i => $name) {
        LeadStage::create(['project_id' => $legacy->id, 'name' => $name, 'sort_order' => $i + 1,
            'is_won' => $name === 'Won', 'is_lost' => $name === 'Lost']);
    }
    $lead = Lead::create(['business_id' => 7, 'project_id' => $legacy->id, 'name' => 'Old lead',
        'stage_id' => LeadStage::where('project_id', $legacy->id)->where('name', 'Qualified')->value('id')]);

    app(CrmOrgService::class)->linkPipeline($this->business, Department::find(10), $legacy->id);

    $stages = LeadStage::where('project_id', $legacy->id)->orderBy('sort_order')->get();
    expect($stages->pluck('system_key', 'name')->all())->toBe([
        'New'                 => 'new',
        'Assigned'            => 'assigned',
        'Contacted'           => 'contacted',
        'Qualified'           => 'in_process',
        'Quotation Preparing' => 'quotation_preparing',
        'Pending Approval'    => 'pending_approval',
        'Quotation Sent'      => 'quotation_sent',
        'Negotiation'         => 'negotiation',
        'On Hold'             => 'on_hold',
        'Won'                 => 'won',
        'Lost'                => 'lost',
    ])
        ->and($lead->fresh()->department_id)->toBe(10);
});

test('re-linking an already-mapped pipeline does not bring back stages the user deleted', function () {
    $org     = app(CrmOrgService::class);
    $project = $org->linkPipeline($this->business, Department::find(10), null);
    $deleted = ['assigned', 'contacted', 'on_hold'];
    LeadStage::where('project_id', $project->id)->whereIn('system_key', $deleted)->get()
        ->each(fn (LeadStage $s) => $this->stages->delete($s));

    $org->unlinkPipeline($this->business, Department::find(10), $project->id);
    $org->linkPipeline($this->business, Department::find(10), $project->id);

    expect(LeadStage::where('project_id', $project->id)->whereIn('system_key', $deleted)->count())->toBe(0)
        ->and(LeadStage::where('project_id', $project->id)->count())->toBe(count(LeadStage::STATUSES) - count($deleted));
});

test('the "New" stage cannot be deleted while it is the only one', function () {
    $new = $this->stages->stageForStatus($this->project, LeadStage::STATUS_NEW);

    expect(fn () => $this->stages->delete($new))->toThrow(ValidationException::class);
});

test('the mapping helper maps won, lost, the first stage and the rest, and reports it', function () {
    $legacy = Project::create(['business_id' => 7, 'name' => 'Legacy', 'status' => 'active']);
    foreach (['Lead in', 'Demo', 'Proposal', 'Closed won', 'Closed lost'] as $i => $name) {
        LeadStage::create(['project_id' => $legacy->id, 'name' => $name, 'sort_order' => $i + 1,
            'is_won' => $name === 'Closed won', 'is_lost' => $name === 'Closed lost']);
    }

    $dry = $this->stages->mapExisting($legacy, pretend: true);
    expect(collect($dry)->pluck('system_key', 'name')->all())->toBe([
        'Lead in' => 'new', 'Demo' => 'in_process', 'Proposal' => 'in_process', 'Closed won' => 'won', 'Closed lost' => 'lost',
    ])
        ->and(LeadStage::where('project_id', $legacy->id)->whereNotNull('system_key')->count())->toBe(0);

    Artisan::call('crm:map-stage-statuses', ['--business' => 7]);
    expect(Artisan::output())->toContain('Dry run')->toContain('Closed lost')
        ->and(LeadStage::where('project_id', $legacy->id)->whereNotNull('system_key')->count())->toBe(0);

    Artisan::call('crm:map-stage-statuses', ['--business' => 7, '--apply' => true]);
    expect(LeadStage::where('project_id', $legacy->id)->orderBy('sort_order')->pluck('system_key', 'name')->all())->toBe([
        'Lead in' => 'new', 'Demo' => 'in_process', 'Proposal' => 'in_process', 'Closed won' => 'won', 'Closed lost' => 'lost',
    ])
        ->and(LeadStage::where('project_id', $legacy->id)->where('name', 'Closed lost')->value('requires_reason'))->toBeTruthy()
        // Already-mapped stages are left alone on a second run
        ->and($this->stages->mapExisting($legacy))->toBe([]);
});

test('stages saved with only the won / lost flags (older desktop builds) get the matching status', function () {
    $stage = $this->stages->create($this->project, ['name' => 'Closed', 'is_won' => true]);
    expect($stage->system_key)->toBe('won');

    $stage = $this->stages->update($stage, ['name' => 'Closed', 'is_won' => false, 'is_lost' => false]);
    expect($stage->system_key)->toBe('in_process')->and($stage->is_won)->toBeFalse();

    $stage = $this->stages->update($stage, ['name' => 'Closed', 'system_key' => 'lost', 'requires_reason' => false]);
    expect($stage->is_lost)->toBeTrue()->and($stage->requires_reason)->toBeTrue();
});

// ── Sources and lost reasons ─────────────────────────────────────────────

test('each business gets its own default lead sources and lost reasons', function () {
    $sources = app(LeadSourceService::class);
    $reasons = app(LostReasonService::class);

    expect($sources->listForBusiness($this->business)->pluck('key')->all())
        ->toBe(['manual', 'web_form', 'csv', 'google_form', 'facebook', 'whatsapp'])
        ->and($reasons->listForBusiness($this->business)->pluck('label')->all())->toBe(LostReason::DEFAULTS);

    $sources->listForBusiness($this->other);
    $reasons->listForBusiness($this->other);

    expect(LeadSource::where('business_id', 7)->count())->toBe(6)
        ->and(LeadSource::where('business_id', 8)->count())->toBe(6)
        ->and(LostReason::where('business_id', 8)->count())->toBe(count(LostReason::DEFAULTS));
});

test('source config is stored encrypted', function () {
    $source = app(LeadSourceService::class)->listForBusiness($this->business)->firstWhere('key', 'facebook');
    $source->update(['config' => ['page_token' => 'secret-token']]);

    expect(DB::table('crm_lead_sources')->where('id', $source->id)->value('config'))->not->toContain('secret-token')
        ->and($source->fresh()->config)->toBe(['page_token' => 'secret-token']);
});

// ── Lead fields ──────────────────────────────────────────────────────────

test('a new lead records its department, source, score, products and creator', function () {
    app(CrmOrgService::class)->linkPipeline($this->business, Department::find(10), $this->project->id);
    $this->project->refresh();

    $lead = ($this->newLead)([
        'source'                => 'public-form',
        'score'                 => 'hot',
        'division_id'           => 1,
        'product_interest_note' => 'Also asked about batteries',
        'products'              => [['product_id' => 1, 'quantity' => 12], 2],
    ]);

    expect($lead->department_id)->toBe(10)
        ->and($lead->division_id)->toBe(1)
        ->and($lead->source)->toBe('public-form')
        ->and($lead->leadSource->key)->toBe('web_form')
        ->and($lead->score)->toBe('hot')
        ->and($lead->created_by)->toBe(1)
        ->and($lead->stage->system_key)->toBe('new')
        ->and($lead->products->pluck('pivot.quantity', 'id')->map(fn ($q) => $q === null ? null : (float) $q)->all())->toBe([1 => 12.0, 2 => null]);

    $lead = $this->leads->update($lead, ['name' => 'Kasun', 'products' => []]);
    expect($lead->products)->toHaveCount(0)->and($lead->product_interest_note)->toBe('Also asked about batteries');
});

test('lead fields reject another business\'s products, a foreign division and an unknown score', function (array $data, string $field) {
    app(CrmOrgService::class)->linkPipeline($this->business, Department::find(10), $this->project->id);
    $this->project->refresh();

    try {
        ($this->newLead)($data);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
})->with([
    'other business product' => [['products' => [3]], 'products'],
    'other business division' => [['division_id' => 2], 'division_id'],
    'unknown score'           => [['score' => 'boiling'], 'score'],
]);

// ── Status rules ─────────────────────────────────────────────────────────

test('moving to a lost stage requires a lost reason of this business', function () {
    $lead = ($this->newLead)();
    $lost = ($this->stage)('lost');

    expect(fn () => $this->leads->moveStage($lead, $lost->id, 1))->toThrow(ValidationException::class)
        ->and(fn () => $this->leads->moveStage($lead, $lost->id, 1, ['lost_reason_id' => ($this->reason)($this->other)->id]))
        ->toThrow(ValidationException::class)
        ->and($lead->fresh()->stage_id)->toBe(($this->stage)('new')->id);

    $reason = ($this->reason)();
    $this->leads->moveStage($lead, $lost->id, 1, ['lost_reason_id' => $reason->id, 'lost_reason' => 'Bought elsewhere']);

    $lead->refresh();
    expect($lead->stage_id)->toBe($lost->id)
        ->and($lead->lost_reason_id)->toBe($reason->id)
        ->and($lead->lost_reason)->toBe('Bought elsewhere')
        ->and($lead->closed_at)->not->toBeNull()
        ->and(LeadStageLog::where('lead_id', $lead->id)->latest('id')->value('reason'))->toBe($reason->label . ': Bought elsewhere');
});

test('markLost and editing to a lost stage need a lost reason too', function () {
    $lead = ($this->newLead)();

    expect(fn () => $this->leads->markLost($lead, null))->toThrow(ValidationException::class)
        ->and(fn () => $this->leads->update($lead, ['name' => 'Kasun', 'stage_id' => ($this->stage)('lost')->id]))->toThrow(ValidationException::class);

    $this->leads->markLost($lead, ($this->reason)()->id, null, 1);
    expect($lead->fresh()->isLost())->toBeTrue();
});

test('won and lost set closed_at, reopening clears it and the lost reason', function () {
    $lead = ($this->newLead)();

    $this->leads->moveStage($lead, ($this->stage)('won')->id, 1);
    $wonAt = $lead->fresh()->closed_at;
    expect($wonAt)->not->toBeNull();

    $this->travel(2)->days();
    // won → lost keeps the original close time
    $this->leads->moveStage($lead->fresh(), ($this->stage)('lost')->id, 1, ['lost_reason_id' => ($this->reason)()->id]);
    expect($lead->fresh()->closed_at->equalTo($wonAt))->toBeTrue();

    $this->leads->reopen($lead->fresh(), 1);
    $lead->refresh();
    expect($lead->stage->system_key)->toBe('new')
        ->and($lead->closed_at)->toBeNull()
        ->and($lead->lost_reason_id)->toBeNull();
});

test('on hold remembers the previous stage and resume returns to it', function () {
    $lead = ($this->newLead)();
    $this->leads->moveStage($lead, ($this->stage)('negotiation')->id, 1);

    expect(fn () => $this->leads->resume($lead->fresh(), 1))->toThrow(ValidationException::class);

    $this->leads->putOnHold($lead->fresh(), 'Customer travelling', 1);
    $lead->refresh();
    expect($lead->stage->system_key)->toBe('on_hold')
        ->and($lead->on_hold_from_stage_id)->toBe(($this->stage)('negotiation')->id);

    $this->leads->resume($lead, 1);
    $lead->refresh();
    expect($lead->stage->system_key)->toBe('negotiation')
        ->and($lead->on_hold_from_stage_id)->toBeNull();
});

test('a lead dragged onto the on-hold stage also remembers where it came from', function () {
    $lead = ($this->newLead)();
    $this->leads->moveStage($lead, ($this->stage)('contacted')->id, 1);
    $this->leads->moveStage($lead->fresh(), ($this->stage)('on_hold')->id, 1);

    expect($lead->fresh()->on_hold_from_stage_id)->toBe(($this->stage)('contacted')->id);

    // Leaving on hold by hand forgets it
    $this->leads->moveStage($lead->fresh(), ($this->stage)('in_process')->id, 1);
    expect($lead->fresh()->on_hold_from_stage_id)->toBeNull();
});

test('pending approval and quotation sent cannot be set by hand', function (string $key) {
    $lead  = ($this->newLead)();
    $stage = ($this->stage)($key);

    expect(fn () => $this->leads->moveStage($lead, $stage->id, 1))->toThrow(ValidationException::class)
        ->and(fn () => $this->leads->update($lead, ['name' => 'Kasun', 'stage_id' => $stage->id]))->toThrow(ValidationException::class)
        ->and(fn () => ($this->newLead)(['stage_id' => $stage->id]))->toThrow(ValidationException::class)
        ->and($lead->fresh()->stage->system_key)->toBe('new');

    // The quotation workflow (Prompt 4.2) sets them as the system
    $this->leads->moveToStatus($lead->fresh(), $key, 1, system: true);
    expect($lead->fresh()->stage->system_key)->toBe($key);
})->with(['pending_approval', 'quotation_sent']);

test('a stage that asks for a reason needs one', function () {
    $stage = ($this->stage)('contacted');
    $stage->update(['requires_reason' => true]);
    $lead = ($this->newLead)();

    expect(fn () => $this->leads->moveStage($lead, $stage->id, 1))->toThrow(ValidationException::class);

    $this->leads->moveStage($lead, $stage->id, 1, ['reason' => 'Called, will visit Monday']);
    expect(LeadStageLog::where('lead_id', $lead->id)->latest('id')->value('reason'))->toBe('Called, will visit Monday');
});

test('every status change writes a stage log', function () {
    $lead = ($this->newLead)();
    $this->leads->moveStage($lead, ($this->stage)('contacted')->id, 1);
    $this->leads->putOnHold($lead->fresh(), null, 1);
    $this->leads->resume($lead->fresh(), 1);
    $this->leads->update($lead->fresh(), ['name' => 'Kasun', 'stage_id' => ($this->stage)('won')->id], 1);
    $this->leads->reopen($lead->fresh(), 1);
    $this->leads->markLost($lead->fresh(), ($this->reason)()->id, null, 1);
    $this->leads->moveToStatus($lead->fresh(), 'quotation_sent', 1, system: true);

    $keys = LeadStageLog::where('lead_id', $lead->id)->orderBy('id')->with('toStage')->get()->map(fn ($l) => $l->toStage->system_key)->all();

    expect($keys)->toBe(['new', 'contacted', 'on_hold', 'contacted', 'won', 'new', 'lost', 'quotation_sent'])
        ->and(LeadStageLog::where('lead_id', $lead->id)->whereNotNull('changed_by')->count())->toBe(8);
});

// ── Desktop API ──────────────────────────────────────────────────────────

class CrmLeadStatusTestApi extends PosCrmApiController
{
    public static int $businessId = 7;

    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(self::$businessId);
    }
}

function crmStatusRequest(int $userId, string $method = 'GET', array $body = []): Request
{
    $request = Request::create('/api/v1/pos/crm', $method, $body);
    $request->setUserResolver(fn () => User::find($userId));

    return $request;
}

/** Status code of a controller call, including the 403/404/422 it throws. */
function crmStatusCode(callable $call): int
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

test('the pipeline payload carries the statuses, lost reasons and the new lead fields', function () {
    $lead = ($this->newLead)(['score' => 'warm', 'products' => [['product_id' => 1, 'quantity' => 3]]]);

    $data = app(CrmLeadStatusTestApi::class)->pipeline(crmStatusRequest(1), $this->project->id)->getData(true)['data'];
    $card = collect($data['columns'])->firstWhere('system_key', 'new')['leads'][0];

    expect(collect($data['statuses'])->pluck('key')->all())->toBe(array_keys(LeadStage::STATUSES))
        ->and(collect($data['columns'])->firstWhere('system_key', 'quotation_sent')['is_reserved'])->toBeTrue()
        ->and(collect($data['columns'])->firstWhere('system_key', 'lost')['requires_reason'])->toBeTrue()
        ->and($data['lost_reasons'])->toHaveCount(count(LostReason::DEFAULTS))
        ->and($card['id'])->toBe($lead->id)
        ->and($card['score'])->toBe('warm')
        ->and($card['product_items'])->toEqual([['id' => 1, 'name' => 'Solar panel 450W', 'quantity' => 3, 'sku' => null, 'unit_price' => null]]);
});

test('a lead product is priced at its next in-stock FIFO layer, as the POS sells it', function () {
    ($this->newLead)(['products' => [['product_id' => 1, 'quantity' => 2]]]);
    DB::table('product_stock_layers')->insert([
        ['business_id' => 7, 'product_id' => 1, 'quantity_remaining' => 0, 'selling_unit_price' => 9000, 'received_at' => '2026-01-01'], // sold out
        ['business_id' => 7, 'product_id' => 1, 'quantity_remaining' => 4, 'selling_unit_price' => 11000, 'received_at' => '2026-02-01'],
        ['business_id' => 7, 'product_id' => 1, 'quantity_remaining' => 9, 'selling_unit_price' => 12500, 'received_at' => '2026-03-01'],
    ]);

    $data = app(CrmLeadStatusTestApi::class)->pipeline(crmStatusRequest(1), $this->project->id)->getData(true)['data'];
    $card = collect($data['columns'])->firstWhere('system_key', 'new')['leads'][0];

    expect($card['product_items'][0]['unit_price'])->toEqual(11000);
});

test('the API rejects a lost move without a reason and accepts one with it', function () {
    $lead = ($this->newLead)();
    $api  = app(CrmLeadStatusTestApi::class);
    $lost = ($this->stage)('lost')->id;

    expect(crmStatusCode(fn () => $api->moveLead(crmStatusRequest(1, 'POST', ['stage_id' => $lost]), $lead->id)))->toBe(422)
        ->and(crmStatusCode(fn () => $api->moveLead(crmStatusRequest(1, 'POST', ['stage_id' => ($this->stage)('quotation_sent')->id]), $lead->id)))->toBe(422)
        ->and(crmStatusCode(fn () => $api->moveLead(crmStatusRequest(1, 'POST', ['stage_id' => $lost, 'lost_reason_id' => ($this->reason)()->id]), $lead->id)))->toBe(200)
        ->and($lead->fresh()->closed_at)->not->toBeNull();
});

test('the API puts a lead on hold and resumes it', function () {
    $lead = ($this->newLead)();
    $api  = app(CrmLeadStatusTestApi::class);
    $this->leads->moveStage($lead, ($this->stage)('contacted')->id, 1);

    expect(crmStatusCode(fn () => $api->holdLead(crmStatusRequest(1, 'POST', ['reason' => 'Busy']), $lead->id)))->toBe(200)
        ->and($lead->fresh()->stage->system_key)->toBe('on_hold')
        ->and(crmStatusCode(fn () => $api->resumeLead(crmStatusRequest(1, 'POST'), $lead->id)))->toBe(200)
        ->and($lead->fresh()->stage->system_key)->toBe('contacted');
});

test('lost reasons and lead sources lists are scoped to the business', function () {
    app(LostReasonService::class)->listForBusiness($this->other)->first()->update(['label' => 'Only in business 8']);
    $api = app(CrmLeadStatusTestApi::class);

    $reasons = $api->lostReasons(crmStatusRequest(1))->getData(true)['data'];
    $sources = $api->leadSources(crmStatusRequest(1))->getData(true)['data'];

    expect(collect($reasons)->pluck('label'))->not->toContain('Only in business 8')
        ->and(collect($reasons)->pluck('id')->diff(LostReason::where('business_id', 7)->pluck('id')))->toBeEmpty()
        ->and(collect($sources)->pluck('id')->diff(LeadSource::where('business_id', 7)->pluck('id')))->toBeEmpty()
        ->and(collect($sources)->firstWhere('key', 'web_form')['lead_source'])->toBe('public-form');
});

test('a lead from another business is not found through the API', function () {
    $otherProject = app(ProjectService::class)->create($this->other, ['name' => 'Other pipeline']);
    $otherLead    = $this->leads->create($otherProject, ['name' => 'Elsewhere'], 9);

    expect(crmStatusCode(fn () => app(CrmLeadStatusTestApi::class)->resumeLead(crmStatusRequest(1, 'POST'), $otherLead->id)))->toBe(404)
        ->and(crmStatusCode(fn () => app(CrmLeadStatusTestApi::class)->moveLead(
            crmStatusRequest(1, 'POST', ['stage_id' => ($this->stage)('contacted', $otherProject)->id]), $otherLead->id)))->toBe(404);
});

test('older desktop builds can still create stages with the won flag only', function () {
    $api = app(CrmLeadStatusTestApi::class);
    $res = $api->createStage(crmStatusRequest(1, 'POST', ['name' => 'Signed', 'is_won' => true]), $this->project->id);

    expect($res->getStatusCode())->toBe(201)
        ->and(LeadStage::where('project_id', $this->project->id)->where('name', 'Signed')->value('system_key'))->toBe('won');
});

// ── Stage entry actions ──────────────────────────────────────────────────

test('a stage saves what happens when a lead enters it, cleaned up', function () {
    $stage = $this->stages->create($this->project, [
        'name'        => 'Site Visit',
        'system_key'  => 'in_process',
        'entry_rules' => [
            'require'   => ['follow_up_at', 'estimated_value', 'not_a_field'],
            'task'      => ['enabled' => true, 'title' => 'Confirm the visit time', 'due_days' => 2],
            'notify'    => ['owner' => true, 'head' => false, 'user_ids' => []],
            'max_days'  => 5,
        ],
    ]);

    expect($stage->entryRules())->toBe([
        'require'   => ['follow_up_at', 'estimated_value'],
        'task'      => ['title' => 'Confirm the visit time', 'due_days' => 2],
        'notify'    => ['owner' => true, 'head' => false, 'user_ids' => []],
        'assign_user_ids' => [],
        'max_days'  => 5,
    ])->and($stage->hasEntryActions())->toBeTrue();

    // Everything switched off is stored as no rules at all
    $plain = $this->stages->update($stage, ['name' => 'Site Visit', 'entry_rules' => ['task' => null]]);
    expect($plain->entry_rules)->toBeNull()->and($plain->hasEntryActions())->toBeFalse();

    expect(fn () => $this->stages->update($stage, ['name' => 'Site Visit', 'entry_rules' => ['task' => ['enabled' => true, 'title' => ' ']]]))
        ->toThrow(ValidationException::class);
    expect(fn () => $this->stages->update($stage, ['name' => 'Site Visit', 'entry_rules' => ['assign_user_ids' => [9]]]))
        ->toThrow(ValidationException::class);
});

test('moving a lead into a stage asks for the details it requires and saves them', function () {
    \Illuminate\Support\Facades\Event::fake([\Modules\CRM\Events\LeadEnteredStage::class]);
    $stage = ($this->stage)('negotiation');
    $stage->update(['entry_rules' => ['require' => ['follow_up_at', 'expected_close_date', 'estimated_value']]]);
    $lead = ($this->newLead)();

    expect(fn () => $this->leads->moveStage($lead, $stage->id, 1, ['expected_close_date' => '2030-01-31', 'estimated_value' => 5000]))
        ->toThrow(ValidationException::class);
    expect(fn () => $this->leads->moveStage($lead->fresh(), $stage->id, 1, ['follow_up_at' => '2000-01-01', 'expected_close_date' => '2030-01-31', 'estimated_value' => 5000]))
        ->toThrow(ValidationException::class);
    expect($lead->fresh()->stage_id)->not->toBe($stage->id);

    $this->leads->moveStage($lead->fresh(), $stage->id, 1, ['follow_up_at' => '2030-01-10', 'expected_close_date' => '2030-01-31', 'estimated_value' => 5000]);

    $lead = $lead->fresh();
    expect($lead->stage_id)->toBe($stage->id)
        ->and($lead->expected_close_date->toDateString())->toBe('2030-01-31')
        ->and((float) $lead->estimated_value)->toBe(5000.0);
    \Illuminate\Support\Facades\Event::assertDispatched(\Modules\CRM\Events\LeadEnteredStage::class,
        fn ($e) => $e->stage->id === $stage->id && $e->followUpAt === '2030-01-10 09:00:00' && $e->byUserId === 1);
});

test('a stage without entry actions raises no entry event, and system moves skip the asked details', function () {
    \Illuminate\Support\Facades\Event::fake([\Modules\CRM\Events\LeadEnteredStage::class]);
    $lead = ($this->newLead)();
    $this->leads->moveStage($lead, ($this->stage)('contacted')->id, 1);
    \Illuminate\Support\Facades\Event::assertNotDispatched(\Modules\CRM\Events\LeadEnteredStage::class);

    $sent = ($this->stage)('quotation_sent');
    $sent->update(['entry_rules' => ['require' => ['estimated_value'], 'notify' => ['head' => true]]]);
    $this->leads->moveToStatus($lead->fresh(), 'quotation_sent', 1, system: true);
    expect($lead->fresh()->stage_id)->toBe($sent->id);
    \Illuminate\Support\Facades\Event::assertDispatched(\Modules\CRM\Events\LeadEnteredStage::class, fn ($e) => $e->followUpAt === null);
});

// ── The business's own statuses ──────────────────────────────────────────

test('a business makes its own status and stages made from it count as its standard status', function () {
    $service = app(\Modules\CRM\Services\CustomStatusService::class);
    $visit = $service->create($this->business, [
        'name'        => 'Site Visit',
        'icon'        => 'fa-location-dot',
        'base_key'    => 'in_process',
        'entry_rules' => ['require' => ['follow_up_at'], 'notify' => ['head' => true, 'user_ids' => [1]], 'assign_user_ids' => [1]],
    ]);

    // A template never names people
    expect($visit->entry_rules['notify']['user_ids'])->toBe([])
        ->and($visit->entry_rules['assign_user_ids'])->toBe([])
        ->and($visit->entry_rules['require'])->toBe(['follow_up_at']);

    $stage = $this->stages->create($this->project, ['name' => 'Site Visit', 'custom_status_id' => $visit->id, 'system_key' => 'won']);
    expect($stage->system_key)->toBe('in_process')
        ->and($stage->custom_status_id)->toBe($visit->id)
        ->and($stage->is_won)->toBeFalse();

    // Another business's status can't be used; a standard name can't be taken; reserved bases are refused
    $theirs = $service->create($this->other, ['name' => 'Theirs', 'base_key' => 'contacted']);
    expect(fn () => $this->stages->create($this->project, ['name' => 'X', 'custom_status_id' => $theirs->id]))->toThrow(ValidationException::class)
        ->and(fn () => $service->create($this->business, ['name' => 'won', 'base_key' => 'won']))->toThrow(ValidationException::class)
        ->and(fn () => $service->create($this->business, ['name' => 'Waiting', 'base_key' => 'quotation_sent']))->toThrow(ValidationException::class);

    // Editing without new actions keeps them; deleting keeps the stage on its standard status
    $service->update($visit, ['name' => 'Site Survey', 'base_key' => 'in_process']);
    expect($visit->fresh()->entry_rules['require'])->toBe(['follow_up_at']);

    $service->delete($visit);
    expect($stage->fresh()->custom_status_id)->toBeNull()->and($stage->fresh()->system_key)->toBe('in_process');
});

// ── Custom stages (behaviour by ticks) ───────────────────────────────────

test('a custom stage takes its behaviour from its ticks', function () {
    $stage = $this->stages->create($this->project, [
        'name' => 'test1', 'system_key' => 'won', 'is_custom' => true, 'needs_approval' => true, 'locks_lead' => true,
    ]);
    expect($stage->is_custom)->toBeTrue()
        ->and($stage->is_won)->toBeTrue()
        ->and($stage->needs_approval)->toBeTrue()
        ->and($stage->locks_lead)->toBeTrue();

    // Only open / won / lost / paused, and the ticks go when it becomes an ordinary stage again
    expect(fn () => $this->stages->update($stage, ['name' => 'test1', 'system_key' => 'quotation_sent', 'is_custom' => true]))
        ->toThrow(ValidationException::class);
    $plain = $this->stages->update($stage, ['name' => 'test1', 'system_key' => 'negotiation', 'is_custom' => false]);
    expect($plain->is_custom)->toBeFalse()->and($plain->needs_approval)->toBeFalse()->and($plain->locks_lead)->toBeFalse();
});

test('a move into a stage that must be approved waits until the head approves or rejects it', function () {
    $stage = $this->stages->create($this->project, [
        'name' => 'test1', 'system_key' => 'in_process', 'is_custom' => true, 'needs_approval' => true,
    ]);
    $lead     = ($this->newLead)();
    $from     = $lead->stage_id;
    $requests = app(\Modules\CRM\Services\StageMoveRequestService::class);

    $first = $requests->request($lead, $stage, 1, ['reason' => 'Customer agreed', 'junk' => 'x']);
    expect($lead->fresh()->stage_id)->toBe($from)
        ->and($first->context)->toBe(['reason' => 'Customer agreed'])
        ->and($requests->pendingByLead([$lead->id])[$lead->id]['to_stage_name'])->toBe('test1');

    // Asking again replaces the waiting request; rejecting leaves the lead where it is
    $second = $requests->request($lead->fresh(), $stage, 1);
    expect($first->fresh()->status)->toBe('cancelled');
    $requests->reject($second, 1, 'Not yet');
    expect($second->fresh()->status)->toBe('rejected')->and($lead->fresh()->stage_id)->toBe($from);
    expect(fn () => $requests->approve($second->fresh(), 1))->toThrow(ValidationException::class);

    $third = $requests->request($lead->fresh(), $stage, 1);
    $requests->approve($third, 1);
    expect($lead->fresh()->stage_id)->toBe($stage->id)
        ->and($third->fresh()->status)->toBe('approved')
        ->and($requests->pendingByLead([$lead->id]))->toBe([]);
});

test('a custom stage checklist must be done before the lead moves on', function () {
    $visit = $this->stages->create($this->project, [
        'name' => 'Site Visit', 'system_key' => 'in_process', 'is_custom' => true,
        'exit_checklist' => ['Measurements taken', ' ', 'Photos uploaded', 'Measurements taken'],
    ]);
    expect($visit->exitChecklist())->toBe(['Measurements taken', 'Photos uploaded']);

    $lead = ($this->newLead)();
    $this->leads->moveStage($lead, $visit->id, 1);
    $next = ($this->stage)('negotiation');

    expect(fn () => $this->leads->moveStage($lead->fresh(), $next->id, 1))->toThrow(ValidationException::class);
    $this->leads->setChecklistItem($lead->fresh(), 'Measurements taken', true);
    expect(fn () => $this->leads->setChecklistItem($lead->fresh(), 'Not on the list', true))->toThrow(ValidationException::class);
    expect(fn () => $this->leads->moveStage($lead->fresh(), $next->id, 1))->toThrow(ValidationException::class);

    $this->leads->setChecklistItem($lead->fresh(), 'Photos uploaded', true);
    $this->leads->moveStage($lead->fresh(), $next->id, 1);
    // Ticks belong to the stage the lead was in
    expect($lead->fresh()->stage_id)->toBe($next->id)->and($lead->fresh()->checklist_done)->toBeNull();
});

// ── Stage behaviour settings (built-in behaviour on/off, rules on any stage) ──

test('a standard stage can switch its built-in behaviour off and take the rules custom stages have', function () {
    $sent = ($this->stage)('quotation_sent');
    expect($sent->isReserved())->toBeTrue()
        ->and(collect($sent->builtInBehaviours())->pluck('key')->all())->toBe(['workflow_only']);

    // Keys the status doesn't have are ignored; the rules don't make it a custom stage
    $sent = $this->stages->updateBehaviours($sent, ['disabled_behaviours' => ['workflow_only', 'lost_reason'], 'locks_lead' => true]);
    expect($sent->disabled_behaviours)->toBe(['workflow_only'])
        ->and($sent->isReserved())->toBeFalse()
        ->and($sent->locks_lead)->toBeTrue()
        ->and($sent->is_custom)->toBeFalse()
        ->and($sent->system_key)->toBe('quotation_sent');

    $lead = ($this->newLead)();
    $this->leads->moveStage($lead, $sent->id, 1);
    expect($lead->fresh()->stage_id)->toBe($sent->id);

    // Saving the stage form with the rules keeps them on an ordinary stage
    $sent = $this->stages->update($sent, ['name' => $sent->name, 'system_key' => 'quotation_sent', 'is_custom' => false, 'locks_lead' => true]);
    expect($sent->locks_lead)->toBeTrue();
});

test('a lost stage with its lost reason switched off closes the lead without one', function () {
    $lost = ($this->stage)('lost');
    // "Closes the lead as lost" is what the status means — it can't be switched off
    $lost = $this->stages->updateBehaviours($lost, ['disabled_behaviours' => ['lost_reason', 'closes_lost'], 'requires_reason' => false]);
    expect($lost->disabled_behaviours)->toBe(['lost_reason'])->and($lost->requires_reason)->toBeTrue();

    $lead = ($this->newLead)();
    $this->leads->moveStage($lead, $lost->id, 1);
    expect($lead->fresh()->stage_id)->toBe($lost->id)
        ->and($lead->fresh()->lost_reason_id)->toBeNull()
        ->and($lead->fresh()->closed_at)->not->toBeNull();
});

test('an on-hold stage that does not remember where the lead paused resumes to the default stage', function () {
    $hold = $this->stages->updateBehaviours(($this->stage)('on_hold'), ['disabled_behaviours' => ['remember_from']]);
    $lead = ($this->newLead)();
    $this->leads->moveStage($lead, ($this->stage)('negotiation')->id, 1);
    $this->leads->moveStage($lead->fresh(), $hold->id, 1);
    expect($lead->fresh()->on_hold_from_stage_id)->toBeNull();

    $this->leads->resume($lead->fresh(), 1);
    expect($lead->fresh()->stage_id)->toBe(($this->stage)('new')->id);
});

test('a no-skipping stage is entered only from the stage right before it, or by going back', function () {
    $stages = $this->stages->listForProject($this->project)->values();
    $target = $stages[4]; // after new, assigned, contacted, in process
    $this->stages->update($target, ['name' => $target->name, 'system_key' => 'in_process', 'is_custom' => true, 'no_skip' => true]);

    $lead = ($this->newLead)();
    expect(fn () => $this->leads->moveStage($lead, $target->id, 1))->toThrow(ValidationException::class);

    $this->leads->moveStage($lead->fresh(), $stages[3]->id, 1);
    $this->leads->moveStage($lead->fresh(), $target->id, 1);
    expect($lead->fresh()->stage_id)->toBe($target->id);

    // Coming back from a later stage is fine
    $this->leads->moveStage($lead->fresh(), $stages[7]->id, 1);
    $this->leads->moveStage($lead->fresh(), $target->id, 1);
    expect($lead->fresh()->stage_id)->toBe($target->id);
});
