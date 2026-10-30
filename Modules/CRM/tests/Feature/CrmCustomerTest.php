<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessRole;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\CrmUserPermission;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAuditLog;
use Modules\CRM\Models\Task;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\CustomerImportService;
use Modules\CRM\Services\CustomerProfileService;
use Modules\CRM\Services\CustomerService;
use Modules\CRM\Services\LeadAttachmentService;
use Modules\CRM\Services\LeadIntakeService;
use Modules\CRM\Services\LeadService;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Http\Controllers\Api\PosCrmApiController;
use Modules\Pos\Http\Controllers\Api\PosCustomerApiController;
use Modules\Pos\Models\Customer;
use Modules\Pos\Services\PosSettingsService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** The migration that adds pos_customers.normalized_phone and the email index. */
function crmCustMigration(): object
{
    return require dirname(__DIR__, 3) . '/Pos/database/migrations/2026_11_05_100000_add_normalized_phone_to_pos_customers_table.php';
}

/** POS settings without the settings tables: no required customer fields unless a test sets them. */
class CrmCustTestPosSettings extends PosSettingsService
{
    public static array $values = [];

    public function __construct() {}

    public function forBusiness(Business $business): array
    {
        return self::$values;
    }
}

/*
 * Business 7 "ABC Solar" (owner user 1): Sales (10) head Nimal (2), staff Sunil (3), Marketing (11)
 * Kamala (4) — the marketing intake. Business 8 (owner 9) has its own Sales pipeline.
 * pos_customers is built as before the customer migration, then the real migration runs.
 */
beforeEach(function () {
    Storage::fake('local');
    CrmCustTestPosSettings::$values = [];
    app()->instance(PosSettingsService::class, new CrmCustTestPosSettings());

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
        $t->string('client_uuid')->nullable();
        $t->string('name');
        $t->string('phone')->nullable();
        $t->string('email')->nullable();
        $t->string('address')->nullable();
        $t->text('notes')->nullable();
        $t->string('customer_type')->default('retail');
        $t->unsignedBigInteger('customer_category_id')->nullable();
        $t->decimal('credit_limit', 12, 2)->nullable();
        $t->timestamps();
    });
    Schema::create('pos_customer_categories', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name', 120);
        $t->string('description', 500)->nullable();
        $t->timestamps();
    });
    Schema::create('pos_sales', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('pos_customer_id')->nullable();
        $t->string('sale_number');
        $t->string('status');
        $t->string('payment_method')->nullable();
        $t->decimal('total', 14, 2)->default(0);
        $t->timestamp('sold_at')->nullable();
        $t->timestamps();
    });
    Schema::create('quotations', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('quote_number');
        $t->unsignedBigInteger('customer_id')->nullable();
        $t->string('reference')->nullable();
        $t->date('quote_date')->nullable();
        $t->date('expiry_date')->nullable();
        $t->string('status')->default('draft');
        $t->decimal('total', 14, 2)->default(0);
        $t->timestamps();
    });
    Schema::create('quotation_items', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('quotation_id');
        $t->integer('sort_order')->default(0);
        $t->timestamps();
    });
    Schema::create('invoices', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('invoice_number');
        $t->unsignedBigInteger('customer_id')->nullable();
        $t->date('issue_date')->nullable();
        $t->string('status')->default('draft');
        $t->decimal('total', 14, 2)->default(0);
        $t->timestamps();
    });
    Schema::create('sales_orders', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('order_number');
        $t->unsignedBigInteger('customer_id')->nullable();
        $t->date('order_date')->nullable();
        $t->string('status')->default('pending');
        $t->decimal('total', 14, 2)->default(0);
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

    // A customer saved before the migration: no normalised phone until the backfill runs
    DB::table('pos_customers')->insert(['id' => 40, 'business_id' => 7, 'name' => 'Legacy Lanka', 'phone' => '071 999 8888', 'email' => 'Legacy@Example.com']);

    crmCustMigration()->up();
    foreach (glob(dirname(__DIR__, 3) . '/FileManager/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.php') as $file) {
        (require $file)->up();
    }

    DB::table('users')->insert(collect([1 => 'Owner', 2 => 'Nimal', 3 => 'Sunil', 4 => 'Kamala', 6 => 'Pipeline only', 9 => 'Other owner'])
        ->map(fn ($name, $id) => ['id' => $id, 'name' => $name, 'email' => str_replace(' ', '', strtolower($name)) . '@example.com'])->values()->all());
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
        ['business_id' => 7, 'user_id' => 6, 'role' => 'crm_staff',           'permissions' => json_encode(['crm_pipeline'])],
    ]);
    DB::table('hr_departments')->insert([
        ['id' => 10, 'business_id' => 7, 'name' => 'Sales',     'head_employee_id' => 100],
        ['id' => 11, 'business_id' => 7, 'name' => 'Marketing', 'head_employee_id' => null],
        ['id' => 20, 'business_id' => 8, 'name' => 'Sales',     'head_employee_id' => null],
    ]);
    DB::table('hr_employees')->insert([
        ['id' => 100, 'business_id' => 7, 'user_id' => 2, 'department_id' => 10, 'full_name' => 'Nimal Perera'],
        ['id' => 101, 'business_id' => 7, 'user_id' => 3, 'department_id' => 10, 'full_name' => 'Sunil Silva'],
        ['id' => 103, 'business_id' => 7, 'user_id' => 4, 'department_id' => 11, 'full_name' => 'Kamala Fernando'],
        ['id' => 106, 'business_id' => 7, 'user_id' => 6, 'department_id' => 10, 'full_name' => 'Pipeline Only'],
    ]);

    $this->business = Business::find(7);
    $this->other    = Business::find(8);
    $org            = app(CrmOrgService::class);

    $this->sales     = $org->linkPipeline($this->business, Department::find(10), null);
    $this->marketing = $org->linkPipeline($this->business, Department::find(11), null);
    $org->saveSettings($this->business, Department::find(11), ['is_marketing_intake' => true]);
    $this->otherSales = $org->linkPipeline($this->other, Department::find(20), null);

    $this->customers = app(CustomerService::class);
    $this->imports   = app(CustomerImportService::class);
    $this->leads     = app(LeadService::class);
    $this->access    = fn (int $userId) => app(CrmPermissionService::class)->accessFor($this->business, User::find($userId));
    $this->lead      = fn (array $data, ?int $userId = 1) => app(LeadIntakeService::class)->capture($data + ['name' => 'Kasun'], 'manual', $this->sales, $userId)->lead;
});

