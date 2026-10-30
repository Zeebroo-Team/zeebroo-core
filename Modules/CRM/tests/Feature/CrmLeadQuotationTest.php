<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessRole;
use Modules\CRM\Jobs\SendQuotationDispatchJob;
use Modules\CRM\Mail\QuotationMail;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\CrmSettingsService;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\QuotationDispatch\QuotationChannel;
use Modules\CRM\Services\QuotationDispatch\QuotationDispatcher;
use Modules\CRM\Services\Timeline\TimelineService;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Http\Controllers\Api\PosCrmLeadQuotationApiController;
use Modules\Pos\Http\Controllers\Api\PosQuotationApiController;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationApproval;
use Modules\Sales\Models\QuotationDispatch;
use Modules\Sales\Services\QuotationPrintService;
use Modules\Sales\Services\QuotationService;
use Modules\Settings\Services\SettingsService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * Business 7 "ABC Solar" (owner user 1):
 *   Sales (10)   head Nimal (2), staff Sunil (3) — assigned to the lead — and Priya (6)
 *   Marketing (11) Kamala (4) — the marketing intake
 *   Support (12) head Dinesh (8)
 *   Chen (5) is the CEO.
 * Products 1 "Solar panel" (stock 3 + 2 = 5) and 2 "Inverter" (no stock).
 * CRM, Sales (quotations) and Settings tables come from the real migrations.
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
        $t->string('logo_path')->nullable();
        $t->timestamps();
    });
    Schema::create('branches', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->string('address')->nullable();
        $t->string('phone')->nullable();
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
    Schema::create('product_stock_layers', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('branch_id')->nullable();
        $t->unsignedBigInteger('product_id');
        $t->decimal('quantity_received', 14, 3);
        $t->decimal('quantity_remaining', 14, 3);
        $t->timestamps();
    });
    Schema::create('design_studio_designs', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('type')->nullable();
        $t->longText('canvas_json')->nullable();
        $t->unsignedBigInteger('quotation_id')->nullable();
        $t->string('proposal_group')->nullable();
        $t->timestamps();
    });

    $modules = dirname(__DIR__, 3);
    foreach ([
        ...glob($modules . '/FileManager/database/migrations/*.php'),
        ...glob($modules . '/CRM/database/migrations/*.php'),
        $modules . '/Settings/database/migrations/2026_04_30_181900_create_settings_table.php',
        ...glob($modules . '/Sales/database/migrations/*quotation*.php'),
    ] as $file) {
        (require $file)->up();
    }

    DB::table('users')->insert(collect([1 => 'Owner', 2 => 'Nimal', 3 => 'Sunil', 4 => 'Kamala', 5 => 'Chen', 6 => 'Priya', 8 => 'Dinesh'])
        ->map(fn ($name, $id) => ['id' => $id, 'name' => $name, 'email' => strtolower($name) . '@example.com'])->values()->all());
    DB::table('businesses')->insert(['id' => 7, 'user_id' => 1, 'name' => 'ABC Solar']);
    DB::table('branches')->insert([['id' => 1, 'business_id' => 7, 'name' => 'Colombo'], ['id' => 2, 'business_id' => 7, 'name' => 'Kandy']]);
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
    ]);
    DB::table('hr_employees')->insert([
        ['id' => 100, 'business_id' => 7, 'user_id' => 2, 'department_id' => 10, 'full_name' => 'Nimal Perera'],
        ['id' => 101, 'business_id' => 7, 'user_id' => 3, 'department_id' => 10, 'full_name' => 'Sunil Silva'],
        ['id' => 106, 'business_id' => 7, 'user_id' => 6, 'department_id' => 10, 'full_name' => 'Priya Jay'],
        ['id' => 103, 'business_id' => 7, 'user_id' => 4, 'department_id' => 11, 'full_name' => 'Kamala Fernando'],
        ['id' => 108, 'business_id' => 7, 'user_id' => 8, 'department_id' => 12, 'full_name' => 'Dinesh Kumar'],
    ]);
    DB::table('products')->insert([
        ['id' => 1, 'business_id' => 7, 'name' => 'Solar panel', 'sku' => 'SP-1'],
        ['id' => 2, 'business_id' => 7, 'name' => 'Inverter',    'sku' => 'INV-1'],
    ]);
    DB::table('product_stock_layers')->insert([
        ['business_id' => 7, 'branch_id' => 1, 'product_id' => 1, 'quantity_received' => 10, 'quantity_remaining' => 3],
        ['business_id' => 7, 'branch_id' => 2, 'product_id' => 1, 'quantity_received' => 5,  'quantity_remaining' => 2],
    ]);

    $this->business = Business::find(7);
    $org = app(CrmOrgService::class);
    $this->sales = $org->linkPipeline($this->business, Department::find(10), null);
    $org->linkPipeline($this->business, Department::find(11), null);
    $org->linkPipeline($this->business, Department::find(12), null);
    $org->saveSettings($this->business, Department::find(11), ['is_marketing_intake' => true]);

    $this->lead = app(LeadService::class)->create($this->sales, ['name' => 'Kasun', 'email' => 'kasun@example.com'], 1);
    $this->lead = app(LeadAssignmentService::class)->sync($this->lead, [3], null, 2);

    Storage::fake('local');
    Storage::fake('public');
});

