<?php

require_once __DIR__ . '/Support/CrmWorkflowFixture.php';

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Mail\InquiryReportMail;
use Modules\CRM\Models\InquiryAllocation;
use Modules\CRM\Models\InquiryReport;
use Modules\CRM\Services\CrmSettingsService;
use Modules\CRM\Services\FollowUpService;
use Modules\CRM\Services\InquiryReportService;
use Modules\CRM\Services\InquiryStatusService;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\CRM\Services\LeadService;
use Modules\Pos\Http\Controllers\Api\PosCrmInquiryApiController;
use Modules\Pos\Models\PosNotification;

class CrmwfInquiryApi extends PosCrmInquiryApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

/*
 * The fixture's lead (Kasun) is given to Sunil (3) by the Sales head Nimal (2) on 2026-11-10 —
 * that is Sunil's first allocation of the day.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-11-10 09:00:00');
    crmwfSetUp();
    $this->assign = app(LeadAssignmentService::class);
    $this->newLead = fn (string $name) => app(LeadService::class)->create($this->sales, ['name' => $name, 'phone' => '07' . random_int(10000000, 99999999)], 1);
});

afterEach(fn () => Carbon::setTestNow());

test('every assignment is recorded as a daily allocation', function () {
    $row = InquiryAllocation::sole();

    expect($row->user_id)->toBe(3)
        ->and($row->lead_id)->toBe($this->lead->id)
        ->and($row->assigned_by)->toBe(2)
        ->and($row->department_id)->toBe(10)
        ->and($row->allocation_date->toDateString())->toBe('2026-11-10')
        ->and($row->released_at)->toBeNull();
});

test('a head cannot give a user more than the daily limit', function () {
    app(CrmSettingsService::class)->save($this->business, ['daily_inquiry_limit' => 2]);

    $this->assign->sync(($this->newLead)('Second'), [3], null, 2);

    expect(fn () => $this->assign->sync(($this->newLead)('Third'), [3], null, 2))
        ->toThrow(ValidationException::class, 'Sunil has already been given 2 of 2 inquiries today');

    // Priya still has room; the limit is per person
    $this->assign->sync(($this->newLead)('For Priya'), [6], null, 2);
    expect(InquiryAllocation::where('user_id', 6)->count())->toBe(1);
});

test('the limit starts again the next day', function () {
    app(CrmSettingsService::class)->save($this->business, ['daily_inquiry_limit' => 1]);

    Carbon::setTestNow('2026-11-11 09:00:00');
    $this->assign->sync(($this->newLead)('Tomorrow'), [3], null, 2);

    expect(InquiryAllocation::where('user_id', 3)->whereDate('allocation_date', '2026-11-11')->count())->toBe(1);
});

test('taking someone off a lead the same day gives the slot back', function () {
    app(CrmSettingsService::class)->save($this->business, ['daily_inquiry_limit' => 1]);

    $this->assign->sync($this->lead->fresh(), [], null, 2);
    $this->assign->sync(($this->newLead)('Replacement'), [3], null, 2);

    expect(InquiryAllocation::where('user_id', 3)->active()->count())->toBe(1)
        ->and(InquiryAllocation::where('user_id', 3)->whereNotNull('released_at')->count())->toBe(1);
});

test('stage automations and system moves are recorded but never refused', function () {
    app(CrmSettingsService::class)->save($this->business, ['daily_inquiry_limit' => 1]);

    $this->assign->add(($this->newLead)('Automation'), [3], 2, null, enforceDailyLimit: false);
    $this->assign->sync(($this->newLead)('System'), [3], null, null);

    $rows = InquiryAllocation::where('user_id', 3)->orderBy('id')->get();
    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('over_limit')->all())->toBe([false, true, true]);
});

test('an administrator can grant an exception; staff and heads cannot', function () {
    app(CrmSettingsService::class)->save($this->business, ['daily_inquiry_limit' => 1]);
    $body = ['user_id' => 3, 'extra_allowed' => 2, 'reason' => 'Trade fair leads'];

    expect(crmwfStatus(fn () => crmwf(CrmwfInquiryApi::class, 'grantException', 2, [], 'POST', $body)))->toBe(403)
        ->and(crmwfStatus(fn () => crmwf(CrmwfInquiryApi::class, 'grantException', 3, [], 'POST', $body)))->toBe(403)
        ->and(crmwfStatus(fn () => crmwf(CrmwfInquiryApi::class, 'grantException', 1, [], 'POST', $body)))->toBe(201);

    $this->assign->sync(($this->newLead)('Extra 1'), [3], null, 2);
    $this->assign->sync(($this->newLead)('Extra 2'), [3], null, 2);
    expect(fn () => $this->assign->sync(($this->newLead)('Extra 3'), [3], null, 2))->toThrow(ValidationException::class);

    expect(InquiryAllocation::where('user_id', 3)->where('over_limit', true)->count())->toBe(2);
});

test('the status counts each inquiry once, at its furthest step', function () {
    $contacted = ($this->newLead)('Contacted');
    $this->assign->sync($contacted, [3], null, 2);
    $contacted->fresh()->recordFirstResponse();

    $followed = ($this->newLead)('Followed');
    $this->assign->sync($followed, [3], null, 2);
    app(FollowUpService::class)->create($followed->fresh(), ['due_at' => '2026-11-11 10:00:00'], 3);

    crmwfSentQuotation();   // the fixture's lead now has a sent quotation

    $mine = app(InquiryStatusService::class)->forUser($this->business, 3, '2026-11-10');

    expect($mine['counts'])->toMatchArray([
        'total' => 3, 'pending' => 0, 'contacted' => 1, 'follow_up' => 1, 'quotation' => 1, 'sale' => 0, 'lost' => 0,
    ])->and(collect($mine['leads'])->pluck('category', 'name')->all())
        ->toBe(['Kasun' => 'quotation', 'Contacted' => 'contacted', 'Followed' => 'follow_up'])
        ->and($mine['capacity'])->toMatchArray(['used' => 3, 'limit' => 10, 'remaining' => 7]);
});

test('staff see only their own day; the head sees the department team', function () {
    $staff = crmwf(CrmwfInquiryApi::class, 'status', 3)->getData(true);
    expect($staff['data']['team'])->toBeNull()
        ->and($staff['data']['mine']['counts']['total'])->toBe(1)
        ->and($staff['meta']['can_team'])->toBeFalse();

    $head = crmwf(CrmwfInquiryApi::class, 'status', 2)->getData(true);
    $team = collect($head['data']['team']);
    expect($team->pluck('department.name')->all())->toBe(['Sales'])
        ->and($team[0]['totals']['total'])->toBe(1)
        ->and(collect($team[0]['users'])->pluck('counts.total', 'user.name')->all())->toMatchArray(['Sunil' => 1, 'Nimal' => 0, 'Priya' => 0]);

    // The owner sees every department with a pipeline
    $admin = crmwf(CrmwfInquiryApi::class, 'status', 1)->getData(true);
    expect(collect($admin['data']['team'])->pluck('department.name')->all())->toBe(['Marketing', 'Sales', 'Support']);
});

test('staff see only their own allocations in the record', function () {
    $this->assign->sync(($this->newLead)('Priya lead'), [6], null, 2);

    expect(collect(crmwf(CrmwfInquiryApi::class, 'allocations', 3)->getData(true)['data'])->pluck('user.id')->all())->toBe([3])
        ->and(collect(crmwf(CrmwfInquiryApi::class, 'allocations', 2)->getData(true)['data'])->pluck('user.id')->sort()->values()->all())->toBe([3, 6]);
});

test('the scheduled report is emailed to management at the set time and the heads are told', function () {
    Mail::fake();
    app(CrmSettingsService::class)->save($this->business, [
        'inquiry_report_enabled' => true, 'inquiry_report_time' => '18:00', 'inquiry_report_recipients' => 'ceo@example.com, gm@example.com',
    ]);
    $reports = app(InquiryReportService::class);

    expect($reports->runScheduled()['businesses'])->toBe(0);   // 09:00 — not yet

    Carbon::setTestNow('2026-11-10 18:05:00');
    $counts = $reports->runScheduled();
    expect($counts)->toMatchArray(['businesses' => 1, 'reports' => 3, 'sent' => 3])
        ->and($reports->runScheduled()['businesses'])->toBe(0);   // once a day

    $sales = InquiryReport::where('department_id', 10)->sole();
    expect($sales->status)->toBe('sent')
        ->and($sales->data['totals']['total'])->toBe(1)
        ->and($sales->recipients)->toBe(['ceo@example.com', 'gm@example.com']);
    Mail::assertSent(InquiryReportMail::class, fn ($m) => $m->hasTo('ceo@example.com') && $m->report->is($sales));
    expect(PosNotification::where('user_id', 2)->where('type', 'crm_inquiry_report')->where('reference_id', $sales->id)->exists())->toBeTrue();
});

test('in review mode the report waits for the head, who adds a note and sends it', function () {
    Mail::fake();
    app(CrmSettingsService::class)->save($this->business, [
        'inquiry_report_enabled' => true, 'inquiry_report_time' => '08:00', 'inquiry_report_review' => true, 'inquiry_report_recipients' => ['ceo@example.com'],
    ]);
    app(InquiryReportService::class)->runScheduled();

    $sales = InquiryReport::where('department_id', 10)->sole();
    expect($sales->status)->toBe('draft');
    Mail::assertNothingSent();

    // Support's head cannot touch Sales' report; staff cannot see reports
    expect(crmwfStatus(fn () => crmwf(CrmwfInquiryApi::class, 'send', 8, [$sales->id], 'POST')))->toBe(404)
        ->and(crmwfStatus(fn () => crmwf(CrmwfInquiryApi::class, 'reports', 3)))->toBe(403);

    crmwf(CrmwfInquiryApi::class, 'review', 2, [$sales->id], 'POST', ['note' => 'Sunil was on leave half the day.']);
    $res = crmwf(CrmwfInquiryApi::class, 'send', 2, [$sales->id], 'POST')->getData(true);

    expect($res['data']['status'])->toBe('sent')
        ->and($res['data']['review_note'])->toBe('Sunil was on leave half the day.')
        ->and($res['data']['sent_by']['id'])->toBe(2);
    Mail::assertSent(InquiryReportMail::class, fn ($m) => str_contains($m->reportHtml(), 'Sunil was on leave half the day.'));
});