class CrmCustTestApi extends PosCustomerApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

class CrmCustTestCrmApi extends PosCrmApiController
{
    public static int $businessId = 7;

    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(self::$businessId);
    }
}

function crmCustRequest(int $userId, string $method = 'GET', array $body = []): Request
{
    $request = Request::create('/api/v1/pos', $method, $body);
    $request->setUserResolver(fn () => User::find($userId));

    return $request;
}

/** Status code of a controller call, including the 403/404/422 it throws. */
function crmCustStatus(callable $call): int
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

// ── Normalised phone ─────────────────────────────────────────────────────

test('the migration adds the normalised phone and the indexes, and rolls back', function () {
    $indexes = collect(Schema::getIndexes('pos_customers'))->pluck('name');

    expect(Schema::hasColumn('pos_customers', 'normalized_phone'))->toBeTrue()
        ->and($indexes)->toContain('pos_customers_business_normalized_phone_index', 'pos_customers_business_email_index');

    crmCustMigration()->down();

    expect(Schema::hasColumn('pos_customers', 'normalized_phone'))->toBeFalse()
        ->and(collect(Schema::getIndexes('pos_customers'))->pluck('name'))->not->toContain('pos_customers_business_email_index');
});

test('saving a customer keeps the phone normalised and the email lower-cased', function () {
    $customer = Customer::create(['business_id' => 7, 'name' => 'Kasun', 'phone' => '077-123 4567', 'email' => '  Kasun@Example.COM ']);

    expect($customer->normalized_phone)->toBe('94771234567')
        ->and($customer->email)->toBe('kasun@example.com');

    $customer->update(['phone' => '+44 20 7946 0958']);
    expect($customer->fresh()->normalized_phone)->toBe('442079460958');

    $customer->update(['phone' => null]);
    expect($customer->fresh()->normalized_phone)->toBeNull();
});

test('the backfill command is a dry run unless --apply is given', function () {
    Artisan::call('crm:normalize-customer-phones');
    expect(DB::table('pos_customers')->where('id', 40)->value('normalized_phone'))->toBeNull()
        ->and(Artisan::output())->toContain('1 to update');

    Artisan::call('crm:normalize-customer-phones', ['--apply' => true]);
    expect(DB::table('pos_customers')->where('id', 40)->value('normalized_phone'))->toBe('94719998888')
        ->and(DB::table('pos_customers')->where('id', 40)->value('email'))->toBe('legacy@example.com');
});

// ── Duplicate detection ──────────────────────────────────────────────────

