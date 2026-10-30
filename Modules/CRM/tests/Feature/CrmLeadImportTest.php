<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessRole;
use Modules\CRM\Http\Controllers\LeadImportController;
use Modules\CRM\Jobs\RunLeadImportJob;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadImport;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\LeadImportService;
use Modules\CRM\Services\LeadIntakeService;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Http\Controllers\Api\PosCrmLeadImportApiController;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * Business 7 (owner user 1): Sales (10) head Nimal (2), staff Sunil (3); Marketing (11) Kamala (4)
 * is the marketing intake. Business 8 (owner user 9) has its own Sales + intake.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-30 12:00:00');
    Storage::fake('local');

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

    DB::table('users')->insert(collect([1 => 'Owner', 2 => 'Nimal', 3 => 'Sunil', 4 => 'Kamala', 9 => 'Other owner'])
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
    ]);
    DB::table('hr_departments')->insert([
        ['id' => 10, 'business_id' => 7, 'name' => 'Sales',     'head_employee_id' => 100],
        ['id' => 11, 'business_id' => 7, 'name' => 'Marketing', 'head_employee_id' => null],
        ['id' => 20, 'business_id' => 8, 'name' => 'Sales',     'head_employee_id' => null],
        ['id' => 21, 'business_id' => 8, 'name' => 'Marketing', 'head_employee_id' => null],
    ]);
    DB::table('hr_employees')->insert([
        ['id' => 100, 'business_id' => 7, 'user_id' => 2, 'department_id' => 10, 'full_name' => 'Nimal Perera'],
        ['id' => 101, 'business_id' => 7, 'user_id' => 3, 'department_id' => 10, 'full_name' => 'Sunil Silva'],
        ['id' => 103, 'business_id' => 7, 'user_id' => 4, 'department_id' => 11, 'full_name' => 'Kamala Fernando'],
    ]);

    $this->business = Business::find(7);
    $org            = app(CrmOrgService::class);

    $this->sales     = $org->linkPipeline($this->business, Department::find(10), null);
    $this->marketing = $org->linkPipeline($this->business, Department::find(11), null);
    $org->saveSettings($this->business, Department::find(11), ['is_marketing_intake' => true]);

    $other = Business::find(8);
    $this->otherSales = $org->linkPipeline($other, Department::find(20), null);
    $org->linkPipeline($other, Department::find(21), null);
    $org->saveSettings($other, Department::find(21), ['is_marketing_intake' => true]);

    $this->imports = app(LeadImportService::class);
    $this->intake  = app(LeadIntakeService::class);
});

afterEach(fn () => Carbon::setTestNow());

/** Upload a file as the owner and return the import with its columns. */
function crmImportUpload(string $content, string $name = 'leads.csv', int $userId = 1, int $businessId = 7): array
{
    return app(LeadImportService::class)->upload(Business::find($businessId), UploadedFile::fake()->createWithContent($name, $content), $userId);
}

/** Preview with the suggested mapping into the intake pipeline. */
function crmImportPreview(LeadImport $import, string $duplicates = 'log', ?string $source = null): array
{
    return app(LeadImportService::class)->preview($import, $import->mapping, $source ?? $import->source, app(LeadIntakeService::class)->intakeProject($import->business_id === 7 ? Business::find(7) : Business::find(8)), $duplicates);
}

/** Good, bad and duplicate rows in our template. */
function crmImportFixture(): string
{
    return implode("\n", [
        'name,phone,email,company,source,product_interest,date,notes,Budget question',
        'Kasun Perera,0771234567,kasun@example.com,Perera Traders,csv,Solar 5kW,2026-10-01,Called about pricing,500k',
        'Bad Email,,not-an-email,,,,,,',
        'No Contact,,,,,,,,',
        'Existing Lead,0712223333,,,,,,,',
        'Kasun Again,+94 77 123 4567,,,,,,,',
        '"Quoted, Name",0759998888,quoted@example.com,,fb,"Inverter, battery",15/10/2026,,',
    ]);
}

