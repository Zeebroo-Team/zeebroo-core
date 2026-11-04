<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Events\QuotationDecided;
use Modules\CRM\Events\QuotationSubmitted;
use Modules\CRM\Models\DepartmentSetting;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Policies\LeadPolicy;
use Modules\HRManagement\Models\Employee;
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
 * Every quotation needs a head's approval. On submit the staff tick which heads to email
 * (approvers()); each gets signed approve / reject links (QuotationApprovalMailer), so they can
 * decide without signing in — the first decision wins.
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
            QuotationSubmitted::dispatch($lead, $quotation, $userId, $this->discountSummary($lead, $quotation));

            return $quotation;
        });
    }

    public function approve(Lead $lead, Quotation $quotation, int $userId, ?string $comment = null): Quotation
    {
        $this->requireStatus($quotation, [Quotation::STATUS_PENDING_APPROVAL], 'Only a quotation waiting for approval can be approved.');

        DB::transaction(function () use ($lead, $quotation, $userId, $comment) {
            $quotation->update([
                'status'           => Quotation::STATUS_APPROVED,
                'approved_by'      => $userId,
                'approved_at'      => now(),
                'approval_comment' => $this->clean($comment),
            ]);
            $this->record($quotation, QuotationApproval::ACTION_APPROVED, $userId, $comment);
            QuotationDecided::dispatch($lead, $quotation, QuotationApproval::ACTION_APPROVED, $userId, $this->clean($comment));
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
            ->with(['lead.stage', 'lead.project', 'lead.department.headEmployee.user:id,name', 'lead.department.coHeadEmployee.user:id,name', 'lead.division.headUser:id,name', 'customer', 'submittedBy:id,name'])
            ->orderBy('updated_at')
            ->get()
            ->filter(fn (Quotation $q) => $q->lead !== null && $this->policy->canApproveQuotation($access, $q->lead))
            ->values();
    }

    /**
     * Who sent a pending quotation and who it waits on: the submitter with their HR department
     * and job title, and the lead's division / department head (null head → an admin decides),
     * plus the note left when submitting.
     *
     * @return array{from: ?array, to: array, note: ?string}
     */
    public function routing(Quotation $quotation): array
    {
        $lead      = $quotation->lead;
        $submitter = $quotation->submittedBy;

        $employee = $submitter === null ? null : Employee::query()
            ->where('business_id', $quotation->business_id)
            ->where('user_id', $submitter->id)
            ->with(['department:id,name', 'jobTitle:id,name'])
            ->first();

        $department = $lead?->department;
        $division   = $lead?->division;
        $head       = $division?->headUser
            ?? $department?->headEmployee?->user
            ?? $department?->coHeadEmployee?->user;

        $note = QuotationApproval::query()
            ->where('quotation_id', $quotation->id)
            ->where('action', QuotationApproval::ACTION_SUBMITTED)
            ->latest('id')
            ->value('comment');

        return [
            'from' => $submitter ? [
                'id'         => (int) $submitter->id,
                'name'       => $submitter->name,
                'department' => $employee?->department?->name,
                'job_title'  => $employee?->jobTitle?->name,
            ] : null,
            'to' => [
                'id'         => $head ? (int) $head->id : null,
                'name'       => $head?->name,
                'role'       => $head ? ($division?->headUser ? 'Division head' : 'Department head') : 'Admin',
                'department' => $department?->name,
                'division'   => $division?->name,
            ],
            'note' => $note,
        ];
    }

    /**
     * The overall discount as a % of the subtotal, against the department's approval limit
     * (crm_department_settings.discount_approval_limit_percent) — shown to the head so a large
     * discount stands out. Every version is approved by the head either way.
     *
     * @return array{percent: float, limit: ?float, over_limit: bool}
     */
    public function discountSummary(Lead $lead, Quotation $quotation): array
    {
        $percent = $quotation->discountPercent();
        $limit   = $this->discountLimit($lead);

        return ['percent' => $percent, 'limit' => $limit, 'over_limit' => $limit !== null && $percent > $limit];
    }

    /** The lead's department discount approval limit (%), or null when none is set. */
    public function discountLimit(Lead $lead): ?float
    {
        $limit = $lead->department_id === null ? null : DepartmentSetting::query()
            ->where('business_id', $lead->business_id)
            ->where('department_id', $lead->department_id)
            ->value('discount_approval_limit_percent');

        return $limit !== null ? (float) $limit : null;
    }

    /**
     * Who a submitted quotation can be emailed to for approval: the lead's division head (when
     * the lead sits in a division) and the department head / co-head — each a Zeebroo user with
     * an email, once. Any one of them approving is enough.
     *
     * @return list<array{id: int, name: string, email: string, role: string}>
     */
    public function approvers(Lead $lead): array
    {
        $lead->loadMissing(['division.headUser', 'department.headEmployee.user', 'department.coHeadEmployee.user']);
        $division = $lead->division !== null && (int) $lead->division->department_id === (int) $lead->department_id ? $lead->division : null;

        $list = [];
        foreach ([
            [$division?->headUser, 'Division head'],
            [$lead->department?->headEmployee?->user, 'Department head'],
            [$lead->department?->coHeadEmployee?->user, 'Department co-head'],
        ] as [$user, $role]) {
            if ($user !== null && $user->email && ! isset($list[$user->id])) {
                $list[$user->id] = ['id' => (int) $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role];
            }
        }

        return array_values($list);
    }

    /**
     * The approvers ticked when submitting (null = all of them). With heads on file at least
     * one must be picked; with none the quotation still goes to an admin in the app.
     *
     * @param  int[]|null  $ids
     * @return list<array{id: int, name: string, email: string, role: string}>
     */
    public function pickApprovers(Lead $lead, ?array $ids): array
    {
        $all = $this->approvers($lead);
        if ($ids === null) {
            return $all;
        }

        $ids    = array_map('intval', $ids);
        $picked = array_values(array_filter($all, fn (array $a) => in_array($a['id'], $ids, true)));
        if ($all !== [] && $picked === []) {
            throw ValidationException::withMessages(['approver_ids' => 'Pick at least one head to send the approval request to.']);
        }

        return $picked;
    }

    /**
     * Whether an emailed approval link (QuotationApprovalMailer) can still be used: null when the
     * head may decide now, otherwise what happened instead — the first decision wins, and a
     * quotation submitted again needs the newest email's link.
     */
    public function linkBlocker(Quotation $quotation, int $approverId, int $submissionId): ?string
    {
        $lead = $quotation->lead;
        if ($lead === null || ! $quotation->isLeadQuotation()) {
            return 'This quotation is no longer available.';
        }

        $latest = QuotationApproval::query()
            ->where('quotation_id', $quotation->id)
            ->where('action', QuotationApproval::ACTION_SUBMITTED)
            ->max('id');
        if ((int) $latest !== $submissionId) {
            return 'This quotation was changed and submitted again — use the link in the newest approval email.';
        }

        if ($quotation->status !== Quotation::STATUS_PENDING_APPROVAL) {
            $decision = QuotationApproval::query()
                ->where('quotation_id', $quotation->id)
                ->where('id', '>', $submissionId)
                ->whereIn('action', [QuotationApproval::ACTION_APPROVED, QuotationApproval::ACTION_RETURNED, QuotationApproval::ACTION_REJECTED])
                ->with('byUser:id,name')
                ->oldest('id')
                ->first();

            return $decision === null
                ? 'This quotation is no longer waiting for approval.'
                : 'Already ' . match ($decision->action) {
                    QuotationApproval::ACTION_APPROVED => 'approved',
                    QuotationApproval::ACTION_RETURNED => 'returned for changes',
                    default                            => 'rejected',
                } . ($decision->byUser ? ' by ' . $decision->byUser->name : '') . ' on ' . $decision->created_at?->format('M j, Y g:i A') . '.';
        }

        if (! in_array($approverId, array_column($this->approvers($lead), 'id'), true)) {
            return 'You are no longer an approver for this quotation.';
        }

        return null;
    }

    /** A decision made from the emailed link, as that head: approve, or reject with a reason. */
    public function decideByLink(Quotation $quotation, int $approverId, int $submissionId, string $decision, ?string $comment): Quotation
    {
        return DB::transaction(function () use ($quotation, $approverId, $submissionId, $decision, $comment) {
            // One decision only: a second head clicking at the same moment sees the first one's
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            if (($blocker = $this->linkBlocker($quotation, $approverId, $submissionId)) !== null) {
                throw ValidationException::withMessages(['decision' => $blocker]);
            }

            return $decision === 'approve'
                ? $this->approve($quotation->lead, $quotation, $approverId, $comment)
                : $this->reject($quotation->lead, $quotation, $approverId, (string) $comment);
        });
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
            QuotationDecided::dispatch($lead, $quotation, $action, $userId, $this->clean($comment));

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