test('duplicates are found on any phone variant and on the email whatever its case', function (string $phone) {
    $kasun = Customer::create(['business_id' => 7, 'name' => 'Kasun', 'phone' => '0771234567', 'email' => 'kasun@example.com']);

    expect($this->customers->duplicatesOf(7, $phone, null)->pluck('id')->all())->toBe([$kasun->id])
        ->and($this->customers->duplicatesOf(7, null, 'KASUN@example.com')->pluck('id')->all())->toBe([$kasun->id])
        ->and($this->customers->duplicatesOf(7, '0770000000', 'someone@example.com'))->toBeEmpty();
})->with(['+94 77 123 4567', '077-123 4567', '0094771234567', '771234567']);

test('customers saved before the migration are matched by phone before the backfill, and by any email case after it', function () {
    expect($this->customers->duplicatesOf(7, '+94719998888', null)->pluck('id')->all())->toBe([40]);

    // "Legacy@Example.com" as typed back then; MySQL compares it case-insensitively anyway
    Artisan::call('crm:normalize-customer-phones', ['--apply' => true]);

    expect($this->customers->duplicatesOf(7, null, 'LEGACY@example.com')->pluck('id')->all())->toBe([40]);
});

test('the duplicate check never looks at another business and can ignore the customer being edited', function () {
    $ours   = Customer::create(['business_id' => 7, 'name' => 'Ours',   'phone' => '0771234567']);
    $theirs = Customer::create(['business_id' => 8, 'name' => 'Theirs', 'phone' => '0771234567']);

    expect($this->customers->duplicatesOf(7, '0771234567', null)->pluck('id')->all())->toBe([$ours->id])
        ->and($this->customers->duplicatesOf(7, '0771234567', null, $ours->id))->toBeEmpty()
        ->and($this->customers->duplicatesOf(8, '0771234567', null)->pluck('id')->all())->toBe([$theirs->id]);
});

test('the duplicates endpoint warns with what matched', function () {
    $kasun = Customer::create(['business_id' => 7, 'name' => 'Kasun', 'phone' => '0771234567', 'email' => 'kasun@example.com']);

    $res  = app(CrmCustTestApi::class)->duplicates(crmCustRequest(3, 'GET', ['phone' => '+94771234567', 'email' => 'kasun@example.com']));
    $body = $res->getData(true);

    expect($body['data'])->toHaveCount(1)
        ->and($body['data'][0]['id'])->toBe($kasun->id)
        ->and($body['data'][0]['matched_on'])->toBe(['phone', 'email'])
        ->and($body['message'])->toContain('Kasun');

    $editing = app(CrmCustTestApi::class)->duplicates(crmCustRequest(3, 'GET', ['phone' => '0771234567', 'ignore_id' => $kasun->id]))->getData(true);
    expect($editing['data'])->toBe([])
        ->and($editing['message'])->toBeNull();
});

// ── Import ───────────────────────────────────────────────────────────────

test('the old desktop import payload still works and keeps its response shape', function () {
    Customer::create(['business_id' => 7, 'name' => 'Existing', 'phone' => '0771234567']);

    // Exactly what desktop builds before this change send
    $res = app(CrmCustTestApi::class)->import(crmCustRequest(1, 'POST', ['rows' => [
        ['name' => 'Nimal Perera', 'phone' => '0719876543', 'email' => 'nimal@example.com', 'address' => '45 Galle Rd', 'category' => 'VIP', 'customer_type' => 'retail'],
        ['name' => 'Existing again', 'phone' => '+94 77 123 4567', 'customer_type' => 'retail'],
        ['name' => 'Priya', 'phone' => '0765555555', 'customer_type' => 'wholesale'],
    ]]));
    $body = $res->getData(true);

    expect($res->getStatusCode())->toBe(200)
        ->and($body)->toHaveKeys(['imported', 'skipped', 'errors'])
        ->and($body['imported'])->toBe(2)
        ->and($body['skipped'])->toBe(1)
        ->and($body['errors'])->toHaveCount(1)
        ->and($body['errors'][0])->toMatchArray(['row' => 2, 'name' => 'Existing again'])
        ->and($body['errors'][0]['message'])->toContain('Already a customer')
        ->and(Customer::where('business_id', 7)->count())->toBe(4)
        ->and(Customer::where('name', 'Nimal Perera')->first()->category?->name)->toBe('VIP')
        ->and(Customer::where('name', 'Priya')->value('customer_type'))->toBe('wholesale');
});