// ── Helpers ──────────────────────────────────────────────────────────────

class CrmQuoteTestApi extends PosCrmLeadQuotationApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

class CrmQuotePosTestApi extends PosQuotationApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

/** Calls the lead quotation API as a user — a fresh controller each time, as each request gets. */
function crmQuote(string $method, int $userId, array $args = [], string $http = 'GET', array $body = [])
{
    $request = Request::create('/api/v1/pos/crm', $http, $body);
    $request->setUserResolver(fn () => User::find($userId));

    return app(CrmQuoteTestApi::class)->{$method}($request, ...$args);
}

function crmQuoteStatus(callable $call): int
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

function crmQuoteBody(?array $items = null, array $extra = []): array
{
    return [
        'quote_date' => '2026-11-05',
        'items'      => $items ?? [
            ['item_type' => 'product', 'product_id' => 1, 'description' => 'Solar panel', 'quantity' => 2, 'unit_price' => 100],
        ],
    ] + $extra;
}

/** A quotation on the test lead, made by Sunil through the API. */
function crmQuoteCreate(?array $items = null, array $extra = []): Quotation
{
    $res = crmQuote('store', 3, [test()->lead->id], 'POST', crmQuoteBody($items, $extra));

    return Quotation::findOrFail($res->getData(true)['data']['id']);
}

function crmQuoteLeadStatus(): ?string
{
    return Lead::with('stage')->find(test()->lead->id)->statusKey();
}

// ── Totals ───────────────────────────────────────────────────────────────

test('regression: a quotation without line discount / tax totals exactly as before', function () {
    $service = app(QuotationService::class);
    $items   = [
        ['item_type' => 'custom', 'description' => 'Install', 'quantity' => 2, 'unit_price' => 100],
        ['item_type' => 'custom', 'description' => 'Cable',   'quantity' => 1.5, 'unit_price' => 33.33],
    ];

    $q = $service->create($this->business, ['quote_date' => '2026-11-05', 'discount_amount' => 20, 'tax_amount' => 15], $items);

    // The original formula: subtotal = Σ round(qty × price, 2); total = subtotal − discount + tax
    expect((float) $q->subtotal)->toBe(250.0)
        ->and((float) $q->discount_amount)->toBe(20.0)
        ->and((float) $q->tax_amount)->toBe(15.0)
        ->and((float) $q->total)->toBe(245.0)
        ->and($q->items->pluck('line_total')->map(fn ($v) => (float) $v)->all())->toBe([200.0, 50.0])
        ->and($q->items->pluck('discount_amount')->map(fn ($v) => (float) $v)->all())->toBe([0.0, 0.0])
        ->and($q->items->pluck('tax_amount')->map(fn ($v) => (float) $v)->all())->toBe([0.0, 0.0])
        ->and($q->quote_number)->toBe('QT-0001')
        ->and($q->version)->toBe(1)
        ->and($q->lead_id)->toBeNull();

    $q = $service->update($q, ['quote_date' => '2026-11-05', 'discount_amount' => 0, 'tax_amount' => 0], [$items[0]]);
    expect((float) $q->subtotal)->toBe(200.0)->and((float) $q->total)->toBe(200.0);

    // Non-lead quotations keep their old status rules: editable while sent, marked sent by hand
    $service->markSent($q);
    expect($q->fresh()->status)->toBe('sent')->and($q->fresh()->isEditable())->toBeTrue();
});

