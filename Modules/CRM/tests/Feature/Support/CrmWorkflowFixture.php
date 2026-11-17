<?php

/*
 * Shared fixture for the lead-closing, follow-up, notification and dashboard tests
 * (require_once'd by each; not a test file itself).
 *
 * Business 7 "ABC Solar" (owner user 1):
 *   Sales (10)     head Nimal (2), staff Sunil (3) — assigned to the lead — and Priya (6)
 *   Marketing (11) Kamala (4) — the marketing intake
 *   Support (12)   head Dinesh (8)
 *   Chen (5) is the CEO.
 * Business 8 "Other Co" (owner user 9) is the other tenant; Ravi (11) works there only.
 * Every CRM, Sales, ProjectManage, AutomationEditor and pos_notifications table comes from the
 * real migrations.
 */

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessRole;
use Modules\CRM\Models\Lead;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\CRM\Services\LeadService;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Http\Controllers\Api\PosCrmFollowUpApiController;
use Modules\Pos\Http\Controllers\Api\PosCrmLeadCloseApiController;
use Modules\Pos\Http\Controllers\Api\PosCrmLeadQuotationApiController;
use Modules\Pos\Http\Controllers\Api\PosCrmReportApiController;
use Modules\Sales\Models\Quotation;
use Symfony\Component\HttpKernel\Exception\HttpException;

function crmwfSetUp(): void
{
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
        $t->decimal('selling_unit_price', 14, 2)->nullable();
        $t->date('received_at')->nullable();
        $t->timestamps();
    });
    Schema::create('design_studio_designs', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('type')->nullable();
        $t->longText('canvas_json')->nullable();
        $t->unsignedBigInteger('quotation_id')->nullable();
        $t->unsignedBigInteger('invoice_id')->nullable();
        $t->string('proposal_group')->nullable();
        $t->timestamps();
    });
    // service_items for quotation lines; the rest are what pm_projects' foreign keys point at
    foreach (['service_items', 'properties', 'modifications', 'rentals'] as $name) {
        Schema::create($name, function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id');
            $t->string('name')->nullable();
            $t->timestamps();
        });
    }

    $modules = dirname(__DIR__, 4);
    foreach ([
        ...glob($modules . '/FileManager/database/migrations/*.php'),
        ...glob($modules . '/CRM/database/migrations/*.php'),
        $modules . '/Settings/database/migrations/2026_04_30_181900_create_settings_table.php',
        ...glob($modules . '/Sales/database/migrations/*.php'),
        ...glob($modules . '/ProjectManage/database/migrations/*.php'),
        ...glob($modules . '/AutomationEditor/database/migrations/*.php'),
        ...glob($modules . '/Pos/database/migrations/*pos_notifications*.php'),
    ] as $file) {
        (require $file)->up();
    }

    DB::table('users')->insert(collect([1 => 'Owner', 2 => 'Nimal', 3 => 'Sunil', 4 => 'Kamala', 5 => 'Chen', 6 => 'Priya', 8 => 'Dinesh', 9 => 'Other owner', 11 => 'Ravi'])
        ->map(fn ($name, $id) => ['id' => $id, 'name' => $name, 'email' => strtolower(str_replace(' ', '', $name)) . '@example.com'])->values()->all());
    DB::table('businesses')->insert([['id' => 7, 'user_id' => 1, 'name' => 'ABC Solar'], ['id' => 8, 'user_id' => 9, 'name' => 'Other Co']]);
    DB::table('branches')->insert([['id' => 1, 'business_id' => 7, 'name' => 'Colombo']]);
    BusinessRole::seedForBusiness(7);
    BusinessRole::seedForBusiness(8);
    $crmKeys = json_encode(['crm_pipeline', 'crm_contacts', 'crm_tasks', 'crm_forms']);
    DB::table('business_members')->insert([
        ['business_id' => 7, 'user_id' => 2,  'role' => 'crm_department_head', 'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 3,  'role' => 'crm_staff',           'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 4,  'role' => 'crm_marketing',       'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 5,  'role' => 'crm_ceo',             'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 6,  'role' => 'crm_staff',           'permissions' => $crmKeys],
        ['business_id' => 7, 'user_id' => 8,  'role' => 'crm_department_head', 'permissions' => $crmKeys],
        ['business_id' => 8, 'user_id' => 11, 'role' => 'crm_marketing',       'permissions' => $crmKeys],
    ]);
    DB::table('hr_departments')->insert([
        ['id' => 10, 'business_id' => 7, 'name' => 'Sales',     'head_employee_id' => 100],
        ['id' => 11, 'business_id' => 7, 'name' => 'Marketing', 'head_employee_id' => null],
        ['id' => 12, 'business_id' => 7, 'name' => 'Support',   'head_employee_id' => 108],
        ['id' => 20, 'business_id' => 8, 'name' => 'Intake',    'head_employee_id' => null],
    ]);
    DB::table('hr_employees')->insert([
        ['id' => 100, 'business_id' => 7, 'user_id' => 2,  'department_id' => 10, 'full_name' => 'Nimal Perera'],
        ['id' => 101, 'business_id' => 7, 'user_id' => 3,  'department_id' => 10, 'full_name' => 'Sunil Silva'],
        ['id' => 106, 'business_id' => 7, 'user_id' => 6,  'department_id' => 10, 'full_name' => 'Priya Jay'],
        ['id' => 103, 'business_id' => 7, 'user_id' => 4,  'department_id' => 11, 'full_name' => 'Kamala Fernando'],
        ['id' => 108, 'business_id' => 7, 'user_id' => 8,  'department_id' => 12, 'full_name' => 'Dinesh Kumar'],
        ['id' => 111, 'business_id' => 8, 'user_id' => 11, 'department_id' => 20, 'full_name' => 'Ravi Other'],
    ]);
    DB::table('products')->insert([['id' => 1, 'business_id' => 7, 'name' => 'Solar panel', 'sku' => 'SP-1']]);
    DB::table('product_stock_layers')->insert([
        ['business_id' => 7, 'branch_id' => 1, 'product_id' => 1, 'quantity_received' => 50, 'quantity_remaining' => 50],
    ]);

    $test = test();
    $test->business = Business::find(7);
    $org = app(CrmOrgService::class);
    $test->sales     = $org->linkPipeline($test->business, Department::find(10), null);
    $test->marketing = $org->linkPipeline($test->business, Department::find(11), null);
    $test->support   = $org->linkPipeline($test->business, Department::find(12), null);
    $org->saveSettings($test->business, Department::find(11), ['is_marketing_intake' => true]);
    $test->other     = $org->linkPipeline(Business::find(8), Department::find(20), null);
    $org->saveSettings(Business::find(8), Department::find(20), ['is_marketing_intake' => true]);

    $test->lead = app(LeadService::class)->create($test->sales, ['name' => 'Kasun', 'company' => 'Kasun Stores', 'email' => 'kasun@example.com', 'phone' => '0771234567'], 1);
    $test->lead = app(LeadAssignmentService::class)->sync($test->lead, [3], null, 2);

    Storage::fake('local');
    Storage::fake('public');
}