test('a bad row is reported in errors instead of failing the whole import', function () {
    $body = app(CrmCustTestApi::class)->import(crmCustRequest(1, 'POST', ['rows' => [
        ['name' => 'Good', 'phone' => '0711111111'],
        ['name' => 'Bad email', 'email' => 'not-an-email'],
        ['name' => '', 'phone' => '0722222222'],
        ['name' => 'Wrong type', 'customer_type' => 'vip'],
    ]]))->getData(true);

    expect($body['imported'])->toBe(1)
        ->and($body['skipped'])->toBe(3)
        ->and(array_column($body['errors'], 'row'))->toBe([2, 3, 4])
        ->and(array_column($body['errors'], 'type'))->toBe(['error', 'error', 'error']);
});

test('required customer fields from the POS settings apply to the import', function () {
    CrmCustTestPosSettings::$values = ['customer_require_phone' => true];

    $body = app(CrmCustTestApi::class)->import(crmCustRequest(1, 'POST', ['rows' => [['name' => 'No phone', 'email' => 'np@example.com']]]))->getData(true);

    expect($body['imported'])->toBe(0)
        ->and($body['errors'][0]['message'])->toContain('phone');
});

test('the same contact twice in one file is imported once', function () {
    $body = app(CrmCustTestApi::class)->import(crmCustRequest(1, 'POST', ['rows' => [
        ['name' => 'Kasun', 'phone' => '0771234567'],
        ['name' => 'Kasun P', 'phone' => '+94 77 123 4567'],
        ['name' => 'Kasun mail', 'email' => 'k@example.com'],
        ['name' => 'Kasun mail 2', 'email' => 'K@EXAMPLE.com'],
    ]]))->getData(true);

    expect($body['imported'])->toBe(2)
        ->and(array_column($body['errors'], 'message'))->toBe(['Same contact as row 1.', 'Same contact as row 3.']);
});

test('in update mode a duplicate row fills in the existing customer without blanking it', function () {
    $existing = Customer::create(['business_id' => 7, 'name' => 'Kasun', 'phone' => '0771234567', 'address' => 'Old address', 'notes' => 'Keep me']);

    $body = app(CrmCustTestApi::class)->import(crmCustRequest(1, 'POST', [
        'duplicates' => 'update',
        'rows'       => [['name' => 'Kasun Perera', 'phone' => '+94771234567', 'email' => 'kasun@example.com', 'address' => '']],
    ]))->getData(true);

    $existing->refresh();
    expect($body['imported'])->toBe(0)
        ->and($body['updated'])->toBe(1)
        ->and($body['errors'])->toBe([])
        ->and(Customer::where('business_id', 7)->count())->toBe(2)
        ->and($existing->name)->toBe('Kasun Perera')
        ->and($existing->email)->toBe('kasun@example.com')
        ->and($existing->address)->toBe('Old address')
        ->and($existing->notes)->toBe('Keep me');
});

test('the import preview says what would happen and saves nothing', function () {
    Customer::create(['business_id' => 7, 'name' => 'Kasun', 'phone' => '0771234567']);

    $body = app(CrmCustTestApi::class)->importPreview(crmCustRequest(1, 'POST', [
        'duplicates' => 'update',
        'rows'       => [['name' => 'New one', 'phone' => '0711111111'], ['name' => 'Kasun', 'phone' => '0771234567'], ['name' => 'Bad', 'email' => 'x']],
    ]))->getData(true);

    expect(array_column($body['rows'], 'status'))->toBe(['create', 'update', 'error'])
        ->and($body['totals'])->toMatchArray(['create' => 1, 'update' => 1, 'error' => 1])
        ->and($body['imported'])->toBe(0)
        ->and(Customer::where('business_id', 7)->count())->toBe(2);
});

test('importing needs the customers permission', function () {
    DB::table('business_members')->where('user_id', 6)->update(['role' => 'cashier', 'permissions' => json_encode(['pos_checkout'])]);

    expect(crmCustStatus(fn () => app(CrmCustTestApi::class)->import(crmCustRequest(6, 'POST', ['rows' => [['name' => 'X']]]))))->toBe(403)
        ->and(Customer::where('name', 'X')->exists())->toBeFalse();
});