test('line discount and tax, plus a header discount, recalculate every total', function () {
    $q = app(QuotationService::class)->create($this->business, ['quote_date' => '2026-11-05', 'discount_amount' => 10], [
        ['item_type' => 'product', 'product_id' => 1, 'description' => 'Solar panel', 'quantity' => 2, 'unit_price' => 100, 'discount_amount' => 20, 'tax_rate' => 10],
        ['item_type' => 'custom', 'description' => 'Labour', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0],
        ['item_type' => 'custom', 'description' => 'Too much off', 'quantity' => 1, 'unit_price' => 30, 'discount_amount' => 99],
    ]);

    $lines = $q->items;
    expect((float) $lines[0]->line_total)->toBe(180.0)     // 200 − 20
        ->and((float) $lines[0]->tax_amount)->toBe(18.0)   // 10% of 180
        ->and((float) $lines[2]->discount_amount)->toBe(30.0) // capped at the line's gross
        ->and((float) $lines[2]->line_total)->toBe(0.0)
        ->and((float) $q->subtotal)->toBe(280.0)           // 200 + 50 + 30
        ->and((float) $q->discount_amount)->toBe(60.0)     // 20 + 30 + header 10
        ->and((float) $q->tax_amount)->toBe(18.0)
        ->and((float) $q->total)->toBe(238.0)              // 280 − 60 + 18
        ->and(app(QuotationService::class)->headerAdjustments($q))->toBe(['discount_amount' => 10.0, 'tax_amount' => 0.0]);
});

// ── Stock ────────────────────────────────────────────────────────────────

test('each product line shows the stock in hand and warns when the quantity is more', function () {
    $q = crmQuoteCreate([
        ['item_type' => 'product', 'product_id' => 1, 'quantity' => 4, 'unit_price' => 100],
        ['item_type' => 'product', 'product_id' => 1, 'quantity' => 2, 'unit_price' => 100],
        ['item_type' => 'product', 'product_id' => 2, 'quantity' => 1, 'unit_price' => 900],
        ['item_type' => 'custom', 'description' => 'Labour', 'quantity' => 1, 'unit_price' => 50],
    ]);

    $stock = crmQuote('show', 3, [$this->lead->id, $q->id])->getData(true)['data']['stock'];

    expect($stock['mode'])->toBe('warn')
        ->and($stock['has_shortage'])->toBeTrue()
        ->and(collect($stock['lines'])->map(fn ($l) => [$l['index'], $l['requested'], $l['available'], $l['short']])->all())->toBe([
            [0, 6, 5, true],   // panels: 4 + 2 on two lines against 3 + 2 in stock
            [1, 6, 5, true],
            [2, 1, 0, true],   // inverter: nothing in stock
        ]);
});

test('the block setting refuses a quotation that asks for more than is in stock', function () {
    app(CrmSettingsService::class)->save($this->business, ['quotation_stock_check' => 'block']);

    $over = crmQuoteBody([['item_type' => 'product', 'product_id' => 1, 'quantity' => 6, 'unit_price' => 100]]);
    try {
        crmQuote('store', 3, [$this->lead->id], 'POST', $over);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['items.0.quantity' => ['Only 5 in stock (6 quoted).']]);
    }

    expect(Quotation::count())->toBe(0)
        ->and(crmQuoteStatus(fn () => crmQuote('store', 3, [$this->lead->id], 'POST', crmQuoteBody())))->toBe(201);
});

test('the stock check can be switched off', function () {
    app(CrmSettingsService::class)->save($this->business, ['quotation_stock_check' => 'off']);

    $res = crmQuote('stockCheck', 3, [$this->lead->id], 'POST', ['items' => [['item_type' => 'product', 'product_id' => 1, 'quantity' => 99]]]);

    expect($res->getData(true)['data'])->toBe(['mode' => 'off', 'has_shortage' => false, 'lines' => []]);
});

