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
    Schema::create('settings', function (Blueprint $t) {
        $t->id();
        $t->string('scope_type');
        $t->unsignedBigInteger('scope_id');
        $t->string('key');
        $t->text('value')->nullable();
        $t->string('value_type')->default('string');
        $t->timestamps();
    });
    Schema::create('pos_customers', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->string('industry', 120)->nullable();
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
    $this->t0 = $t0 = now()->subDays(2)->startOfMinute();
    $lead = fn (int $id, string $name, ?string $company, int $dept, int $stage, float $estimate) => [
        'id' => $id, 'business_id' => 7, 'project_id' => 1, 'department_id' => $dept, 'stage_id' => $stage,
        'name' => $name, 'company' => $company, 'source' => 'manual', 'assigned_to' => 3,
        'estimated_value' => $estimate, 'created_at' => $t0, 'updated_at' => now(),
    ];
    DB::table('crm_leads')->insert([
        $lead(1, 'Ann',   'Acme Ltd', 10, 2, 900),
        $lead(2, 'Bob',   'Acme Ltd', 10, 1, 500),
        $lead(3, 'Kasun', null,       10, 3, 300),
        $lead(4, 'Cara',  'Acme Ltd', 12, 1, 200),
    ]);
    $quote = fn (int $id, int $lead, string $status, float $total, float $subtotal = 0, float $discount = 0) => [
        'id' => $id, 'business_id' => 7, 'lead_id' => $lead, 'quote_number' => 'Q-' . $id, 'quote_date' => today(), 'status' => $status,
        'subtotal' => $subtotal ?: $total, 'discount_amount' => $discount,
        'total' => $total, 'sent_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
    ];
    DB::table('quotations')->insert([
        $quote(1, 1, 'accepted', 1000, 1100, 100), // 9.1% discount, approved by the head
        $quote(2, 2, 'sent', 600),                 // no discount, within the limits
        $quote(3, 3, 'superseded', 999),
    ]);
    DB::table('quotation_approvals')->insert([
        ['quotation_id' => 1, 'action' => 'submitted',    'version' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['quotation_id' => 1, 'action' => 'approved',     'version' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['quotation_id' => 2, 'action' => 'within_limit', 'version' => 1, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Acme Ltd is a saved customer in construction (leads 1 and 2 are linked to it)
    DB::table('pos_customers')->insert(['id' => 1, 'business_id' => 7, 'name' => 'Acme Ltd', 'industry' => 'Construction']);
    DB::table('crm_leads')->whereIn('id', [1, 2])->update(['customer_id' => 1]);

    // Handling: lead 1 worked after 2 h, first contact = its quotation sent (a day later);
    // lead 2 worked after 4 h, called after 5 h, follow-up done yesterday. Lead 4 never worked.
    DB::table('crm_leads')->where('id', 1)->update(['first_response_at' => $t0->copy()->addHours(2)]);
    DB::table('crm_leads')->where('id', 2)->update(['first_response_at' => $t0->copy()->addHours(4)]);
    DB::table('quotations')->where('id', 1)->update(['sent_at' => $t0->copy()->addHours(24)]);
    DB::table('crm_activities')->insert(['business_id' => 7, 'subject_type' => \Modules\CRM\Models\Lead::class, 'subject_id' => 2,
        'type' => 'call', 'occurred_at' => $t0->copy()->addHours(5), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('crm_follow_ups')->insert(['business_id' => 7, 'lead_id' => 2, 'user_id' => 3, 'status' => 'done',
        'due_at' => now()->subDay(), 'done_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()]);

    // What the leads asked for
    DB::table('crm_lead_products')->insert([
        ['lead_id' => 2, 'product_id' => 2],
        ['lead_id' => 3, 'product_id' => 1],
        ['lead_id' => 4, 'product_id' => 1],
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
    expect($csv)->toContain('CRM report — sales by department')
        ->and($csv)->toContain('Department,Leads,Open,Won,Lost')
        ->and($csv)->toContain('Sales,3,1,1,1,33.3')
        ->and($csv)->toContain('Total,4');

    $pdf = $exports->salesAnalysisPdf($this->business, $report);
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($pdf->getContent())->toStartWith('%PDF');
});

it('shows the inquiry-handling KPIs per department', function () {
    $r = ($this->report)(['group_by' => 'department', 'view' => 'kpi']);
    $rows = collect($r['rows'])->keyBy('label');

    expect($r['view'])->toBe('kpi')
        ->and(collect($r['columns'])->pluck('key'))->toContain('avg_contact_hours', 'assigned', 'quote_sale_rate')
        ->and($rows['Sales'])->toMatchArray([
            'inquiries'          => 3,
            'avg_response_hours' => 3.0,   // (2 h + 4 h) ÷ 2
            'avg_contact_hours'  => 17.7,  // (24 h + 5 h + 24 h) ÷ 3 — lead 3's sent (now superseded) version was a contact too
            'follow_ups_done'    => 1,
            'quotations'         => 2,     // the superseded version is left out
            'converted'          => 1,
            'quote_sale_rate'    => 50.0,
            'pending'            => 1,
            'overdue'            => 0,
            'won'                => 1,
            'won_value'          => 1000.0,
            'assigned'           => 3,
            'staff'              => 1,
        ])
        ->and($rows['Support'])->toMatchArray(['inquiries' => 1, 'pending' => 1, 'overdue' => 1, 'avg_response_hours' => null])
        ->and($r['totals'])->toMatchArray(['inquiries' => 4, 'quotations' => 2, 'converted' => 1, 'overdue' => 1, 'inquiry_quote_rate' => 75.0])
        ->and($r['totals'])->not->toHaveKey('staff');
});

it('groups by period with every bucket of the range', function () {
    $from = now()->subDays(40)->toDateString();
    $r = ($this->report)(['group_by' => 'period', 'period' => 'month', 'from' => $from, 'to' => today()->toDateString()]);

    $expected = now()->subDays(40)->startOfMonth()->diffInMonths(now()->startOfMonth()) + 1;
    expect($r['period'])->toBe('month')
        ->and($r['rows'])->toHaveCount((int) round($expected))
        ->and(collect($r['rows'])->firstWhere('label', $this->t0->format('F Y'))['leads'])->toBeGreaterThanOrEqual(1)
        ->and($r['totals']['leads'])->toBe(4);

    $kpi = ($this->report)(['group_by' => 'period', 'period' => 'day', 'view' => 'kpi']);
    expect($kpi['rows'])->toHaveCount(30)
        ->and(collect($kpi['rows'])->firstWhere('label', $this->t0->format('D, j M Y'))['inquiries'])->toBe(4)
        ->and($kpi['totals']['quotations'])->toBe(2);
});

it('refuses a daily report over more than a year', function () {
    ($this->report)(['group_by' => 'period', 'period' => 'day', 'from' => now()->subYears(2)->toDateString()]);
})->throws(ValidationException::class);

it('groups and filters by the customer industry', function () {
    $rows = collect(($this->report)(['group_by' => 'industry'])['rows'])->keyBy('label');
    expect($rows['Construction'])->toMatchArray(['leads' => 2, 'won' => 1])
        ->and($rows['Industry not set']['leads'])->toBe(2);

    $r = ($this->report)(['group_by' => 'department', 'industry' => 'Construction']);
    expect($r['totals']['leads'])->toBe(2)
        ->and($r['options']['industries'])->toBe(['Construction']);
});

it('shows product inquiry volume and filters by product', function () {
    $products = collect(($this->report)(['group_by' => 'product'])['rows'])->keyBy('label');
    expect($products['Solar panel']['inquiries'])->toBe(2)
        ->and($products['Inverter']['inquiries'])->toBe(1);

    expect(($this->report)(['group_by' => 'department', 'product_id' => 2])['totals']['leads'])->toBe(1)
        ->and(collect(($this->report)([])['options']['products'])->pluck('name')->all())->toBe(['Inverter', 'Solar panel']);
});

it('breaks quotations down by discount band with the approval decisions', function () {
    $r = ($this->report)(['group_by' => 'price']);
    $rows = collect($r['rows'])->keyBy('label');

    expect($rows)->toHaveCount(6)
        ->and($rows['No discount'])->toMatchArray(['quotations' => 1, 'quoted_value' => 600.0, 'within_limit' => 1, 'sold' => 0])
        ->and($rows['5% – 10%'])->toMatchArray(['quotations' => 1, 'discount' => 100.0, 'discount_percent' => 9.1, 'approved' => 1, 'sold' => 1, 'sales_value' => 1000.0])
        ->and($r['totals'])->toMatchArray(['quotations' => 2, 'approved' => 1, 'rejected' => 0, 'sale_rate' => 50.0]);
});

it('lists one customer’s inquiries, quotations and sales', function () {
    $h = app(CrmSalesAnalysisService::class)->customerHistory($this->business, CrmAccess::full(true, 1), ['customer' => 'Acme Ltd']);

    expect($h['summary'])->toMatchArray(['inquiries' => 3, 'open' => 2, 'quotations' => 2, 'sales' => 1, 'sales_value' => 1000.0, 'industry' => 'Construction'])
        ->and(collect($h['quotations'])->pluck('number')->sort()->values()->all())->toBe(['Q-1', 'Q-2'])
        ->and($h['sales'][0])->toMatchArray(['lead_id' => 1, 'quotation' => 'Q-1', 'value' => 1000.0]);
});

it('exports the KPI view as Excel', function () {
    $report = ($this->report)(['group_by' => 'staff', 'view' => 'kpi']);

    ob_start();
    app(CrmReportExportService::class)->salesAnalysisExcel($report)->sendContent();
    $xlsx = ob_get_clean();

    expect($xlsx)->toStartWith('PK');
});

it('adds the inquiry KPIs to the dashboard', function () {
    $d = app(\Modules\CRM\Services\CrmReportService::class)->dashboard($this->business, CrmAccess::full(true, 1), []);

    expect($d['inquiry_kpis'])->toMatchArray(['inquiries' => 4, 'quotations' => 2, 'converted' => 1, 'follow_ups_done' => 1, 'overdue' => 1]);
});