test('a web import file is read by its column names and the rows not imported can be downloaded', function () {
    $csv  = "Customer Name,Mobile,E-mail,Address,Remarks\n"
          . "Kasun,0771234567,kasun@example.com,Colombo,first\n"
          . "Nimal,0771234567,,Kandy,same phone\n"
          . "Broken,,not-an-email,,\n";
    $path = tempnam(sys_get_temp_dir(), 'cust');
    file_put_contents($path, $csv);

    $table  = $this->imports->readFile($path, 'csv');
    $result = $this->imports->import($this->business, $table['rows'], 'skip');
    $failed = array_values(array_filter($result['rows'], fn ($r) => in_array($r['status'], ['skip', 'error'], true)));
    $errors = $this->imports->errorRowsCsv($table['headers'], $failed);

    expect($table['rows'][0])->toMatchArray(['row' => 2, 'name' => 'Kasun', 'phone' => '0771234567', 'email' => 'kasun@example.com', 'address' => 'Colombo', 'notes' => 'first'])
        ->and($result['totals'])->toMatchArray(['create' => 1, 'skip' => 1, 'error' => 1])
        ->and($errors)->toContain('Row,"Customer Name",Mobile,E-mail,Address,Remarks,Error')
        ->and($errors)->toContain('4,Broken,,not-an-email,,,"The email field must be a valid email address."')
        ->and($errors)->toContain('3,Nimal,0771234567,,Kandy,"same phone","Same contact as row 2."');

    file_put_contents($path, "Phone,Email\n0771234567,a@example.com\n");
    expect(fn () => $this->imports->readFile($path, 'csv'))->toThrow(ValidationException::class);
    unlink($path);
});

test('the template has every import column', function () {
    expect(strtok(ltrim($this->imports->templateCsv(), "\xEF\xBB\xBF"), "\n"))->toBe(implode(',', CustomerImportService::COLUMNS));
});

// ── Lead → customer ──────────────────────────────────────────────────────

test('converting a lead links it to an existing customer with the same phone or email', function (array $leadContact) {
    $lead     = ($this->lead)($leadContact);
    $existing = Customer::create(['business_id' => 7, 'name' => 'Kasun Customer', 'phone' => '0771234567', 'email' => 'kasun@example.com']);

    $converted = $this->leads->convertToCustomer($lead, 1);

    expect($converted->customer_id)->toBe($existing->id)
        ->and($converted->customer->wasRecentlyCreated)->toBeFalse()
        ->and($converted->isWon())->toBeTrue()
        ->and(Customer::where('business_id', 7)->count())->toBe(2)
        ->and(LeadAuditLog::where('lead_id', $lead->id)->where('event', LeadAuditLog::EVENT_CONVERTED)->first()->meta)
            ->toMatchArray(['customer_id' => $existing->id, 'linked_existing' => true]);
})->with([
    'phone' => [['phone' => '+94 77 123 4567']],
    'email' => [['email' => 'KASUN@example.com']],
]);

test('converting a lead with no matching customer creates one', function () {
    $lead = ($this->lead)(['name' => 'Nimal', 'company' => 'Nimal Traders', 'phone' => '0711111111']);

    $converted = $this->leads->convertToCustomer($lead, 1);

    expect($converted->customer->wasRecentlyCreated)->toBeTrue()
        ->and($converted->customer->name)->toBe('Nimal Traders')
        ->and($converted->customer->normalized_phone)->toBe('94711111111');
});

test('a lead already linked at intake keeps its customer, which gets the missing email', function () {
    $existing = Customer::create(['business_id' => 7, 'name' => 'Kasun Customer', 'phone' => '0771234567']);
    $lead     = ($this->lead)(['phone' => '0771234567', 'email' => 'kasun@example.com']);

    expect($lead->customer_id)->toBe($existing->id);

    $converted = $this->leads->convertToCustomer($lead, 1);

    expect($converted->customer_id)->toBe($existing->id)
        ->and($existing->fresh()->email)->toBe('kasun@example.com')
        ->and(Customer::where('business_id', 7)->count())->toBe(2);
});

test('a customer of another business is never linked on convert', function () {
    Customer::create(['business_id' => 8, 'name' => 'Theirs', 'phone' => '0771234567']);
    $lead = ($this->lead)(['phone' => '0771234567']);

    $customer = $this->leads->convertToCustomer($lead, 1)->customer;

    expect($customer->business_id)->toBe(7)
        ->and($customer->wasRecentlyCreated)->toBeTrue();
});