// ── Format detection and mapping ─────────────────────────────────────────

test('the format and column mapping are detected from the header row', function (string $header, string $format, array $mapping) {
    $headers = str_getcsv($header, ',', '"', '');

    expect($this->imports->detectFormat($headers))->toBe($format)
        ->and($this->imports->suggestMapping($headers, $format))->toBe($mapping);
})->with([
    'template' => ['name,phone,email,company,source,product_interest,date,notes', 'template',
        ['name', 'phone', 'email', 'company', 'source', 'product_interest', 'date', 'notes']],
    'google form' => ['Timestamp,Your full name,What is your phone number?,Email Address,Which product are you interested in?,Anything else?', 'google_form',
        ['date', 'name', 'phone', 'email', 'product_interest', 'note']],
    'facebook' => ['id,created_time,ad_id,ad_name,campaign_name,form_name,is_organic,platform,full_name,phone_number,email,city,lead_status', 'facebook',
        ['external_id', 'date', '', 'note', 'note', 'note', '', 'source', 'name', 'phone', 'email', 'note', '']],
]);

// ── Preview ──────────────────────────────────────────────────────────────

test('the preview checks every row and saves nothing', function () {
    ($this->intake->capture(['name' => 'Existing', 'phone' => '0712223333'], 'manual', $this->business, 1));

    $import = crmImportUpload(crmImportFixture())['import'];
    $result = crmImportPreview($import);
    $byRow  = collect($result['rows'])->keyBy('row');

    expect($result['totals'])->toBe(['total' => 6, 'ok' => 2, 'duplicate' => 2, 'error' => 2, 'skipped' => 0])
        ->and($byRow[2]['status'])->toBe('ok')
        ->and($byRow[3]['status'])->toBe('error')
        ->and($byRow[3]['reason'])->toContain('not valid')
        ->and($byRow[4]['reason'])->toBe('No phone number or email.')
        ->and($byRow[5]['status'])->toBe('duplicate')
        ->and($byRow[5]['reason'])->toContain('Already a lead')
        // Same normalised phone as row 2
        ->and($byRow[6]['status'])->toBe('duplicate')
        ->and($byRow[6]['reason'])->toContain('row 2')
        ->and($byRow[7]['name'])->toBe('Quoted, Name')
        ->and(Lead::count())->toBe(1)
        ->and($import->fresh()->status)->toBe(LeadImport::STATUS_PREVIEWED);
});

test('with "skip" rows whose contact has an open lead are skipped', function () {
    $this->intake->capture(['name' => 'Existing', 'phone' => '0712223333'], 'manual', $this->business, 1);

    $import = crmImportUpload(crmImportFixture())['import'];
    $result = crmImportPreview($import, 'skip');

    expect($result['totals'])->toMatchArray(['ok' => 2, 'duplicate' => 0, 'skipped' => 2]);

    $this->imports->start($import->fresh());

    expect($import->fresh())->created_count->toBe(2)->skipped_count->toBe(2)->duplicate_count->toBe(0)
        ->and(Activity::where('type', Activity::TYPE_ENQUIRY)->count())->toBe(0);
});

test('a mapping without a phone or email column is refused', function () {
    $import = crmImportUpload(crmImportFixture())['import'];
    $mapping = array_map(fn ($t) => in_array($t, ['phone', 'email'], true) ? '' : $t, $import->mapping);

    expect(fn () => $this->imports->preview($import, $mapping, 'csv', $this->marketing, 'log'))->toThrow(ValidationException::class);
});

// ── Run ──────────────────────────────────────────────────────────────────

