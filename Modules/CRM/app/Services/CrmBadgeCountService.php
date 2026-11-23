<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Carbon;
use Modules\Business\Models\Business;
use Modules\CRM\Models\InquiryReport;

/**
 * The red counters on the desktop's CRM ribbon buttons: what is waiting on the signed-in user.
 * A counter is null when the user has no access to that feature (the button shows no badge).
 *
 *   - followups:  my follow-ups due today or overdue
 *   - approvals:  quotations + revision requests waiting on my approval
 *   - inbox:      leads in the marketing inbox I can see
 *   - inquiries:  daily inquiry reports of my departments still waiting to be sent (last 14 days)
 */
class CrmBadgeCountService
{
    public function __construct(
        private readonly FollowUpService $followUps,
        private readonly QuotationApprovalService $approvals,
        private readonly QuotationRevisionService $revisions,
        private readonly MarketingInboxService $inbox,
        private readonly InquiryAllocationService $allocations,
    ) {}

    /** @return array{followups: ?int, approvals: ?int, inbox: ?int, inquiries: ?int} */
    public function counts(Business $business, CrmAccess $access): array
    {
        return [
            'followups' => $this->followUps($business, $access),
            'approvals' => $this->approvals($business, $access),
            'inbox'     => $this->inbox($business, $access),
            'inquiries' => $this->inquiries($business, $access),
        ];
    }

    private function followUps(Business $business, CrmAccess $access): ?int
    {
        if (! $access->can('followups_view')) {
            return null;
        }

        return $this->followUps->listFor($business, $access, 'mine', 'overdue')->count()
            + $this->followUps->listFor($business, $access, 'mine', 'today')->count();
    }

    private function approvals(Business $business, CrmAccess $access): ?int
    {
        if (! (($access->unrestricted || $access->isHead()) && $access->can('quotations_approve'))) {
            return null;
        }

        return $this->approvals->inbox($business, $access)->count()
            + $this->revisions->inbox($business, $access)->count();
    }

    private function inbox(Business $business, CrmAccess $access): ?int
    {
        if (! $access->usesInbox() || ! $access->can('leads_view') || ! $access->can('inbox_view')) {
            return null;
        }

        return (int) ($this->inbox->counts($business, $access)['all'] ?? 0);
    }

    private function inquiries(Business $business, CrmAccess $access): ?int
    {
        if ($access->isReadOnly()) {
            return null;
        }
        $all = $access->worksAllLeads();
        if (! $all && $access->headDepartmentIds === []) {
            return null;
        }

        $today = $this->allocations->today($business);

        return InquiryReport::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [InquiryReport::STATUS_DRAFT, InquiryReport::STATUS_FAILED])
            ->whereDate('report_date', '>=', Carbon::parse($today)->subDays(13)->toDateString())
            ->whereDate('report_date', '<=', $today)
            ->when(! $all, fn ($q) => $q->whereIn('department_id', $access->headDepartmentIds))
            ->count();
    }
}