test('the desktop convert endpoint says whether the customer is new and needs edit rights on the lead', function () {
    Customer::create(['business_id' => 7, 'name' => 'Kasun Customer', 'email' => 'kasun@example.com']);
    $lead = ($this->lead)(['email' => 'kasun@example.com', 'phone' => '0771234567']);

    // Pipeline access without the right to edit leads; and a lead of another business
    CrmUserPermission::create(['business_id' => 7, 'user_id' => 6, 'permissions' => ['leads_edit' => false]]);
    $theirs = app(LeadIntakeService::class)->capture(['name' => 'Theirs', 'phone' => '0712222222'], 'manual', $this->otherSales, 9)->lead;

    expect(crmCustStatus(fn () => app(CrmCustTestCrmApi::class)->convertLead(crmCustRequest(6, 'POST'), $lead->id)))->toBe(403)
        ->and(crmCustStatus(fn () => app(CrmCustTestCrmApi::class)->convertLead(crmCustRequest(1, 'POST'), $theirs->id)))->toBe(404)
        ->and($lead->fresh()->converted_at)->toBeNull()
        ->and($theirs->fresh()->converted_at)->toBeNull();

    $res  = app(CrmCustTestCrmApi::class)->convertLead(crmCustRequest(1, 'POST'), $lead->id);
    $body = $res->getData(true);

    expect($res->getStatusCode())->toBe(200)
        ->and($body['data']['created'])->toBeFalse()
        ->and($body['data']['customer']['name'])->toBe('Kasun Customer')
        ->and($body['message'])->toContain('existing customer');

    // A closed lead cannot be converted again
    expect(crmCustStatus(fn () => app(CrmCustTestCrmApi::class)->convertLead(crmCustRequest(1, 'POST'), $lead->id)))->toBe(422);
});

// ── Customer 360 ─────────────────────────────────────────────────────────

test('the customer page brings together leads, quotations, documents, follow-ups, orders and the timeline', function () {
    $customer = Customer::create(['business_id' => 7, 'name' => 'Kasun', 'phone' => '0771234567']);
    $lead     = ($this->lead)(['phone' => '0771234567']);
    $other    = Customer::create(['business_id' => 7, 'name' => 'Someone else']);

    DB::table('quotations')->insert([
        ['business_id' => 7, 'quote_number' => 'Q-001', 'customer_id' => $customer->id, 'quote_date' => '2026-10-01', 'status' => 'rejected', 'total' => 1000, 'created_at' => '2026-10-01 10:00:00'],
        ['business_id' => 7, 'quote_number' => 'Q-002', 'customer_id' => $customer->id, 'quote_date' => '2026-10-05', 'status' => 'sent',     'total' => 900,  'created_at' => '2026-10-05 10:00:00'],
        ['business_id' => 7, 'quote_number' => 'Q-OTHER', 'customer_id' => $other->id, 'quote_date' => '2026-10-05', 'status' => 'sent',  'total' => 5,    'created_at' => '2026-10-05 10:00:00'],
    ]);
    DB::table('invoices')->insert(['business_id' => 7, 'invoice_number' => 'INV-1', 'customer_id' => $customer->id, 'issue_date' => '2026-10-06', 'status' => 'paid', 'total' => 900]);
    DB::table('sales_orders')->insert(['business_id' => 7, 'order_number' => 'SO-1', 'customer_id' => $customer->id, 'order_date' => '2026-10-06', 'status' => 'completed', 'total' => 900]);
    DB::table('pos_sales')->insert([
        ['business_id' => 7, 'pos_customer_id' => $customer->id, 'sale_number' => 'S-1', 'status' => 'completed', 'total' => 50, 'sold_at' => '2026-10-07 09:00:00'],
        ['business_id' => 7, 'pos_customer_id' => $customer->id, 'sale_number' => 'S-2', 'status' => 'void',      'total' => 70, 'sold_at' => '2026-10-07 10:00:00'],
    ]);
    Task::create(['business_id' => 7, 'subject_type' => Customer::class, 'subject_id' => $customer->id, 'title' => 'Call back', 'status' => Task::STATUS_PENDING, 'due_at' => now()->subDay()]);
    Task::create(['business_id' => 7, 'subject_type' => Lead::class, 'subject_id' => $lead->id, 'title' => 'Send brochure', 'status' => Task::STATUS_PENDING]);
    Activity::create(['business_id' => 7, 'subject_type' => Customer::class, 'subject_id' => $customer->id, 'type' => Activity::TYPE_CALL, 'body' => 'Spoke about solar', 'occurred_at' => now(), 'created_by' => 1]);
    app(LeadAttachmentService::class)->upload($lead, UploadedFile::fake()->create('site-plan.pdf', 20, 'application/pdf'), 1);

    $p = app(CustomerProfileService::class)->build($customer, ($this->access)(1));

    expect(array_column($p['leads'], 'id'))->toBe([$lead->id])
        ->and(array_column($p['quotations'], 'number'))->toBe(['Q-002', 'Q-001'])
        ->and(array_column($p['orders'], 'number'))->toBe(['S-2', 'S-1', 'INV-1', 'SO-1'])
        ->and(array_column($p['documents'], 'name'))->toBe(['site-plan.pdf'])
        ->and($p['documents'][0]['lead']['id'])->toBe($lead->id)
        ->and(array_column($p['tasks'], 'title'))->toEqualCanonicalizing(['Call back', 'Send brochure'])
        ->and($p['summary'])->toMatchArray(['leads' => 1, 'open_leads' => 1, 'quotations' => 2, 'open_quotations' => 1, 'open_tasks' => 2, 'overdue_tasks' => 1, 'orders' => 4])
        // Paid invoice + completed POS sale; void sales and orders (invoiced separately) do not count
        ->and($p['summary']['total_sales'])->toBe(950.0)
        ->and(array_column($p['timeline'], 'type'))->toContain('activity', 'quotation', 'invoice', 'pos_sale', 'file')
        ->and(collect($p['timeline'])->firstWhere('type', 'file')['lead']['id'])->toBe($lead->id);
});

