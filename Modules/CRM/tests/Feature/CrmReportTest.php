<?php

require_once __DIR__ . '/Support/CrmWorkflowFixture.php';

use Illuminate\Support\Carbon;
use Modules\CRM\Models\FollowUp;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LostReason;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\CRM\Services\LeadCloseService;
use Modules\CRM\Services\LeadMessageService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\LostReasonService;
use Modules\Sales\Services\QuotationService;

/*
 * November 2026 (the range under test), plus one September lead outside it:
 *
 *   L1 Kasun   Sales    manual    Sunil  created 11-01 10:00, message 13:00 (3 h), a quotation of 100 pending approval,
 *                                       a follow-up overdue since 11-20
 *   L2 Amal    Sales    website   Sunil  est. 1000, created 11-05 08:00, message 09:00 (1 h), quotation 1200 accepted
 *                                       (sent 11-09), won 11-10 08:00
 *   L3 Bimal   Sales    referral  Priya  est. 500,  created 11-06 08:00, lost 11-08 08:00 ("Price")
 *   L4 Chathu  Sales    website   Priya  est. 2000, created 11-07 08:00, won 11-09 08:00
 *   L5 Dilan   Support  phone     Dinesh            created 11-03 08:00, lost 11-04 08:00 ("No budget")
 *   L6 Old     Sales    manual    —                 created 09-01 — outside the range
 */
