<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Carbon;
use Modules\Business\Models\Business;
use Modules\CRM\Models\InquiryReport;

/**
 * The red counters on the desktop's CRM ribbon buttons, and inside each window the tab that
 * holds them: what is waiting on the signed-in user. A counter (and its details) is null when
 * the user has no access to that feature, so neither the button nor the tab shows a badge.
 *
 *   - followups:  my follow-ups overdue + due today
 *   - approvals:  quotations + revision requests waiting on my approval
 *   - inbox:      leads in the marketing inbox (with how many need review / are unverified)
 *   - inquiries:  my inquiries of today still pending + daily reports of my departments not yet sent (14 days)
 */
class CrmBadgeCountService
{
    public function __construct(
        private readonly FollowUpService $followUps,
        private readonly QuotationApprovalService $approvals,
        private readonly QuotationRevisionService $revisions,
        private readonly MarketingInboxService $inbox,
        private readonly InquiryAllocationService $allocations,
        private readonly InquiryStatusService $inquiryStatus,
    ) {}

    /**
     * @return array{followups: ?int, approvals: ?int, inbox: ?int, inquiries: ?int, details: array<string, ?array<string, ?int>>}
     */
    public function counts(Business $business, CrmAccess $access): array
    {
        $details = [
            'followups' => $this->followUps($business, $access),
            'approvals' => $this->approvals($business, $access),
            'inbox'     => $this->inbox($business, $access),
            'inquiries' => $this->inquiries($business, $access),
        ];

        return [
            'followups' => $details['followups'] === null ? null : $details['followups']['overdue'] + $details['followups']['today'],
            'approvals' => $details['approvals'] === null ? null : $details['approvals']['quotations'] + $details['approvals']['revisions'],
            // Every inbox lead waits on Marketing (to verify or to forward)
            'inbox'     => $details['inbox'] === null ? null : $details['inbox']['all'],
            'inquiries' => $details['inquiries'] === null ? null : (int) $details['inquiries']['mine_pending'] + (int) $details['inquiries']['reports_unsent'],
            'details'   => $details,
        ];
    }

    /** @return array{overdue: int, today: int}|null */
    private function followUps(Business $business, CrmAccess $access): ?array
    {
        if (! $access->can('followups_view')) {
            return null;
        }

        return [
            'overdue' => $this->followUps->listFor($business, $access, 'mine', 'overdue')->count(),
            'today'   => $this->followUps->listFor($business, $access, 'mine', 'today')->count(),
        ];
    }

    /** @return array{quotations: int, revisions: int}|null */
    private function approvals(Business $business, CrmAccess $access): ?array
    {
        if (! (($access->unrestricted || $access->isHead()) && $access->can('quotations_approve'))) {
            return null;
        }

        return [
            'quotations' => $this->approvals->inbox($business, $access)->count(),
            'revisions'  => $this->revisions->inbox($business, $access)->count(),
        ];
    }

    /** @return array{all: int, review: int, unverified: int}|null */
    private function inbox(Business $business, CrmAccess $access): ?array
    {
        if (! $access->usesInbox() || ! $access->can('leads_view') || ! $access->can('inbox_view')) {
            return null;
        }

        $counts = $this->inbox->counts($business, $access);

        return [
            'all'        => (int) ($counts['all'] ?? 0),
            'review'     => (int) ($counts['review'] ?? 0),
            'unverified' => (int) ($counts['unverified'] ?? 0),
        ];
    }

    /** @return array{mine_pending: ?int, reports_unsent: ?int}|null */
    private function inquiries(Business $business, CrmAccess $access): ?array
    {
        $today = $this->allocations->today($business);

        $minePending = $access->userId !== null
            ? (int) ($this->inquiryStatus->forUser($business, $access->userId, $today)['counts']['pending'] ?? 0)
            : null;

        return [
            'mine_pending'   => $minePending,
            'reports_unsent' => $this->unsentReports($business, $access, $today),
        ];
    }

    /** Daily reports of the user's departments still waiting to be sent; null if they manage none. */
    private function unsentReports(Business $business, CrmAccess $access, string $today): ?int
    {
        if ($access->isReadOnly()) {
            return null;
        }
        $all = $access->worksAllLeads();
        if (! $all && $access->headDepartmentIds === []) {
            return null;
        }

        return InquiryReport::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [InquiryReport::STATUS_DRAFT, InquiryReport::STATUS_FAILED])
            ->whereDate('report_date', '>=', Carbon::parse($today)->subDays(13)->toDateString())
            ->whereDate('report_date', '<=', $today)
            ->when(! $all, fn ($q) => $q->whereIn('department_id', $access->headDepartmentIds))
            ->count();
    }
}
