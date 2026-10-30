<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\DepartmentSetting;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Policies\LeadPolicy;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationApproval;
use Modules\Sales\Services\QuotationPrintService;

/**
 * A lead quotation's approval: staff on the lead submit it, the department (or division) head
 * or an admin approves, returns it with comments or rejects it. Each step is a
 * quotation_approvals row and moves the lead:
 *
 *   draft / returned ──submit──▶ pending_approval      lead → Pending Approval
 *   pending_approval ──approve─▶ approved (+ PDF)       lead stays Pending Approval until sent
 *   pending_approval ──return──▶ returned               lead → Quotation Preparing
 *   pending_approval ──reject──▶ rejected               lead → Quotation Preparing
 *
 * Who may do which step is LeadPolicy::canQuote / canApprove, checked by the caller.
 */
class QuotationApprovalService
{
    public function __construct(
        private readonly LeadQuotationService $leadQuotations,
        private readonly QuotationPrintService $print,
        private readonly LeadPolicy $policy,
    ) {}

    public function submit(Lead $lead, Quotation $quotation, int $userId, ?string $comment = null): Quotation
    {
        $this->requireStatus($quotation, [Quotation::STATUS_DRAFT, Quotation::STATUS_RETURNED], 'Only a draft or returned quotation can be submitted.');
        if ($quotation->items()->count() === 0) {
            throw ValidationException::withMessages(['quotation' => 'Add at least one line before submitting.']);
        }

        return DB::transaction(function () use ($lead, $quotation, $userId, $comment) {
            $quotation->update([
                'status'           => Quotation::STATUS_PENDING_APPROVAL,
                'submitted_by'     => $userId,
                'approved_by'      => null,
                'approved_at'      => null,
                'approval_comment' => null,
            ]);
            $this->record($quotation, QuotationApproval::ACTION_SUBMITTED, $userId, $comment);
            $this->leadQuotations->moveLeadTo($lead, LeadStage::STATUS_PENDING_APPROVAL, $userId);

            return $quotation;
        });
    }

    public function approve(Lead $lead, Quotation $quotation, int $userId, ?string $comment = null): Quotation
    {
        $this->requireStatus($quotation, [Quotation::STATUS_PENDING_APPROVAL], 'Only a quotation waiting for approval can be approved.');

        DB::transaction(function () use ($quotation, $userId, $comment) {
            $quotation->update([
                'status'           => Quotation::STATUS_APPROVED,
                'approved_by'      => $userId,
                'approved_at'      => now(),
                'approval_comment' => $this->clean($comment),
            ]);
            $this->record($quotation, QuotationApproval::ACTION_APPROVED, $userId, $comment);
        });

        // The PDF of this approved version; if rendering fails it is made again when sending
        try {
            $this->print->generatePdf($quotation);
        } catch (\Throwable $e) {
            Log::warning('Quotation PDF could not be generated on approval: ' . $e->getMessage(), ['quotation_id' => $quotation->id]);
        }

        return $quotation->fresh();
    }

    public function returnForChanges(Lead $lead, Quotation $quotation, int $userId, string $comment): Quotation
    {
        return $this->decline($lead, $quotation, $userId, $comment, Quotation::STATUS_RETURNED, QuotationApproval::ACTION_RETURNED);
    }

    public function reject(Lead $lead, Quotation $quotation, int $userId, string $comment): Quotation
    {
        return $this->decline($lead, $quotation, $userId, $comment, Quotation::STATUS_REJECTED, QuotationApproval::ACTION_REJECTED);
    }

    /**
     * Quotations waiting for this user's approval: pending ones on leads they see and may approve.
     *
     * @return Collection<int, Quotation>
     */
    public function inbox(Business $business, CrmAccess $access): Collection
    {
        $leadIds = Lead::query()->where('crm_leads.business_id', $business->id)->visibleWith($access)->select('crm_leads.id');

        return Quotation::query()
            ->where('business_id', $business->id)
            ->where('status', Quotation::STATUS_PENDING_APPROVAL)
            ->whereIn('lead_id', $leadIds)
            ->with(['lead.stage', 'lead.project', 'customer', 'submittedBy:id,name'])
            ->orderBy('updated_at')
            ->get()
            ->filter(fn (Quotation $q) => $q->lead !== null && $this->policy->canApprove($access, $q->lead))
            ->values();
    }

    /**
     * The overall discount as a % of the subtotal, against the department's approval limit
     * (crm_department_settings.discount_approval_limit_percent), for the approver to see.
     *
     * @return array{percent: float, limit: ?float, over_limit: bool}
     */
    public function discountSummary(Lead $lead, Quotation $quotation): array
    {
        $percent = (float) $quotation->subtotal > 0 ? round((float) $quotation->discount_amount / (float) $quotation->subtotal * 100, 2) : 0.0;
        $limit   = $lead->department_id === null ? null : DepartmentSetting::query()
            ->where('business_id', $lead->business_id)
            ->where('department_id', $lead->department_id)
            ->value('discount_approval_limit_percent');
        $limit = $limit !== null ? (float) $limit : null;

        return ['percent' => $percent, 'limit' => $limit, 'over_limit' => $limit !== null && $percent > $limit];
    }

    private function decline(Lead $lead, Quotation $quotation, int $userId, string $comment, string $status, string $action): Quotation
    {
        $this->requireStatus($quotation, [Quotation::STATUS_PENDING_APPROVAL], 'Only a quotation waiting for approval can be ' . ($status === Quotation::STATUS_RETURNED ? 'returned' : 'rejected') . '.');
        if ($this->clean($comment) === null) {
            throw ValidationException::withMessages(['comment' => 'Say what needs to change (or why it is rejected).']);
        }

        return DB::transaction(function () use ($lead, $quotation, $userId, $comment, $status, $action) {
            $quotation->update([
                'status'           => $status,
                'approved_by'      => null,
                'approved_at'      => null,
                'approval_comment' => $this->clean($comment),
            ]);
            $this->record($quotation, $action, $userId, $comment);
            $this->leadQuotations->moveLeadTo($lead, LeadStage::STATUS_QUOTATION_PREPARING, $userId, $this->clean($comment));

            return $quotation;
        });
    }

    private function record(Quotation $quotation, string $action, int $userId, ?string $comment): QuotationApproval
    {
        return QuotationApproval::create([
            'quotation_id' => $quotation->id,
            'action'       => $action,
            'version'      => (int) $quotation->version,
            'by_user_id'   => $userId,
            'comment'      => $this->clean($comment),
        ]);
    }

    private function requireStatus(Quotation $quotation, array $statuses, string $message): void
    {
        if (! $quotation->isLeadQuotation() || ! in_array($quotation->status, $statuses, true)) {
            throw ValidationException::withMessages(['quotation' => $message]);
        }
    }

    private function clean(?string $comment): ?string
    {
        $comment = trim((string) $comment);

        return $comment === '' ? null : mb_substr($comment, 0, 2000);
    }
}