beforeEach(function () {
    Carbon::setTestNow('2026-11-01 10:00:00');
    crmwfSetUp();

    $leads  = app(LeadService::class);
    $close  = app(LeadCloseService::class);
    $assign = app(LeadAssignmentService::class);
    app(LostReasonService::class)->ensureDefaults(7);
    $price    = LostReason::create(['business_id' => 7, 'label' => 'Price']);
    $noBudget = LostReason::create(['business_id' => 7, 'label' => 'No budget']);

    Carbon::setTestNow('2026-11-01 13:00:00');
    app(LeadMessageService::class)->post($this->lead->fresh(), 3, 'Called, sending a quote');
    $q1 = app(QuotationService::class)->create($this->business, ['quote_date' => '2026-11-02', 'lead_id' => $this->lead->id], [
        ['item_type' => 'custom', 'description' => 'Survey', 'quantity' => 1, 'unit_price' => 100],
    ]);
    $q1->update(['status' => 'pending_approval']);
    FollowUp::create(['business_id' => 7, 'lead_id' => $this->lead->id, 'user_id' => 3, 'status' => 'pending', 'due_at' => '2026-11-20 09:00:00']);

    Carbon::setTestNow('2026-11-03 08:00:00');
    $l5 = $assign->sync($leads->create($this->support, ['name' => 'Dilan', 'source' => 'phone'], 1), [8], 8, 1);
    Carbon::setTestNow('2026-11-04 08:00:00');
    $close->markLost($l5, $noBudget->id, null, 8);

    Carbon::setTestNow('2026-11-05 08:00:00');
    $l2 = $leads->create($this->sales, ['name' => 'Amal', 'source' => 'website', 'estimated_value' => 1000, 'assigned_to' => 3], 2);
    Carbon::setTestNow('2026-11-05 09:00:00');
    app(LeadMessageService::class)->post($l2->fresh(), 3, 'Hello');

    Carbon::setTestNow('2026-11-06 08:00:00');
    $l3 = $leads->create($this->sales, ['name' => 'Bimal', 'source' => 'referral', 'estimated_value' => 500, 'assigned_to' => 6], 2);
    Carbon::setTestNow('2026-11-07 08:00:00');
    $l4 = $leads->create($this->sales, ['name' => 'Chathu', 'source' => 'website', 'estimated_value' => 2000, 'assigned_to' => 6], 2);

    Carbon::setTestNow('2026-11-08 08:00:00');
    $close->markLost($l3->fresh(), $price->id, 'Too dear', 6);
    Carbon::setTestNow('2026-11-09 08:00:00');
    $close->markWon($l4->fresh(), ['create' => 'none'], 6);
    $q2 = app(QuotationService::class)->create($this->business, ['quote_date' => '2026-11-08', 'lead_id' => $l2->id], [
        ['item_type' => 'custom', 'description' => 'Panels', 'quantity' => 1, 'unit_price' => 1200],
    ]);
    $q2->update(['status' => 'accepted', 'sent_at' => now()]);
    Carbon::setTestNow('2026-11-10 08:00:00');
    $close->markWon($l2->fresh(), ['create' => 'none'], 3);

    Carbon::setTestNow('2026-09-01 08:00:00');
    $leads->create($this->sales, ['name' => 'Old', 'estimated_value' => 9999], 2);

    Carbon::setTestNow('2026-11-30 12:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function crmReport(int $userId, array $filters = []): array
{
    return crmwf(CrmwfReportApi::class, 'dashboard', $userId, [], 'GET', $filters + ['from' => '2026-11-01', 'to' => '2026-11-30'])->getData(true)['data'];
}

test('the dashboard numbers for the whole business', function () {
    $r = crmReport(1);
    $k = $r['kpis'];

    expect($k['leads'])->toBe(5)
        ->and($k['won'])->toBe(2)
        ->and($k['lost'])->toBe(2)
        ->and($k['open'])->toBe(1)
        ->and($k['conversion_rate'])->toBe(40)
        ->and($k['pipeline_value'])->toBe(3500)
        ->and($k['won_in_range'])->toBe(2)
        ->and($k['won_value_in_range'])->toBe(3200)   // Amal's accepted 1200 + Chathu's estimated 2000
        ->and($k['lost_in_range'])->toBe(2)
        ->and($k['quotations_sent'])->toBe(1)
        ->and($k['quotation_value_sent'])->toBe(1200)
        ->and($k['quotations_won'])->toBe(1)
        ->and($k['quotation_value_won'])->toBe(1200)
        ->and($k['quotations_lost'])->toBe(0)
        ->and($k['pending_approvals'])->toBe(1)
        ->and($k['pending_approval_value'])->toBe(100)
        ->and($k['overdue_follow_ups'])->toBe(1)
        ->and($k['avg_response_hours'])->toBe(2)      // (3 h + 1 h) / 2
        ->and($k['avg_close_days'])->toBe(2.5);       // (5 + 2 + 2 + 1) / 4
});

test('leads by source, department, the funnel, staff ranking and lost reasons', function () {
    $r = crmReport(1);

    // Kasun was entered without a source
    expect(collect($r['by_source'])->mapWithKeys(fn ($s) => [(string) $s['key'] => [$s['leads'], $s['won']]])->sortKeys()->all())
        ->toBe(['' => [1, 0], 'phone' => [1, 0], 'referral' => [1, 0], 'website' => [2, 2]])
        ->and($r['by_source'][0]['key'])->toBe('website')
        ->and(collect($r['by_source'])->firstWhere('key', null)['label'])->toBe('Unknown')
        ->and(collect($r['by_department'])->mapWithKeys(fn ($d) => [$d['label'] => [$d['leads'], $d['won']]])->all())
        ->toBe(['Sales' => [4, 2], 'Support' => [1, 0]]);

    $funnel = collect($r['funnel'])->pluck('leads', 'key');
    expect($funnel['won'])->toBe(2)->and($funnel['lost'])->toBe(2)->and($funnel['pending_approval'])->toBe(0)
        ->and($funnel->sum())->toBe(5)
        ->and(collect($r['funnel'])->pluck('key')->first())->toBe('new');

    expect(collect($r['staff'])->map(fn ($s) => [$s['name'], $s['leads'], $s['won'], $s['won_value'], $s['conversion_rate']])->all())->toBe([
        ['Priya',  2, 1, 2000, 50],
        ['Sunil',  2, 1, 1200, 50],
        ['Dinesh', 1, 0, 0,    0],
    ]);

    expect(collect($r['lost_reasons'])->pluck('leads', 'label')->all())->toBe(['No budget' => 1, 'Price' => 1])
        ->and(count($r['trend']))->toBe(30)
        ->and(collect($r['trend'])->sum('leads'))->toBe(5);
});

test('filters narrow every number', function () {
    expect(crmReport(1, ['source' => 'website'])['kpis']['leads'])->toBe(2)
        ->and(crmReport(1, ['user_id' => 6])['kpis']['won'])->toBe(1)
        ->and(crmReport(1, ['department_id' => 12])['kpis']['leads'])->toBe(1)
        ->and(crmReport(1, ['from' => '2026-11-06', 'to' => '2026-11-07'])['kpis']['leads'])->toBe(2)
        ->and(crmReport(1, ['from' => '2026-09-01', 'to' => '2026-11-30'])['kpis']['leads'])->toBe(6);
});

test('a head sees only their own department', function () {
    $nimal = crmReport(2);
    expect($nimal['kpis']['leads'])->toBe(4)
        ->and(collect($nimal['by_department'])->pluck('label')->all())->toBe(['Sales'])
        ->and($nimal['kpis']['avg_close_days'])->toBe(3)  // (5 + 2 + 2) / 3
        ->and(collect($nimal['options']['departments'])->pluck('name')->all())->toBe(['Sales'])
        ->and($nimal['options']['scope'])->toBe('headed');

    $dinesh = crmReport(8);
    expect($dinesh['kpis']['leads'])->toBe(1)->and($dinesh['kpis']['overdue_follow_ups'])->toBe(0);

    // Asking for another department is refused
    expect(crmwfStatus(fn () => crmwf(CrmwfReportApi::class, 'dashboard', 2, [], 'GET', ['department_id' => 12])))->toBe(422);
});

test('who may open the dashboard', function (int $userId, int $status) {
    expect(crmwfStatus(fn () => crmwf(CrmwfReportApi::class, 'dashboard', $userId)))->toBe($status);
})->with([
    'owner (admin)' => [1, 200],
    'the CEO'       => [5, 200],
    'a head'        => [2, 200],
    'staff'         => [3, 403],
    'marketing'     => [4, 403],
]);

test('the CEO sees the whole business', function () {
    expect(crmReport(5)['kpis']['leads'])->toBe(5);
});

test('the current view exports to PDF and Excel', function () {
    $pdf = crmwf(CrmwfReportApi::class, 'pdf', 2, [], 'GET', ['from' => '2026-11-01', 'to' => '2026-11-30']);
    expect($pdf->getStatusCode())->toBe(200)
        ->and($pdf->headers->get('Content-Type'))->toBe('application/pdf')
        ->and(substr($pdf->getContent(), 0, 4))->toBe('%PDF')
        ->and($pdf->headers->get('Content-Disposition'))->toContain('crm-dashboard-2026-11-01-to-2026-11-30.pdf');

    $xlsx = crmwf(CrmwfReportApi::class, 'excel', 2, [], 'GET', ['from' => '2026-11-01', 'to' => '2026-11-30']);
    ob_start();
    $xlsx->sendContent();
    $bytes = ob_get_clean();
    expect($xlsx->getStatusCode())->toBe(200)
        ->and(substr($bytes, 0, 2))->toBe('PK');

    $path = tempnam(sys_get_temp_dir(), 'crm') . '.xlsx';
    file_put_contents($path, $bytes);
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    expect($book->getSheetNames())->toContain('Summary', 'Staff ranking', 'Funnel')
        ->and($book->getSheetByName('Summary')->getCell('B3')->getValue())->toBe(4);
    @unlink($path);

    expect(crmwfStatus(fn () => crmwf(CrmwfReportApi::class, 'pdf', 3)))->toBe(403);
});