test('running the import creates leads through intake and keeps the failed rows', function () {
    $existing = $this->intake->capture(['name' => 'Existing', 'phone' => '0712223333'], 'manual', $this->business, 1)->lead;

    $import = crmImportUpload(crmImportFixture())['import'];
    crmImportPreview($import);
    $import = $this->imports->start($import->fresh());

    expect($import->status)->toBe(LeadImport::STATUS_COMPLETED)
        ->and($import->created_count)->toBe(2)
        ->and($import->duplicate_count)->toBe(2)
        ->and($import->failed_count)->toBe(2)
        ->and($import->processed_rows)->toBe(6);

    $kasun = Lead::where('name', 'Kasun Perera')->first();
    expect($kasun->project_id)->toBe($this->marketing->id)
        ->and($kasun->source)->toBe('csv')
        ->and($kasun->company)->toBe('Perera Traders')
        ->and($kasun->product_interest_note)->toBe('Solar 5kW')
        ->and($kasun->notes)->toBe("Called about pricing\nBudget question: 500k")
        ->and($kasun->created_at->toDateString())->toBe('2026-10-01')
        ->and($kasun->created_by)->toBe(1);

    $quoted = Lead::where('name', 'Quoted, Name')->first();
    expect($quoted->source)->toBe('facebook')
        ->and($quoted->created_at->toDateString())->toBe('2026-10-15');

    // Repeat enquiries were logged on the open leads, not created
    expect($existing->fresh()->needs_review)->toBeTrue()
        ->and(Lead::where('normalized_phone', '94771234567')->count())->toBe(1)
        ->and(Activity::where('type', Activity::TYPE_ENQUIRY)->count())->toBe(2);

    $csv = $this->imports->errorRowsCsv($import);
    expect($csv)->toContain('Row,name,phone')
        ->and($csv)->toContain('Bad Email')
        ->and($csv)->toContain('No phone number or email.');
});

test('uploading the same file again skips the rows imported before', function () {
    $first = crmImportUpload(crmImportFixture())['import'];
    crmImportPreview($first);
    $this->imports->start($first->fresh());
    $leads = Lead::count();

    $second = crmImportUpload(crmImportFixture() . "\nNew Person,0701112222,,,,,,,")['import'];
    $preview = crmImportPreview($second);

    expect($preview['totals'])->toMatchArray(['ok' => 1, 'skipped' => 4, 'error' => 2]);

    $this->imports->start($second->fresh());

    expect(Lead::count())->toBe($leads + 1)
        ->and($second->fresh()->created_count)->toBe(1);
});

test('an import can only be started once', function () {
    $import = crmImportUpload(crmImportFixture())['import'];
    crmImportPreview($import);
    $this->imports->start($import->fresh());

    expect(fn () => $this->imports->start($import->fresh()))->toThrow(ValidationException::class);
});

test('a Facebook Lead Ads export (UTF-16, tab separated) is imported', function () {
    $tsv = implode("\r\n", [
        "id\tcreated_time\tad_id\tcampaign_name\tplatform\tfull_name\tphone_number\temail\twhat_is_your_budget?",
        "l:111\t2026-10-20T09:15:00+05:30\t555\tSolar Oct\tfb\tRuwan Jaya\tp:+94771112222\truwan@example.com\t1M",
        "l:222\t2026-10-21T10:00:00+05:30\t555\tSolar Oct\tig\tDilini K\tp:+94772223333\t\t",
    ]);
    $import = crmImportUpload("\xFF\xFE" . mb_convert_encoding($tsv, 'UTF-16LE', 'UTF-8'), 'facebook-leads.csv')['import'];

    expect($import->format)->toBe('facebook')->and($import->source)->toBe('facebook');

    crmImportPreview($import);
    $this->imports->start($import->fresh());

    $ruwan = Lead::where('name', 'Ruwan Jaya')->first();
    $dilini = Lead::where('name', 'Dilini K')->first();
    expect($ruwan->source)->toBe('facebook')
        ->and($ruwan->external_id)->toBe('l:111')
        ->and($ruwan->normalized_phone)->toBe('94771112222')
        ->and($ruwan->notes)->toBe("campaign_name: Solar Oct\nwhat_is_your_budget?: 1M")
        ->and($dilini->source)->toBe('instagram');
});

