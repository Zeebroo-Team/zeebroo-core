<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Lead;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\LeadQuotationService;
use Modules\CRM\Services\QuotationApprovalService;
use Modules\CRM\Services\QuotationDispatch\QuotationDispatcher;
use Modules\CRM\Services\QuotationNegotiationService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationApproval;
use Modules\Sales\Models\QuotationCustomerResponse;
use Modules\Sales\Models\QuotationDispatch;
use Modules\Sales\Models\QuotationItem;
use Modules\Sales\Services\QuotationPrintService;
use Modules\Sales\Services\QuotationService;
use Modules\Sales\Services\QuotationVersionService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Quotations inside the desktop lead window — built from inventory with a stock check — and
 * their approval: staff on the lead submit, the department head approves / returns / rejects,
 * then the approved PDF is emailed to the customer. The quotation is the Sales module's
 * (also reachable at /quotations/{id}); this controller adds the CRM rules (LeadPolicy).
 */
class PosCrmLeadQuotationApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private ?CrmAccess $crmAccess = null;

    public function __construct(
        private readonly CrmPermissionService $crmPermissions,
        private readonly LeadPolicy $policy,
        private readonly LeadQuotationService $leadQuotations,
        private readonly QuotationService $quotations,
        private readonly QuotationApprovalService $approvals,
        private readonly QuotationDispatcher $dispatcher,
        private readonly QuotationPrintService $print,
        private readonly QuotationNegotiationService $negotiation,
        private readonly QuotationVersionService $versions,
    ) {}

    /** GET crm/leads/{lead}/quotations — the lead's quotations, newest first. */
    public function index(Request $request, int $leadId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);

        return response()->json([
            'data' => $this->leadQuotations->forLead($lead)->map(fn (Quotation $q) => $this->presentListItem($q))->values(),
            'meta' => [
                'can_create'  => $this->policy->canQuote($access, $lead),
                'can_approve' => $this->policy->canApprove($access, $lead),
                'stock_mode'  => $this->leadQuotations->stockCheck($business, [])['mode'],
                'lead'        => $this->presentLead($lead),
            ],
        ]);
    }

    /** POST crm/leads/{lead}/quotations — header + items[]; the lead moves to Quotation Preparing. */
    public function store(Request $request, int $leadId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canQuote($access, $lead));

        $data      = $this->validated($request, $business);
        $quotation = $this->leadQuotations->create($lead, $data, $data['items'], $request->user()?->id);

        return response()->json([
            'message' => 'Quotation ' . $quotation->quote_number . ' created.',
            'data'    => $this->presentDetail($business, $access, $lead, $quotation),
        ], 201);
    }

    /** POST crm/leads/{lead}/quotations/stock-check — { items[], branch_id? }: stock of each product line. */
    public function stockCheck(Request $request, int $leadId): JsonResponse
    {
        [$business] = $this->context($request, $leadId);

        $data = $request->validate([
            'branch_id'          => ['nullable', 'integer'],
            'items'              => ['present', 'array', 'max:200'],
            'items.*.item_type'  => ['nullable', 'string', 'in:product,service,custom'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.quantity'   => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $this->leadQuotations->stockCheck($business, $data['items'], $data['branch_id'] ?? null)]);
    }

    /** GET crm/leads/{lead}/quotations/{quotation} */
    public function show(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        return response()->json(['data' => $this->presentDetail($business, $access, $lead, $quotation)]);
    }

    /** PUT crm/leads/{lead}/quotations/{quotation} — while draft or returned. */
    public function update(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canQuote($access, $lead));
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        $data      = $this->validated($request, $business);
        $quotation = $this->leadQuotations->update($lead, $quotation, $data, $data['items']);

        return response()->json(['message' => 'Quotation saved.', 'data' => $this->presentDetail($business, $access, $lead, $quotation)]);
    }

    /**
     * POST …/{quotation}/responses — { response: accept|reject|discount_request, requested_discount?,
     * notes?, lost_reason_id?, lost_reason? }: what the customer said about the sent version.
     */
    public function respond(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canQuote($access, $lead));
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        $data = $request->validate([
            'response'           => ['required', 'string', Rule::in(array_keys(QuotationCustomerResponse::RESPONSES))],
            'requested_discount' => ['nullable', 'numeric', 'gt:0', 'max:100', 'required_if:response,' . QuotationCustomerResponse::RESPONSE_DISCOUNT_REQUEST],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'lost_reason_id'     => ['nullable', 'integer'],
            'lost_reason'        => ['nullable', 'string', 'max:500'],
        ]);

        $row = $this->negotiation->record($lead, $quotation, $data, (int) $request->user()->id);

        return response()->json([
            'message' => $row->label() . '.',
            'data'    => $this->presentDetail($business, $access, $lead->fresh(), $quotation->fresh()),
        ]);
    }

    /** GET …/{quotation}/versions — every version of this quotation, oldest first, each with what changed from the one before. */
    public function versions(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [, , $lead] = $this->context($request, $leadId);
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        return response()->json(['data' => $this->presentVersions($quotation)]);
    }

    /** GET crm/customers/{customer}/quotations/{quotation}/versions — the same history, from the customer window. */
    public function customerVersions(Request $request, int $customerId, int $quotationId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessAllowed($this->access($request, $business)->can('contacts_view'));

        $quotation = Quotation::query()
            ->where('business_id', $business->id)
            ->where('customer_id', $customerId)
            ->findOrFail($quotationId);

        return response()->json(['data' => $this->presentVersions($quotation)]);
    }

    /** GET …/{quotation}/diff?against={id} — what changed from another version (default: the one it revises) to this one. */
    public function diff(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [, , $lead] = $this->context($request, $leadId);
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        $againstId = $request->integer('against') ?: $quotation->parent_quotation_id;
        if (! $againstId) {
            return response()->json(['message' => 'This is the first version — there is nothing to compare it with.'], 422);
        }
        $against = $this->versions->versions($quotation)->firstWhere('id', $againstId);
        if ($against === null) {
            abort(404);
        }

        return response()->json(['data' => $this->versions->diff($against, $quotation)]);
    }

    /** POST …/{quotation}/revise — a new draft version of an approved, sent or rejected quotation; the old one is superseded. */
    public function revise(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canQuote($access, $lead));
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        $revision = $this->leadQuotations->revise($lead, $quotation, $request->user()?->id);

        return response()->json([
            'message' => 'Version ' . $revision->version . ' (' . $revision->quote_number . ') created.',
            'data'    => $this->presentDetail($business, $access, $lead, $revision),
        ], 201);
    }

    /** POST …/{quotation}/submit — { comment? }: staff on the lead send it for approval. */
    public function submit(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canQuote($access, $lead));
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        $data      = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $quotation = $this->approvals->submit($lead, $quotation, (int) $request->user()->id, $data['comment'] ?? null);

        return response()->json(['message' => 'Submitted for approval.', 'data' => $this->presentDetail($business, $access, $lead->fresh(), $quotation)]);
    }

    /** POST …/{quotation}/approve — { comment? }: the department head or an admin. */
    public function approve(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        return $this->decide($request, $leadId, $quotationId, 'approve');
    }

    /** POST …/{quotation}/return — { comment }: back to the staff for changes. */
    public function returnForChanges(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        return $this->decide($request, $leadId, $quotationId, 'return');
    }

    /** POST …/{quotation}/reject — { comment } */
    public function reject(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        return $this->decide($request, $leadId, $quotationId, 'reject');
    }

    /** GET …/{quotation}/pdf — the quotation PDF (made on approval; made now if missing). */
    public function pdf(Request $request, int $leadId, int $quotationId): StreamedResponse
    {
        [, , $lead] = $this->context($request, $leadId);
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        // A draft is still being edited — rebuild it so the download matches its current lines
        $path = $quotation->status === Quotation::STATUS_DRAFT
            ? $this->print->generatePdf($quotation)
            : $this->print->pdfPath($quotation);

        return Storage::disk(QuotationPrintService::disk())->download($path, $quotation->quote_number . '.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * POST …/{quotation}/send — { channel?, to, cc[]?, subject, message }: queue the approved PDF to
     * the customer. A draft / returned version within the department's discount limit is sent
     * straight away, without the head's approval.
     */
    public function send(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canQuote($access, $lead));
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        $channel = (string) $request->input('channel', 'email');
        $data    = $request->validate([
            'channel' => ['nullable', 'string', Rule::in(array_column($this->dispatcher->channels(), 'key'))],
            'to'      => $this->dispatcher->channel($channel)->recipientRules(),
            'cc'      => ['nullable', 'array', 'max:10'],
            'cc.*'    => ['email', 'max:191'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        if (in_array($quotation->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_RETURNED], true)) {
            $quotation = $this->approvals->releaseWithinLimit($lead, $quotation, (int) $request->user()->id);
        }
        $dispatch = $this->dispatcher->queue($quotation, $channel, $data, $request->user()?->id);

        return response()->json([
            'message' => $dispatch->status === QuotationDispatch::STATUS_FAILED
                ? 'Sending failed: ' . $dispatch->error
                : ($dispatch->status === QuotationDispatch::STATUS_SENT ? 'Quotation sent to ' . $dispatch->to . '.' : 'Quotation queued for ' . $dispatch->to . '.'),
            'data'    => $this->presentDetail($business, $access, $lead->fresh(), $quotation->fresh()),
        ], $dispatch->status === QuotationDispatch::STATUS_FAILED ? 422 : 200);
    }

    /** GET crm/quotation-approvals — quotations waiting for this user's approval. */
    public function inbox(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->access($request, $business);

        $rows = $this->approvals->inbox($business, $access)->map(fn (Quotation $q) => $this->presentListItem($q) + [
            'lead'     => $this->presentLead($q->lead),
            'discount' => $this->approvals->discountSummary($q->lead, $q),
            'routing'  => $this->approvals->routing($q),
            'subtotal' => round((float) $q->subtotal, 2),
            'items_count' => $q->items()->count(),
        ])->values();

        return response()->json(['data' => $rows, 'meta' => ['can_approve' => $access->unrestricted || $access->isHead()]]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function decide(Request $request, int $leadId, int $quotationId, string $decision): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canApprove($access, $lead));
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        $data   = $request->validate(['comment' => [$decision === 'approve' ? 'nullable' : 'required', 'string', 'max:2000']]);
        $userId = (int) $request->user()->id;

        [$quotation, $message] = match ($decision) {
            'approve' => [$this->approvals->approve($lead, $quotation, $userId, $data['comment'] ?? null), 'Quotation approved.'],
            'return'  => [$this->approvals->returnForChanges($lead, $quotation, $userId, $data['comment']), 'Quotation returned for changes.'],
            'reject'  => [$this->approvals->reject($lead, $quotation, $userId, $data['comment']), 'Quotation rejected.'],
        };

        return response()->json(['message' => $message, 'data' => $this->presentDetail($business, $access, $lead->fresh(), $quotation)]);
    }

    /** @return array<string, mixed> header fields + items, validated */
    private function validated(Request $request, Business $business): array
    {
        return $request->validate([
            'branch_id'               => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'customer_id'             => ['nullable', 'integer', Rule::exists('pos_customers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'reference'               => ['nullable', 'string', 'max:120'],
            'quote_date'              => ['required', 'date'],
            'expiry_date'             => ['nullable', 'date', 'after_or_equal:quote_date'],
            'notes'                   => ['nullable', 'string', 'max:5000'],
            'discount_amount'         => ['nullable', 'numeric', 'min:0'],
            'tax_amount'              => ['nullable', 'numeric', 'min:0'],
            'items'                   => ['required', 'array', 'min:1', 'max:200'],
            'items.*.item_type'       => ['nullable', 'string', 'in:product,service,custom'],
            'items.*.product_id'      => ['nullable', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'items.*.service_item_id' => ['nullable', 'integer'],
            'items.*.description'     => ['nullable', 'string', 'max:255'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function presentListItem(Quotation $q): array
    {
        return [
            'id'               => (int) $q->id,
            'lead_id'          => $q->lead_id,
            'quote_number'     => $q->quote_number,
            'base_number'      => $this->quotations->baseNumber($q),
            'version'          => (int) $q->version,
            'parent_quotation_id' => $q->parent_quotation_id,
            'is_superseded'    => $q->isSuperseded(),
            'status'           => $q->status,
            'status_label'     => $q->statusLabel(),
            'status_color'     => $q->statusColor(),
            'customer_name'    => $q->customer?->name,
            'quote_date'       => $q->quote_date?->toDateString(),
            'expiry_date'      => $q->expiry_date?->toDateString(),
            'total'            => round((float) $q->total, 2),
            'is_editable'      => $q->isEditable(),
            'submitted_by'     => $q->submittedBy ? ['id' => $q->submittedBy->id, 'name' => $q->submittedBy->name] : null,
            'approved_by'      => $q->approvedBy ? ['id' => $q->approvedBy->id, 'name' => $q->approvedBy->name] : null,
            'approved_at'      => $q->approved_at?->toIso8601String(),
            'approval_comment' => $q->approval_comment,
            'sent_at'          => $q->sent_at?->toIso8601String(),
            'sent_via'         => $q->sent_via,
            'has_pdf'          => $q->pdf_path !== null,
            'updated_at'       => $q->updated_at?->toIso8601String(),
        ];
    }

    private function presentDetail(Business $business, CrmAccess $access, Lead $lead, Quotation $q): array
    {
        $q->load(['customer', 'items.product:id,name,sku', 'approvals.byUser:id,name', 'dispatches.sentBy:id,name', 'customerResponses.recordedBy:id,name', 'submittedBy:id,name', 'approvedBy:id,name']);

        $items = $q->items->map(fn (QuotationItem $i) => [
            'id'              => (int) $i->id,
            'item_type'       => $i->product_id ? 'product' : ($i->service_item_id ? 'service' : 'custom'),
            'product_id'      => $i->product_id,
            'service_item_id' => $i->service_item_id,
            'product_name'    => $i->product?->name,
            'sku'             => $i->product?->sku,
            'description'     => $i->description,
            'quantity'        => round((float) $i->quantity, 3),
            'unit_price'      => round((float) $i->unit_price, 2),
            'discount_amount' => round((float) $i->discount_amount, 2),
            'tax_rate'        => round((float) $i->tax_rate, 3),
            'tax_amount'      => round((float) $i->tax_amount, 2),
            'line_total'      => round((float) $i->line_total, 2),
        ])->values()->all();

        $canQuote   = $this->policy->canQuote($access, $lead);
        $canApprove = $this->policy->canApprove($access, $lead);
        $pending    = $q->status === Quotation::STATUS_PENDING_APPROVAL;
        $discount   = $this->approvals->discountSummary($lead, $q);
        $preparing  = in_array($q->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_RETURNED], true);
        // A draft within the department's discount limit goes out without the head (releaseWithinLimit)
        $sendDirect = $canQuote && $preparing && ! $discount['requires_approval'] && count($items) > 0;
        $canSend    = $sendDirect || ($canQuote && in_array($q->status, [Quotation::STATUS_APPROVED, Quotation::STATUS_SENT], true));
        $newer      = $q->isSuperseded()
            ? Quotation::query()->where('business_id', $q->business_id)->where('parent_quotation_id', $q->id)->first(['id', 'quote_number', 'version'])
            : null;

        return $this->presentListItem($q) + [
            'reference'     => $q->reference,
            'notes'         => $q->notes,
            'customer_id'   => $q->customer_id,
            'branch_id'     => $q->branch_id,
            'subtotal'      => round((float) $q->subtotal, 2),
            'discount_amount' => round((float) $q->discount_amount, 2),
            'tax_amount'    => round((float) $q->tax_amount, 2),
            'header'        => $this->quotations->headerAdjustments($q),
            'items'         => $items,
            'stock'         => $this->leadQuotations->stockCheck($business, $items, $q->branch_id),
            'discount'      => $discount,
            'superseded_by' => $newer ? ['id' => (int) $newer->id, 'quote_number' => $newer->quote_number, 'version' => (int) $newer->version] : null,
            'responses'     => $q->customerResponses->map(fn (QuotationCustomerResponse $r) => $this->presentResponse($r))->values(),
            'approvals'     => $q->approvals->map(fn (QuotationApproval $a) => [
                'id'      => (int) $a->id,
                'action'  => $a->action,
                'label'   => $a->label(),
                'version' => (int) $a->version,
                'comment' => $a->comment,
                'by'      => $a->byUser ? ['id' => $a->byUser->id, 'name' => $a->byUser->name] : null,
                'at'      => $a->created_at?->toIso8601String(),
            ])->values(),
            'dispatches'    => $q->dispatches->map(fn (QuotationDispatch $d) => [
                'id'      => (int) $d->id,
                'channel' => $d->channel,
                'to'      => $d->to,
                'cc'      => $d->cc ?? [],
                'subject' => $d->subject,
                'status'  => $d->status,
                'error'   => $d->error,
                'by'      => $d->sentBy ? ['id' => $d->sentBy->id, 'name' => $d->sentBy->name] : null,
                'sent_at' => $d->sent_at?->toIso8601String(),
                'at'      => $d->created_at?->toIso8601String(),
            ])->values(),
            'lead'          => $this->presentLead($lead),
            'can'           => [
                'edit'    => $canQuote && $q->isEditable(),
                'submit'  => $canQuote && in_array($q->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_RETURNED], true),
                'approve' => $canApprove && $pending,
                'return'  => $canApprove && $pending,
                'reject'  => $canApprove && $pending,
                'revise'  => $canQuote && ! $q->isEditable() && ! in_array($q->status, [Quotation::STATUS_PENDING_APPROVAL, Quotation::STATUS_ACCEPTED, Quotation::STATUS_SUPERSEDED], true),
                'send'    => $canSend,
                // Sending this draft skips the approval (its discount is within the limit)
                'send_direct' => $sendDirect,
                'respond' => $canQuote && $q->status === Quotation::STATUS_SENT,
            ],
            'channels'      => $canSend ? $this->dispatcher->channels() : [],
            'send_defaults' => $canSend ? $this->dispatcher->defaults($lead, $q) : null,
        ];
    }

    private function presentResponse(QuotationCustomerResponse $r): array
    {
        return [
            'id'                 => (int) $r->id,
            'response'           => $r->response,
            'label'              => $r->label(),
            'version'            => (int) $r->version,
            'requested_discount' => $r->requested_discount !== null ? (float) $r->requested_discount : null,
            'notes'              => $r->notes,
            'by'                 => $r->recordedBy ? ['id' => $r->recordedBy->id, 'name' => $r->recordedBy->name] : null,
            'at'                 => $r->created_at?->toIso8601String(),
        ];
    }

    /** Every version, oldest first, with its discount, the customer's responses and the diff from the version before. */
    private function presentVersions(Quotation $quotation): array
    {
        $versions = $this->versions->versions($quotation);
        $previous = null;
        $rows     = [];
        foreach ($versions as $v) {
            $rows[] = $this->presentListItem($v) + [
                'subtotal'         => round((float) $v->subtotal, 2),
                'discount_amount'  => round((float) $v->discount_amount, 2),
                'discount_percent' => $v->discountPercent(),
                'items_count'      => $v->items->count(),
                'is_current'       => (int) $v->id === (int) $quotation->id,
                'responses'        => $v->customerResponses->map(fn (QuotationCustomerResponse $r) => $this->presentResponse($r))->values(),
                'diff'             => $previous ? $this->versions->diff($previous, $v) : null,
            ];
            $previous = $v;
        }

        return $rows;
    }

    private function presentLead(?Lead $lead): ?array
    {
        if ($lead === null) {
            return null;
        }
        $lead->loadMissing(['stage', 'project']);

        return [
            'id'           => (int) $lead->id,
            'name'         => $lead->name,
            'company'      => $lead->company,
            'email'        => $lead->email,
            'project_id'   => (int) $lead->project_id,
            'project_name' => $lead->project?->name,
            'status_key'   => $lead->statusKey(),
            'stage_name'   => $lead->stageLabel(),
            'stage_color'  => $lead->stageColor(),
        ];
    }

    /** @return array{0: Business, 1: CrmAccess, 2: Lead} a lead of this business the user may see (404 otherwise) */
    private function context(Request $request, int $leadId): array
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->access($request, $business);
        if (! $access->can('leads_view')) {
            $this->abortUnlessAllowed(false);
        }

        $lead = Lead::where('business_id', $business->id)->findOrFail($leadId);
        if (! $this->policy->canView($access, $lead)) {
            abort(404);
        }

        return [$business, $access, $lead];
    }

    private function access(Request $request, Business $business): CrmAccess
    {
        return $this->crmAccess ??= $this->crmPermissions->accessFor($business, $request->user());
    }

    private function abortUnlessAllowed(bool $allowed): void
    {
        if (! $allowed) {
            throw new HttpResponseException(
                response()->json(['message' => 'You do not have permission to perform this action.'], 403)
            );
        }
    }
}
