<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Events\QuotationRevisionDecided;
use Modules\CRM\Events\QuotationRevisionRequested;
use Modules\CRM\Models\Lead;
use Modules\CRM\Policies\LeadPolicy;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationRevisionRequest;
use Modules\Sales\Services\QuotationService;

/**
 * Revision control of lead quotations. Once a quotation leaves draft (submitted, approved,
 * sent, rejected …) it cannot be edited; to change it the staff send a revision request with
 * a reason, and the department / division head (or an admin) decides:
 *
 *   request ──▶ pending ──approve──▶ approved: a new draft version is made (QT-0007-v2),
 *                       │                       the old one is superseded (kept read-only)
 *                       ├─reject───▶ rejected   (the quotation stays as it is)
 *                       └─cancel───▶ cancelled  (by the requester or a head)
 *
 * A head asking is approved straight away. Each request keeps who asked, who decided, the
 * version it produced and who made the changes in it (markRevised, on each save of that
 * version) — with QuotationVersionService's diff that is the full revision history.
 *
 * Who may do which step is LeadPolicy::canQuote / canApproveQuotation, checked by the caller.
 */
class QuotationRevisionService
{
    public function __construct(
        private readonly QuotationService $quotations,
        private readonly LeadQuotationService $leadQuotations,
        private readonly LeadPolicy $policy,
    ) {}

    /** Why a revision cannot be asked for now, or null when it can. */
    public function blocker(Quotation $quotation): ?string
    {
        if (! $quotation->isLeadQuotation()) {
            return 'Only a quotation made from a lead goes through revision requests.';
        }
        if (($blocker = $this->quotations->reviseBlocker($quotation)) !== null) {
            return $blocker;
        }
        if ($this->pendingFor($quotation) !== null) {
            return 'A revision of this quotation is already waiting for the head\'s decision.';
        }

        return null;
    }

    public function pendingFor(Quotation $quotation): ?QuotationRevisionRequest
    {
        return QuotationRevisionRequest::query()
            ->where('quotation_id', $quotation->id)
            ->where('status', QuotationRevisionRequest::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    /**
     * Ask for a revision. With $approveNow (the asker may approve it themselves) the new version
     * is made at once and returned; otherwise the pending request is returned.
     */
    public function request(Lead $lead, Quotation $quotation, int $userId, string $reason, bool $approveNow = false): QuotationRevisionRequest|Quotation
    {
        $reason = $this->clean($reason);
        if ($reason === null) {
            throw ValidationException::withMessages(['reason' => 'Say what needs to change and why.']);
        }

        return DB::transaction(function () use ($lead, $quotation, $userId, $reason, $approveNow) {
            // One request at a time: a second click (or a colleague) sees the first one
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            if (($blocker = $this->blocker($quotation)) !== null) {
                throw ValidationException::withMessages(['quotation' => $blocker]);
            }

            $request = QuotationRevisionRequest::create([
                'business_id'  => $quotation->business_id,
                'quotation_id' => $quotation->id,
                'version'      => (int) $quotation->version,
                'status'       => QuotationRevisionRequest::STATUS_PENDING,
                'reason'       => $reason,
                'requested_by' => $userId,
            ]);

            if ($approveNow) {
                return $this->approveRequest($lead, $quotation, $request, $userId, null, notify: false);
            }

            QuotationRevisionRequested::dispatch($lead, $request, $userId);

            return $request;
        });
    }

    /** The head approves: the new draft version is made and returned. */
    public function approve(Lead $lead, QuotationRevisionRequest $request, int $userId, ?string $comment = null): Quotation
    {
        return DB::transaction(function () use ($lead, $request, $userId, $comment) {
            $request   = $this->lockPending($request);
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($request->quotation_id);

            return $this->approveRequest($lead, $quotation, $request, $userId, $comment);
        });
    }

    public function reject(Lead $lead, QuotationRevisionRequest $request, int $userId, string $comment): QuotationRevisionRequest
    {
        if ($this->clean($comment) === null) {
            throw ValidationException::withMessages(['comment' => 'Say why the revision is rejected.']);
        }

        return DB::transaction(function () use ($lead, $request, $userId, $comment) {
            $request = $this->lockPending($request);
            $request->update([
                'status'           => QuotationRevisionRequest::STATUS_REJECTED,
                'decided_by'       => $userId,
                'decided_at'       => now(),
                'decision_comment' => $this->clean($comment),
            ]);
            QuotationRevisionDecided::dispatch($lead, $request, $userId);

            return $request;
        });
    }

    /** Withdrawn by whoever asked, or dropped by a head. */
    public function cancel(QuotationRevisionRequest $request, int $userId): QuotationRevisionRequest
    {
        return DB::transaction(function () use ($request, $userId) {
            $request = $this->lockPending($request);
            $request->update([
                'status'     => QuotationRevisionRequest::STATUS_CANCELLED,
                'decided_by' => $userId,
                'decided_at' => now(),
            ]);

            return $request;
        });
    }

    /**
     * Who made the changes in a version that came from a revision request: the last one to
     * save it. Called on each save of a lead quotation; any other quotation is left alone.
     */
    public static function markRevised(Quotation $quotation, ?int $userId): void
    {
        if ($userId === null || $quotation->version <= 1) {
            return;
        }

        QuotationRevisionRequest::query()
            ->where('revision_id', $quotation->id)
            ->update(['revised_by' => $userId, 'revised_at' => now()]);
    }

    public function requestForQuotation(Quotation $quotation, int $requestId): QuotationRevisionRequest
    {
        return QuotationRevisionRequest::query()
            ->where('quotation_id', $quotation->id)
            ->findOrFail($requestId);
    }

    /**
     * Revision requests waiting for this user's decision: pending ones on leads they see and
     * may approve, oldest first.
     *
     * @return Collection<int, QuotationRevisionRequest>
     */
    public function inbox(Business $business, CrmAccess $access): Collection
    {
        $leadIds = Lead::query()->where('crm_leads.business_id', $business->id)->visibleWith($access)->select('crm_leads.id');

        return QuotationRevisionRequest::query()
            ->where('business_id', $business->id)
            ->where('status', QuotationRevisionRequest::STATUS_PENDING)
            ->whereHas('quotation', fn ($q) => $q->whereIn('lead_id', $leadIds))
            ->with(['quotation.lead.stage', 'quotation.lead.project', 'quotation.customer', 'requestedBy:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (QuotationRevisionRequest $r) => $r->quotation?->lead !== null && $this->policy->canApproveQuotation($access, $r->quotation->lead))
            ->values();
    }

    private function approveRequest(Lead $lead, Quotation $quotation, QuotationRevisionRequest $request, int $userId, ?string $comment, bool $notify = true): Quotation
    {
        if (($blocker = $this->quotations->reviseBlocker($quotation)) !== null) {
            throw ValidationException::withMessages(['quotation' => $blocker]);
        }

        $revision = $this->leadQuotations->revise($lead, $quotation, $userId);
        $request->update([
            'status'           => QuotationRevisionRequest::STATUS_APPROVED,
            'decided_by'       => $userId,
            'decided_at'       => now(),
            'decision_comment' => $this->clean($comment),
            'revision_id'      => $revision->id,
        ]);

        if ($notify) {
            QuotationRevisionDecided::dispatch($lead, $request, $userId);
        }

        return $revision;
    }

    private function lockPending(QuotationRevisionRequest $request): QuotationRevisionRequest
    {
        $request = QuotationRevisionRequest::query()->lockForUpdate()->findOrFail($request->id);
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['request' => 'This revision request was already ' . $request->status . '.']);
        }

        return $request;
    }

    private function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, 2000);
    }
}