test('in branch mode the stock is that of the quotation\'s branch', function () {
    $check = fn (?int $branch) => crmQuote('stockCheck', 3, [$this->lead->id], 'POST', [
        'branch_id' => $branch, 'items' => [['item_type' => 'product', 'product_id' => 1, 'quantity' => 1]],
    ])->getData(true)['data']['lines'][0]['available'];

    expect($check(2))->toEqual(5);   // branch mode off: the whole business

    app(SettingsService::class)->set($this->business, 'business.multi_warehouse_branch', true);

    expect($check(2))->toEqual(2)
        ->and($check(1))->toEqual(3)
        ->and($check(null))->toEqual(5);
});

// ── Creating from the lead ───────────────────────────────────────────────

test('creating a quotation from the lead links it and moves the lead to Quotation Preparing', function () {
    $res = crmQuote('store', 3, [$this->lead->id], 'POST', crmQuoteBody());
    $q   = Quotation::sole();

    expect($res->getStatusCode())->toBe(201)
        ->and($q->lead_id)->toBe($this->lead->id)
        ->and($q->status)->toBe('draft')
        ->and($q->reference)->toBe('Kasun (Lead #' . $this->lead->id . ')')
        ->and($q->items->sole()->product_id)->toBe(1)
        ->and(crmQuoteLeadStatus())->toBe(LeadStage::STATUS_QUOTATION_PREPARING);

    $list = crmQuote('index', 2, [$this->lead->id])->getData(true);
    expect(array_column($list['data'], 'quote_number'))->toBe(['QT-0001'])
        ->and($list['meta']['can_approve'])->toBeTrue()
        ->and($list['meta']['can_create'])->toBeTrue();

    // The Sales quotation links back to its lead, and is edited from the lead only
    $pos = app(CrmQuotePosTestApi::class);
    $req = Request::create('/', 'GET');
    $req->setUserResolver(fn () => User::find(1));
    $detail = $pos->show($req, $q)->getData(true)['data'];
    expect($detail['lead'])->toBe(['id' => $this->lead->id, 'name' => 'Kasun', 'project_id' => $this->sales->id])
        ->and($detail['is_editable'])->toBeFalse()
        ->and($pos->update(Request::create('/', 'PATCH', crmQuoteBody())->setUserResolver(fn () => User::find(1)), $q)->getStatusCode())->toBe(422);
});

test('who may create a quotation on the lead', function (int $userId, int $status) {
    expect(crmQuoteStatus(fn () => crmQuote('store', $userId, [$this->lead->id], 'POST', crmQuoteBody())))->toBe($status);
})->with([
    'owner'                 => [1, 201],
    'the head'              => [2, 201],
    'the assigned staff'    => [3, 201],
    'staff not on the lead' => [6, 403],
    'the CEO (view only)'   => [5, 403],
    'another department'    => [8, 404],
]);

// ── Approval ─────────────────────────────────────────────────────────────

test('who may submit and who may approve', function () {
    $q = crmQuoteCreate();

    expect(crmQuoteStatus(fn () => crmQuote('submit', 6, [$this->lead->id, $q->id], 'POST')))->toBe(403)
        ->and(crmQuoteStatus(fn () => crmQuote('submit', 3, [$this->lead->id, $q->id], 'POST', ['comment' => 'Please check'])))->toBe(200)
        ->and($q->fresh()->status)->toBe('pending_approval')
        ->and($q->fresh()->submitted_by)->toBe(3)
        ->and(crmQuoteLeadStatus())->toBe(LeadStage::STATUS_PENDING_APPROVAL);

    foreach ([3 => 403, 6 => 403, 5 => 403, 8 => 404] as $userId => $status) {
        expect(crmQuoteStatus(fn () => crmQuote('approve', $userId, [$this->lead->id, $q->id], 'POST')))->toBe($status);
    }

    expect(crmQuoteStatus(fn () => crmQuote('approve', 2, [$this->lead->id, $q->id], 'POST', ['comment' => 'OK'])))->toBe(200);

    $q = $q->fresh();
    expect($q->status)->toBe('approved')
        ->and($q->approved_by)->toBe(2)
        ->and($q->approved_at)->not->toBeNull()
        ->and($q->approval_comment)->toBe('OK')
        ->and(QuotationApproval::where('quotation_id', $q->id)->orderBy('id')->pluck('action')->all())->toBe(['submitted', 'approved'])
        // The PDF of the approved version is rendered and stored
        ->and($q->pdf_path)->toBe('quotations/7/QT-0001.pdf')
        ->and(substr(Storage::disk('local')->get($q->pdf_path), 0, 4))->toBe('%PDF');
});