test('a Google Forms export keeps unmapped answers in the notes', function () {
    $csv = implode("\n", [
        'Timestamp,Your full name,What is your phone number?,Email Address,Which product are you interested in?,Preferred call time',
        '10/25/2026 14:05:22,Amal Silva,0773334444,amal@example.com,Solar water heater,Evening',
    ]);
    $import = crmImportUpload($csv, 'Responses.csv')['import'];

    expect($import->format)->toBe('google_form');

    crmImportPreview($import);
    $this->imports->start($import->fresh());

    $amal = Lead::where('name', 'Amal Silva')->first();
    expect($amal->source)->toBe('google-form')
        ->and($amal->product_interest_note)->toBe('Solar water heater')
        ->and($amal->notes)->toBe('Preferred call time: Evening')
        ->and($amal->created_at->format('Y-m-d H:i'))->toBe('2026-10-25 14:05');
});

test('large imports run on the queue', function () {
    Queue::fake();

    $rows = ['name,phone'];
    for ($i = 0; $i < LeadImportService::QUEUE_ABOVE_ROWS + 1; $i++) {
        $rows[] = "Person {$i},07" . str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT);
    }
    $import = crmImportUpload(implode("\n", $rows))['import'];
    crmImportPreview($import);

    expect($this->imports->start($import->fresh())->status)->toBe(LeadImport::STATUS_QUEUED)
        ->and(Lead::count())->toBe(0);
    Queue::assertPushed(RunLeadImportJob::class, fn ($job) => $job->importId === $import->id);

    (new RunLeadImportJob($import->id))->handle($this->imports);

    expect($import->fresh())->status->toBe(LeadImport::STATUS_COMPLETED)->created_count->toBe(LeadImportService::QUEUE_ABOVE_ROWS + 1);
});

// ── Desktop API ──────────────────────────────────────────────────────────

class CrmLeadImportTestApi extends PosCrmLeadImportApiController
{
    public static int $businessId = 7;

    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(self::$businessId);
    }
}

function crmImportRequest(int $userId, string $method = 'GET', array $body = [], array $files = []): Request
{
    $request = Request::create('/api/v1/pos/crm/lead-imports', $method, $body, [], $files);
    $request->setUserResolver(fn () => User::find($userId));

    return $request;
}

function crmImportStatus(callable $call): int
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

test('the desktop endpoints upload, preview and run an import', function () {
    $api = fn () => app(CrmLeadImportTestApi::class);

    $upload = $api()->store(crmImportRequest(4, 'POST', [], ['files' => [UploadedFile::fake()->createWithContent('leads.csv', crmImportFixture())]]));
    $data   = $upload->getData(true)['data'];

    expect($upload->getStatusCode())->toBe(201)
        ->and($data['headers'][0])->toBe('name')
        ->and($data['mapping'][1])->toBe('phone')
        ->and($data['project']['id'])->toBe($this->marketing->id)
        ->and($data['sample'])->toHaveCount(5);

    $preview = $api()->preview(crmImportRequest(4, 'POST', ['mapping' => $data['mapping'], 'source' => 'csv', 'duplicates' => 'log']), $data['id'])->getData(true)['data'];
    expect($preview['totals'])->toMatchArray(['ok' => 3, 'duplicate' => 1, 'error' => 2]);

    $run = $api()->run(crmImportRequest(4, 'POST'), $data['id']);
    expect($run->getStatusCode())->toBe(200)
        ->and($run->getData(true)['data']['created_count'])->toBe(3)
        ->and($run->getData(true)['data']['has_error_rows'])->toBeTrue();

    $list = $api()->index(crmImportRequest(4))->getData(true)['data'];
    expect($list['imports'])->toHaveCount(1)
        ->and(collect($list['fields'])->pluck('key'))->toContain('phone');

    expect($api()->errors(crmImportRequest(4), $data['id'])->getContent())->toContain('No phone number or email.');
});