test('the customer page only shows the leads the user may see', function () {
    $customer = Customer::create(['business_id' => 7, 'name' => 'Kasun', 'phone' => '0771234567']);
    $sales    = ($this->lead)(['phone' => '0771234567']);
    // Still in the marketing intake (another number, linked by hand), so Sales staff do not see it yet
    $intake   = app(LeadIntakeService::class)->capture(['name' => 'Kasun office', 'phone' => '0112345678'], 'manual', $this->marketing, 4)->lead;
    $intake->update(['customer_id' => $customer->id]);

    $policy  = app(LeadPolicy::class);
    $access  = ($this->access)(3);
    $visible = collect([$sales, $intake])->filter(fn (Lead $l) => $policy->canView($access, $l->fresh()))->pluck('id')->sort()->values()->all();

    $shown = collect(app(CustomerProfileService::class)->build($customer, $access)['leads'])->pluck('id')->sort()->values()->all();

    expect($shown)->toBe($visible)
        // The owner sees both, so something is really being hidden from the staff member
        ->and(app(CustomerProfileService::class)->build($customer, ($this->access)(1))['leads'])->toHaveCount(2)
        ->and(count($visible))->toBeLessThan(2);
});

test('the desktop customer endpoint needs contacts access and stays inside the business', function () {
    $ours   = Customer::create(['business_id' => 7, 'name' => 'Ours']);
    $theirs = Customer::create(['business_id' => 8, 'name' => 'Theirs']);
    $api    = fn () => app(CrmCustTestCrmApi::class);

    $res = $api()->customer(crmCustRequest(1), $ours->id);

    expect($res->getStatusCode())->toBe(200)
        ->and($res->getData(true)['data']['customer']['name'])->toBe('Ours')
        ->and(crmCustStatus(fn () => $api()->customer(crmCustRequest(1), $theirs->id)))->toBe(404);

    // Contacts switched off for this user
    CrmUserPermission::create(['business_id' => 7, 'user_id' => 6, 'permissions' => ['contacts_view' => false]]);
    expect(crmCustStatus(fn () => $api()->customer(crmCustRequest(6), $ours->id)))->toBe(403);
});

// ── Web pages ────────────────────────────────────────────────────────────

class CrmCustTestContactController extends \Modules\CRM\Http\Controllers\ContactController
{
    protected function requireBusiness(Request $request): Business { return Business::find(7); }
}

class CrmCustTestImportController extends \Modules\CRM\Http\Controllers\CustomerImportController
{
    protected function requireBusiness(Request $request): Business { return Business::find(7); }
}

/** Render CRM pages inside a bare layout (the app theme needs tables this schema does not have). */
function crmCustBareTheme(): void
{
    (require dirname(__DIR__, 3) . '/Settings/database/migrations/2026_04_30_181900_create_settings_table.php')->up();

    $dir = sys_get_temp_dir() . '/crm-cust-theme';
    @mkdir($dir . '/layouts', 0777, true);
    file_put_contents($dir . '/layouts/app.blade.php', "@yield('content')");
    \Illuminate\Support\Facades\View::replaceNamespace('theme', [$dir]);
    \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag());
}

/** A web request with a session that lives across the calls of one test. */
function crmCustWebRequest(int $userId, string $method = 'GET', array $body = [], array $files = []): Request
{
    $request = Request::create('/crm/contacts', $method, $body, [], $files);
    $request->setUserResolver(fn () => User::find($userId));
    $request->setLaravelSession(app('session.store'));

    return $request;
}