test('an admin may approve too', function () {
    $q = crmQuoteCreate();
    crmQuote('submit', 3, [$this->lead->id, $q->id], 'POST');

    expect(crmQuoteStatus(fn () => crmQuote('approve', 1, [$this->lead->id, $q->id], 'POST')))->toBe(200);
});

test('returned with comments → the staff edit and resubmit', function () {
    $q = crmQuoteCreate();
    crmQuote('submit', 3, [$this->lead->id, $q->id], 'POST');

    expect(crmQuoteStatus(fn () => crmQuote('returnForChanges', 2, [$this->lead->id, $q->id], 'POST')))->toBe(422);   // a comment is required
    crmQuote('returnForChanges', 2, [$this->lead->id, $q->id], 'POST', ['comment' => 'Discount too high']);

    expect($q->fresh()->status)->toBe('returned')
        ->and($q->fresh()->approval_comment)->toBe('Discount too high')
        ->and(crmQuoteLeadStatus())->toBe(LeadStage::STATUS_QUOTATION_PREPARING);

    $edited = crmQuoteBody([['item_type' => 'product', 'product_id' => 1, 'quantity' => 2, 'unit_price' => 100, 'discount_amount' => 5]]);
    expect(crmQuoteStatus(fn () => crmQuote('update', 3, [$this->lead->id, $q->id], 'PUT', $edited)))->toBe(200)
        ->and((float) $q->fresh()->total)->toBe(195.0)
        ->and(crmQuoteStatus(fn () => crmQuote('submit', 3, [$this->lead->id, $q->id], 'POST')))->toBe(200)
        ->and(crmQuoteLeadStatus())->toBe(LeadStage::STATUS_PENDING_APPROVAL);

    // Waiting for approval: no more edits
    expect(crmQuoteStatus(fn () => crmQuote('update', 3, [$this->lead->id, $q->id], 'PUT', $edited)))->toBe(422);
});

test('rejected → locked; a new version carries the lines over', function () {
    $q = crmQuoteCreate();
    crmQuote('submit', 3, [$this->lead->id, $q->id], 'POST');
    crmQuote('reject', 2, [$this->lead->id, $q->id], 'POST', ['comment' => 'Wrong product']);

    expect($q->fresh()->status)->toBe('rejected')
        ->and(crmQuoteStatus(fn () => crmQuote('update', 3, [$this->lead->id, $q->id], 'PUT', crmQuoteBody())))->toBe(422)
        ->and(crmQuoteStatus(fn () => crmQuote('submit', 3, [$this->lead->id, $q->id], 'POST')))->toBe(422);

    $v2 = Quotation::findOrFail(crmQuote('revise', 3, [$this->lead->id, $q->id], 'POST')->getData(true)['data']['id']);

    expect($v2->quote_number)->toBe('QT-0001-v2')
        ->and($v2->version)->toBe(2)
        ->and($v2->parent_quotation_id)->toBe($q->id)
        ->and($v2->status)->toBe('draft')
        ->and($v2->lead_id)->toBe($this->lead->id)
        ->and($v2->items->sole()->product_id)->toBe(1)
        ->and((float) $v2->total)->toBe((float) $q->total)
        // Numbering carries on from the first version, not the revision
        ->and(crmQuoteCreate()->quote_number)->toBe('QT-0002');
});

test('only quotations waiting for approval can be decided', function () {
    $q = crmQuoteCreate();

    expect(crmQuoteStatus(fn () => crmQuote('approve', 2, [$this->lead->id, $q->id], 'POST')))->toBe(422)
        ->and(crmQuoteStatus(fn () => crmQuote('reject', 2, [$this->lead->id, $q->id], 'POST', ['comment' => 'x'])))->toBe(422);
});

