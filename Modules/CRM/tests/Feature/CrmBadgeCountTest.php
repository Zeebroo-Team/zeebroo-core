<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;
use Modules\CRM\Models\InquiryReport;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmBadgeCountService;
use Modules\CRM\Services\FollowUpService;
use Modules\CRM\Services\InquiryAllocationService;
use Modules\CRM\Services\InquiryStatusService;
use Modules\CRM\Services\MarketingInboxService;
use Modules\CRM\Services\QuotationApprovalService;
use Modules\CRM\Services\QuotationRevisionService;

// The ribbon counters only sum what each feature's own service already lists,
// so those services are stubbed; the inquiry reports are counted from the table.
beforeEach(function () {
    Schema::create('crm_inquiry_reports', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('department_id')->nullable();
        $t->date('report_date');
        $t->string('status')->default('draft');
        $t->json('data')->nullable();
        $t->timestamps();
    });

    $this->business = (new Business)->forceFill(['id' => 7]);

    $followUps = Mockery::mock(FollowUpService::class);
    $followUps->shouldReceive('listFor')->with(Mockery::any(), Mockery::any(), 'mine', 'overdue')->andReturn(collect([1, 2]));
    $followUps->shouldReceive('listFor')->with(Mockery::any(), Mockery::any(), 'mine', 'today')->andReturn(collect([3]));

    $approvals = Mockery::mock(QuotationApprovalService::class);
    $approvals->shouldReceive('inbox')->andReturn(collect([1, 2, 3]));

    $revisions = Mockery::mock(QuotationRevisionService::class);
    $revisions->shouldReceive('inbox')->andReturn(collect([1]));

    $inbox = Mockery::mock(MarketingInboxService::class);
    $inbox->shouldReceive('counts')->andReturn(['all' => 5, 'review' => 1, 'unverified' => 4, 'verified' => 1]);

    $allocations = Mockery::mock(InquiryAllocationService::class);
    $allocations->shouldReceive('today')->andReturn('2026-11-23');

    $status = Mockery::mock(InquiryStatusService::class);
    $status->shouldReceive('forUser')->andReturn(['counts' => ['total' => 4, 'pending' => 2]]);

    $this->service = new CrmBadgeCountService($followUps, $approvals, $revisions, $inbox, $allocations, $status);

    $report = fn (int $dept, string $date, string $status, int $business = 7) => InquiryReport::create([
        'business_id' => $business, 'department_id' => $dept, 'report_date' => $date, 'status' => $status,
    ]);
    $report(10, '2026-11-22', InquiryReport::STATUS_DRAFT);
    $report(10, '2026-11-21', InquiryReport::STATUS_FAILED);
    $report(10, '2026-11-20', InquiryReport::STATUS_SENT);   // already sent
    $report(11, '2026-11-22', InquiryReport::STATUS_DRAFT);   // another department
    $report(10, '2026-10-01', InquiryReport::STATUS_DRAFT);   // older than 14 days
    $report(10, '2026-11-22', InquiryReport::STATUS_DRAFT, 8); // another business
});

afterEach(fn () => Mockery::close());

test('an admin sees every counter, with the per-tab details', function () {
    $counts = $this->service->counts($this->business, CrmAccess::full(true, 1));

    expect($counts)->toBe([
        'followups' => 3,   // 2 overdue + 1 due today
        'approvals' => 4,   // 3 quotations + 1 revision request
        'inbox'     => 5,
        'inquiries' => 5,   // 2 of my inquiries pending + 3 unsent reports (last 14 days, all departments)
        'details'   => [
            'followups' => ['overdue' => 2, 'today' => 1],
            'approvals' => ['quotations' => 3, 'revisions' => 1],
            'inbox'     => ['all' => 5, 'review' => 1, 'unverified' => 4],
            'inquiries' => ['mine_pending' => 2, 'reports_unsent' => 3],
        ],
    ]);
});

test('a department head sees approvals and only their departments\' reports, not the inbox', function () {
    $head = new CrmAccess(false, userId: 2, role: CrmAccess::ROLE_MEMBER, departmentIds: [10], headDepartmentIds: [10]);
    $counts = $this->service->counts($this->business, $head);

    expect($counts['approvals'])->toBe(4)
        ->and($counts['inbox'])->toBeNull()
        ->and($counts['details']['inbox'])->toBeNull()
        ->and($counts['inquiries'])->toBe(4)
        ->and($counts['details']['inquiries'])->toBe(['mine_pending' => 2, 'reports_unsent' => 2]);
});

test('a staff member gets their follow-ups and their own pending inquiries only', function () {
    $staff = new CrmAccess(false, userId: 3, role: CrmAccess::ROLE_MEMBER, departmentIds: [10]);
    $counts = $this->service->counts($this->business, $staff);

    expect($counts['followups'])->toBe(3)
        ->and($counts['approvals'])->toBeNull()
        ->and($counts['inbox'])->toBeNull()
        ->and($counts['inquiries'])->toBe(2)
        ->and($counts['details']['inquiries'])->toBe(['mine_pending' => 2, 'reports_unsent' => null]);
});

test('a switched-off follow-ups permission hides that counter', function () {
    $staff = new CrmAccess(false, permissions: ['followups_view' => false], userId: 3, role: CrmAccess::ROLE_MEMBER);

    expect($this->service->counts($this->business, $staff)['followups'])->toBeNull();
});
