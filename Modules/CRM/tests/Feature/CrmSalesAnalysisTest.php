<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmReportExportService;
use Modules\CRM\Services\CrmSalesAnalysisService;

/*
 * Business 7 "ABC Solar", one pipeline with an open, a won and a lost stage.
 *   Sales (10):   Acme Ltd Ã—2 (one won for 1,000, one open), Kasun (lost)
 *   Support (12): Acme Ltd (open)
 * Quotations (sent): Acme won lead accepted 1,000 = 4 Ã— Solar panel; Acme open lead 600 = 2 Ã— Inverter;
 * a superseded version on the Kasun lead that must be left out.
 */
beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->timestamps();
    });
    Schema::create('businesses', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('branches', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
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
        $t->timestamps();
    });
    Schema::create('products', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('service_items', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('design_studio_designs', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('quotation_id')->nullable();
        $t->timestamps();
    });

    $modules = dirname(__DIR__, 3);
    foreach ([
        ...glob($modules . '/FileManager/database/migrations/*.php'),
        ...glob($modules . '/CRM/database/migrations/*.php'),
        ...glob($modules . '/Sales/database/migrations/*quotation*.php'),
    ] as $file) {
        (require $file)->up();
    }

    DB::table('users')->insert([['id' => 3, 'name' => 'Sunil', 'email' => 'sunil@example.com']]);
    DB::table('businesses')->insert(['id' => 7, 'user_id' => 1, 'name' => 'ABC Solar']);
    DB::table('hr_departments')->insert([
        ['id' => 10, 'business_id' => 7, 'name' => 'Sales'],
        ['id' => 12, 'business_id' => 7, 'name' => 'Support'],
    ]);
    DB::table('products')->insert([
        ['id' => 1, 'business_id' => 7, 'name' => 'Solar panel'],
        ['id' => 2, 'business_id' => 7, 'name' => 'Inverter'],
    ]);
    DB::table('crm_projects')->insert(['id' => 1, 'business_id' => 7, 'name' => 'Solar', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('crm_lead_stages')->insert([
        ['id' => 1, 'project_id' => 1, 'name' => 'New',  'system_key' => 'new',  'is_won' => false, 'is_lost' => false],
        ['id' => 2, 'project_id' => 1, 'name' => 'Won',  'system_key' => 'won',  'is_won' => true,  'is_lost' => false],
        ['id' => 3, 'project_id' => 1, 'name' => 'Lost', 'system_key' => 'lost', 'is_won' => false, 'is_lost' => true],
    ]);
    $lead = fn (int $id, string $name, ?string $company, int $dept, int $stage, float $estimate) => [
        'id' => $id, 'business_id' => 7, 'project_id' => 1, 'department_id' => $dept, 'stage_id' => $stage,
        'name' => $name, 'company' => $company, 'source' => 'manual', 'assigned_to' => 3,
        'estimated_value' => $estimate, 'created_at' => now()->subDays(2), 'updated_at' => now(),
    ];
    DB::table('crm_leads')->insert([
        $lead(1, 'Ann',   'Acme Ltd', 10, 2, 900),
        $lead(2, 'Bob',   'Acme Ltd', 10, 1, 500),
        $lead(3, 'Kasun', null,       10, 3, 300),
        $lead(4, 'Cara',  'Acme Ltd', 12, 1, 200),
    ]);
    $quote = fn (int $id, int $lead, string $status, float $total) => [
        'id' => $id, 'business_id' => 7, 'lead_id' => $lead, 'quote_number' => 'Q-' . $id, 'quote_date' => today(), 'status' => $status,
        'total' => $total, 'sent_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
    ];
    DB::table('quotations')->insert([
        $quote(1, 1, 'accepted', 1000),
        $quote(2, 2, 'sent', 600),
        $quote(3, 3, 'superseded', 999),
    ]);
    DB::table('quotation_items')->insert([
        ['quotation_id' => 1, 'product_id' => 1, 'description' => 'Solar panel', 'quantity' => 4, 'unit_price' => 250, 'line_total' => 1000],
        ['quotation_id' => 2, 'product_id' => 2, 'description' => 'Inverter',    'quantity' => 2, 'unit_price' => 300, 'line_total' => 600],
        ['quotation_id' => 3, 'product_id' => 1, 'description' => 'Solar panel', 'quantity' => 9, 'unit_price' => 111, 'line_total' => 999],
    ]);

    $this->business = Business::find(7);
    $this->report   = fn (array $input) => app(CrmSalesAnalysisService::class)->report($this->business, CrmAccess::full(true, 1), $input);
});

it('groups leads by department with won value and quotations', function () {
    $r = ($this->report)(['group_by' => 'department']);

    $sales = collect($r['rows'])->firstWhere('label', 'Sales');
    expect($sales)->toMatchArray([
        'leads' => 3, 'open' => 1, 'won' => 1, 'lost' => 1, 'conversion_rate' => 33.3,
        'won_value' => 1000.0, 'quotations' => 2, 'quoted_value' => 1600.0,
    ])
        ->and(collect($r['rows'])->firstWhere('label', 'Support')['leads'])->toBe(1)
        ->and($r['totals'])->toMatchArray(['leads' => 4, 'won' => 1, 'conversion_rate' => 25.0, 'avg_deal_value' => 1000.0]);
});

it('groups by customer, falling back to the lead name, and filters by status and search', function () {
    $rows = collect(($this->report)(['group_by' => 'customer'])['rows'])->keyBy('label');
    expect($rows->keys()->sort()->values()->all())->toBe(['Acme Ltd', 'Kasun'])
        ->and($rows['Acme Ltd']['leads'])->toBe(3);

    expect(($this->report)(['group_by' => 'customer', 'status' => 'open'])['totals']['leads'])->toBe(2)
        ->and(collect(($this->report)(['group_by' => 'customer', 'search' => 'kas'])['rows'])->pluck('label')->all())->toBe(['Kasun']);
});

it('shows what each customer prefers, leaving superseded quotations out', function () {
    $products = collect(($this->report)(['group_by' => 'product'])['rows'])->keyBy('label');
    expect($products['Solar panel'])->toMatchArray(['qty' => 4.0, 'quoted_value' => 1000.0, 'won_value' => 1000.0, 'win_share' => 100.0])
        ->and($products['Inverter'])->toMatchArray(['qty' => 2.0, 'won_value' => 0.0]);

    $prefs = ($this->report)(['group_by' => 'customer_product'])['rows'];
    expect(collect($prefs)->map(fn ($r) => [$r['customer'], $r['label'], $r['share']])->all())->toBe([
        ['Acme Ltd', 'Solar panel', 62.5],
        ['Acme Ltd', 'Inverter', 37.5],
    ]);
});

it('limits the department filter to what a head heads', function () {
    $head = new CrmAccess(false, userId: 2, role: CrmAccess::ROLE_MEMBER, headDepartmentIds: [12]);
    app(CrmSalesAnalysisService::class)->report($this->business, $head, ['department_id' => 10]);
})->throws(ValidationException::class);

it('exports the table as CSV and PDF', function () {
    $report  = ($this->report)(['group_by' => 'department']);
    $exports = app(CrmReportExportService::class);

    ob_start();
    $exports->salesAnalysisCsv($report)->sendContent();
    $csv = ob_get_clean();
    expect($csv)->toContain('Department,Leads,Open,Won,Lost')
        ->and($csv)->toContain('Sales,3,1,1,1,33.3')
        ->and($csv)->toContain('Total,4');

    $pdf = $exports->salesAnalysisPdf($this->business, $report);
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($pdf->getContent())->toStartWith('%PDF');
});