// ── Controllers bound to business 7 ──────────────────────────────────────

class CrmwfCloseApi extends PosCrmLeadCloseApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

class CrmwfFollowUpApi extends PosCrmFollowUpApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

class CrmwfQuoteApi extends PosCrmLeadQuotationApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

class CrmwfReportApi extends PosCrmReportApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

/** Calls a CRM API controller as a user — a fresh controller each time, as each request gets. */
function crmwf(string $controller, string $method, int $userId, array $args = [], string $http = 'GET', array $body = [])
{
    $request = Request::create('/api/v1/pos/crm', $http, $body);
    $request->setUserResolver(fn () => User::find($userId));

    return app($controller)->{$method}($request, ...$args);
}

function crmwfStatus(callable $call): int
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

/**
 * A quotation on the test lead, submitted by Sunil, approved by Nimal and sent (email, faked):
 * two lines, 2 × 100 with a 20 line discount and 10% tax, plus 1 × 50 labour.
 */
function crmwfSentQuotation(array $extra = []): Quotation
{
    Mail::fake();
    $lead = test()->lead;
    $res  = crmwf(CrmwfQuoteApi::class, 'store', 3, [$lead->id], 'POST', [
        'quote_date' => now()->toDateString(),
        'items'      => [
            ['item_type' => 'product', 'product_id' => 1, 'description' => 'Solar panel', 'quantity' => 2, 'unit_price' => 100, 'discount_amount' => 20, 'tax_rate' => 10],
            ['item_type' => 'custom', 'description' => 'Labour', 'quantity' => 1, 'unit_price' => 50],
        ],
    ] + $extra);
    $q = Quotation::findOrFail($res->getData(true)['data']['id']);
    crmwf(CrmwfQuoteApi::class, 'submit', 3, [$lead->id, $q->id], 'POST');
    crmwf(CrmwfQuoteApi::class, 'approve', 2, [$lead->id, $q->id], 'POST');
    crmwf(CrmwfQuoteApi::class, 'send', 3, [$lead->id, $q->id], 'POST', ['to' => 'kasun@example.com', 'subject' => 'Quote', 'message' => 'Hi']);

    return $q->fresh();
}

function crmwfLead(): Lead
{
    return Lead::with('stage')->findOrFail(test()->lead->id);
}