test('only the uploader runs an import and other businesses never see it', function () {
    $api    = fn () => app(CrmLeadImportTestApi::class);
    $import = crmImportUpload(crmImportFixture(), 'leads.csv', 4)['import'];

    // Sunil (staff) cannot add leads to the marketing pipeline
    expect(crmImportStatus(fn () => $api()->preview(crmImportRequest(3, 'POST', ['mapping' => $import->mapping, 'source' => 'csv', 'duplicates' => 'log']), $import->id)))->toBe(404)
        ->and(crmImportStatus(fn () => $api()->preview(crmImportRequest(3, 'POST', ['mapping' => $import->mapping, 'source' => 'csv', 'project_id' => $this->marketing->id, 'duplicates' => 'log']), (crmImportUpload(crmImportFixture(), 'x.csv', 3)['import'])->id)))->toBe(403)
        // Kamala's import is not visible to the Sales staff
        ->and(crmImportStatus(fn () => $api()->show(crmImportRequest(3), $import->id)))->toBe(404);

    CrmLeadImportTestApi::$businessId = 8;
    try {
        expect(crmImportStatus(fn () => $api()->show(crmImportRequest(9), $import->id)))->toBe(404);
    } finally {
        CrmLeadImportTestApi::$businessId = 7;
    }
});

// ── Web ──────────────────────────────────────────────────────────────────

class CrmLeadImportTestWeb extends LeadImportController
{
    protected function requireBusiness(Request $request): Business|RedirectResponse
    {
        return Business::find(7);
    }
}

test('the web screens upload, preview and run an import', function () {
    $web = fn () => app(CrmLeadImportTestWeb::class);

    $upload = $web()->store(crmImportRequest(4, 'POST', [], ['file' => UploadedFile::fake()->createWithContent('leads.csv', crmImportFixture())]));
    $import = LeadImport::query()->latest('id')->first();
    expect($upload->getTargetUrl())->toEndWith('/crm/imports/' . $import->id)
        ->and($import->user_id)->toBe(4);

    $web()->preview(crmImportRequest(4, 'POST', ['mapping' => $import->mapping, 'source' => 'csv', 'duplicates' => 'log']), $import->id);
    expect($import->fresh()->status)->toBe(LeadImport::STATUS_PREVIEWED)
        ->and(Lead::query()->where('business_id', 7)->count())->toBe(0);

    $web()->run(crmImportRequest(4, 'POST'), $import->id);
    expect($import->fresh()->status)->toBe(LeadImport::STATUS_COMPLETED)
        ->and($import->fresh()->created_count)->toBe(3)
        ->and($web()->errors(crmImportRequest(4), $import->id)->getContent())->toContain('No phone number or email.');
});

test('the web screens refuse view-only users and other uploaders', function () {
    $web    = fn () => app(CrmLeadImportTestWeb::class);
    $import = crmImportUpload(crmImportFixture(), 'leads.csv', 4)['import'];

    // Sunil (staff) cannot run Kamala's upload, nor see it
    expect(crmImportStatus(fn () => $web()->run(crmImportRequest(3, 'POST'), $import->id)))->toBe(404)
        ->and(crmImportStatus(fn () => $web()->errors(crmImportRequest(3), $import->id)))->toBe(404);

    // A CEO is view-only and never imports
    DB::table('users')->insert(['id' => 5, 'name' => 'Ceo', 'email' => 'ceo@example.com']);
    DB::table('business_members')->insert(['business_id' => 7, 'user_id' => 5, 'role' => 'crm_ceo', 'permissions' => json_encode(['crm_pipeline'])]);
    expect(crmImportStatus(fn () => $web()->template(crmImportRequest(5))))->toBe(403);
});

test('an import that was never run can be discarded with its file', function () {
    $import = crmImportUpload(crmImportFixture(), 'leads.csv', 4)['import'];
    Storage::disk('local')->assertExists($import->file_path);

    app(CrmLeadImportTestApi::class)->destroy(crmImportRequest(4, 'DELETE'), $import->id);

    Storage::disk('local')->assertMissing($import->file_path);
    expect(LeadImport::find($import->id))->toBeNull();
});