test('the approvals inbox lists what each head has to decide', function () {
    $q = crmQuoteCreate();
    crmQuoteCreate();                                   // a draft — not in the inbox
    crmQuote('submit', 3, [$this->lead->id, $q->id], 'POST');

    $inbox = function (int $userId) {
        $request = Request::create('/', 'GET');
        $request->setUserResolver(fn () => User::find($userId));

        return array_column(app(CrmQuoteTestApi::class)->inbox($request)->getData(true)['data'], 'id');
    };

    expect($inbox(2))->toBe([$q->id])
        ->and($inbox(1))->toBe([$q->id])
        ->and($inbox(3))->toBe([])
        ->and($inbox(8))->toBe([]);
});

// ── Sending ──────────────────────────────────────────────────────────────

test('an unapproved lead quotation cannot be sent', function () {
    $q = crmQuoteCreate();
    $send = ['to' => 'kasun@example.com', 'subject' => 'Quote', 'message' => 'Hi'];

    expect(crmQuoteStatus(fn () => crmQuote('send', 3, [$this->lead->id, $q->id], 'POST', $send)))->toBe(422);

    crmQuote('submit', 3, [$this->lead->id, $q->id], 'POST');
    expect(crmQuoteStatus(fn () => crmQuote('send', 3, [$this->lead->id, $q->id], 'POST', $send)))->toBe(422)
        ->and(fn () => app(QuotationService::class)->markSent($q->fresh()))->toThrow(ValidationException::class)
        ->and(QuotationDispatch::count())->toBe(0)
        ->and($q->fresh()->status)->toBe('pending_approval');
});

test('sending queues the email with the PDF attached; on success the quotation and lead are marked sent', function () {
    Queue::fake();
    $q = crmQuoteCreate();
    crmQuote('submit', 3, [$this->lead->id, $q->id], 'POST');
    crmQuote('approve', 2, [$this->lead->id, $q->id], 'POST');

    $detail = crmQuote('show', 3, [$this->lead->id, $q->id])->getData(true)['data'];
    expect($detail['can']['send'])->toBeTrue()
        ->and($detail['send_defaults']['to'])->toBe('kasun@example.com')
        ->and($detail['send_defaults']['subject'])->toBe('Quotation QT-0001 from ABC Solar');

    $res = crmQuote('send', 3, [$this->lead->id, $q->id], 'POST', [
        'to' => 'kasun@example.com', 'cc' => ['nimal@example.com'], 'subject' => 'Your quotation', 'message' => "Dear Kasun,\nAttached.",
    ]);

    $dispatch = QuotationDispatch::sole();
    expect($res->getStatusCode())->toBe(200)
        ->and($dispatch->status)->toBe('queued')
        ->and($dispatch->channel)->toBe('email')
        ->and($dispatch->cc)->toBe(['nimal@example.com'])
        ->and($dispatch->file_path)->toBe('quotations/7/QT-0001.pdf')
        ->and($dispatch->sent_by)->toBe(3)
        ->and($q->fresh()->status)->toBe('approved');   // not sent until the job ran
    Queue::assertPushed(SendQuotationDispatchJob::class, fn ($job) => $job->dispatchId === $dispatch->id);

    Mail::fake();
    (new SendQuotationDispatchJob($dispatch->id))->handle(app(QuotationDispatcher::class));

    Mail::assertSent(QuotationMail::class, function (QuotationMail $mail) {
        return $mail->hasTo('kasun@example.com')
            && $mail->hasCc('nimal@example.com')
            && $mail->hasSubject('Your quotation')
            && $mail->hasAttachmentFromStorageDisk('local', 'quotations/7/QT-0001.pdf', 'QT-0001.pdf', ['mime' => 'application/pdf']);
    });

    $q = $q->fresh();
    expect($dispatch->fresh()->status)->toBe('sent')
        ->and($dispatch->fresh()->sent_at)->not->toBeNull()
        ->and($q->status)->toBe('sent')
        ->and($q->sent_at)->not->toBeNull()
        ->and($q->sent_via)->toBe('email')
        ->and(crmQuoteLeadStatus())->toBe(LeadStage::STATUS_QUOTATION_SENT);
});