test('the web customer page shows every section of the 360 view', function () {
    crmCustBareTheme();
    $this->actingAs(User::find(1));
    $customer = Customer::create(['business_id' => 7, 'name' => 'Kasun', 'phone' => '0771234567']);
    $lead     = ($this->lead)(['name' => 'Kasun solar', 'phone' => '0771234567']);
    DB::table('quotations')->insert(['business_id' => 7, 'quote_number' => 'Q-777', 'customer_id' => $customer->id, 'quote_date' => '2026-10-01', 'status' => 'sent', 'total' => 1000]);
    DB::table('invoices')->insert(['business_id' => 7, 'invoice_number' => 'INV-777', 'customer_id' => $customer->id, 'issue_date' => '2026-10-06', 'status' => 'paid', 'total' => 900]);
    Task::create(['business_id' => 7, 'subject_type' => Lead::class, 'subject_id' => $lead->id, 'title' => 'Send brochure', 'status' => Task::STATUS_PENDING]);
    $file = app(LeadAttachmentService::class)->upload($lead, UploadedFile::fake()->create('site-plan.pdf', 20, 'application/pdf'), 1);

    $html = app(CrmCustTestContactController::class)->show(crmCustWebRequest(1), $customer, app(CustomerProfileService::class))->render();

    expect($html)->toContain('Kasun solar', 'Q-777', 'INV-777', 'site-plan.pdf', 'Send brochure', 'Timeline')
        ->toContain(route('crm.leads.attachments.download', [$lead->id, $file->id]));
});

test('the web customer page of another business is not found', function () {
    crmCustBareTheme();
    $theirs = Customer::create(['business_id' => 8, 'name' => 'Theirs']);

    expect(crmCustStatus(fn () => app(CrmCustTestContactController::class)->show(crmCustWebRequest(1), $theirs, app(CustomerProfileService::class))))->toBe(404);
});

test('the web import goes upload, preview, run, and offers the rows not imported', function () {
    crmCustBareTheme();
    $this->actingAs(User::find(1));
    Customer::create(['business_id' => 7, 'name' => 'Kasun', 'phone' => '0771234567', 'address' => 'Old']);
    $web  = app(CrmCustTestImportController::class);
    $file = UploadedFile::fake()->createWithContent('customers.csv',
        "name,phone,email,address\nKasun Perera,+94 77 123 4567,,Colombo 3\nNimal,0711111111,nimal@example.com,\nBad,,nope,\n");

    $upload = $web->store(crmCustWebRequest(1, 'POST', [], ['file' => $file]));
    $token  = basename($upload->getTargetUrl());

    // Default: duplicates are skipped
    $preview = $web->preview(crmCustWebRequest(1), $token)->render();
    expect($preview)->toContain('Already a customer', '1 new', '1 skipped', '1 errors')
        ->and(Customer::where('business_id', 7)->count())->toBe(2);

    // Update mode, then run
    expect($web->preview(crmCustWebRequest(1, 'GET', ['duplicates' => 'update']), $token)->render())->toContain('1 updates');
    $web->run(crmCustWebRequest(1, 'POST', ['duplicates' => 'update']), $token);

    $result = $web->result(crmCustWebRequest(1), $token)->render();
    $errors = $web->errors(crmCustWebRequest(1), $token)->getContent();

    expect($result)->toContain('1 new customers', '1 updated', '1 failed')
        ->and(Customer::where('name', 'Nimal')->exists())->toBeTrue()
        ->and(Customer::where('normalized_phone', '94771234567')->value('address'))->toBe('Colombo 3')
        ->and($errors)->toContain('4,Bad,,nope,')
        ->and($errors)->not->toContain('Nimal')
        // The uploaded file is gone once the import has run; a second run changes nothing
        ->and(Storage::disk('local')->files('crm/customer-imports/7'))->toBe(["crm/customer-imports/7/{$token}-not-imported.csv"]);

    $web->run(crmCustWebRequest(1, 'POST', ['duplicates' => 'update']), $token);
    expect(Customer::where('business_id', 7)->count())->toBe(3);
});

test('an upload is only reachable from the session that made it, and a file without a name column is refused', function () {
    crmCustBareTheme();
    $web = app(CrmCustTestImportController::class);

    expect(crmCustStatus(fn () => $web->preview(crmCustWebRequest(1), (string) \Illuminate\Support\Str::uuid())))->toBe(404);

    $res = $web->store(crmCustWebRequest(1, 'POST', [], ['file' => UploadedFile::fake()->createWithContent('c.csv', "phone\n0771234567\n")]));
    expect($res->getTargetUrl())->toBe(route('crm.contacts.import.index'))
        ->and(session('errors')->first())->toContain('"name" column');
});