test('a failed send is recorded and leaves the quotation approved', function () {
    app(QuotationDispatcher::class)->extend(new class implements QuotationChannel {
        public function key(): string { return 'email'; }
        public function label(): string { return 'Email'; }
        public function recipientRules(): array { return ['required', 'email']; }
        public function send(QuotationDispatch $dispatch, Quotation $quotation): void
        {
            throw new RuntimeException('SMTP said no');
        }
    });
    $q = crmQuoteCreate();
    crmQuote('submit', 3, [$this->lead->id, $q->id], 'POST');
    crmQuote('approve', 2, [$this->lead->id, $q->id], 'POST');

    // The sync queue runs the job straight away
    $res = crmQuote('send', 3, [$this->lead->id, $q->id], 'POST', ['to' => 'kasun@example.com', 'subject' => 'Q', 'message' => 'Hi']);

    expect($res->getStatusCode())->toBe(422)
        ->and(QuotationDispatch::sole()->status)->toBe('failed')
        ->and(QuotationDispatch::sole()->error)->toBe('SMTP said no')
        ->and($q->fresh()->status)->toBe('approved')
        ->and(crmQuoteLeadStatus())->toBe(LeadStage::STATUS_PENDING_APPROVAL);
});

test('the lead timeline shows the quotation, its approval steps and the send', function () {
    $q = crmQuoteCreate();
    crmQuote('submit', 3, [$this->lead->id, $q->id], 'POST', ['comment' => 'Ready']);
    crmQuote('returnForChanges', 2, [$this->lead->id, $q->id], 'POST', ['comment' => 'Fix the price']);

    $entries = collect(app(TimelineService::class)->forLead($this->lead, ['quotation']));

    expect($entries->pluck('title')->sort()->values()->all())->toBe([
        'QT-0001: returned for changes',
        'QT-0001: submitted for approval',
        'Quotation QT-0001 created',
    ])
        ->and($entries->firstWhere('title', 'QT-0001: returned for changes')['body'])->toBe('Fix the price')
        ->and($entries->firstWhere('title', 'QT-0001: returned for changes')['user']['name'])->toBe('Nimal')
        ->and(collect(app(TimelineService::class)->types())->pluck('key'))->toContain('quotation');
});

test('the PDF can be downloaded by anyone who sees the lead', function () {
    $q = crmQuoteCreate();

    $res = crmQuote('pdf', 6, [$this->lead->id, $q->id]);

    expect($res->getStatusCode())->toBe(200)
        ->and($res->headers->get('content-type'))->toBe('application/pdf')
        ->and(Storage::disk(QuotationPrintService::disk())->exists($q->fresh()->pdf_path))->toBeTrue()
        ->and(crmQuoteStatus(fn () => crmQuote('pdf', 8, [$this->lead->id, $q->id])))->toBe(404);
});

test('the new migrations roll back and run again on SQLite', function () {
    $modules = dirname(__DIR__, 3);
    $files   = [
        ...glob($modules . '/Sales/database/migrations/2026_11_05_*.php'),
        $modules . '/CRM/database/migrations/2026_11_05_100400_add_quotation_stock_check_to_crm_settings_table.php',
    ];

    foreach (array_reverse($files) as $file) {
        (require $file)->down();
    }
    expect(Schema::hasColumn('quotations', 'lead_id'))->toBeFalse()
        ->and(Schema::hasTable('quotation_approvals'))->toBeFalse();

    foreach ($files as $file) {
        (require $file)->up();
    }
    expect(Schema::hasColumns('quotations', ['lead_id', 'version', 'parent_quotation_id', 'submitted_by', 'approved_by', 'approved_at', 'pdf_path', 'sent_at', 'sent_via', 'approval_comment']))->toBeTrue()
        ->and(Schema::hasColumns('quotation_items', ['discount_amount', 'tax_rate', 'tax_amount']))->toBeTrue()
        ->and(Schema::hasTable('quotation_dispatches'))->toBeTrue();
});
