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
use Modules\CRM\Services\CrmProfileService;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\CRM\Services\LeadQuotationService;
use Modules\CRM\Services\QuotationApprovalMailer;
use Modules\CRM\Services\QuotationApprovalService;
use Modules\CRM\Services\QuotationDispatch\QuotationDispatcher;
use Modules\CRM\Services\QuotationNegotiationService;
use Modules\CRM\Services\QuotationRevisionService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\CrmQuotationCatalogService;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationApproval;
use Modules\Sales\Models\QuotationCustomerResponse;
use Modules\Sales\Models\QuotationDispatch;
use Modules\Sales\Models\QuotationItem;
use Modules\Sales\Models\QuotationRevisionRequest;
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
        private readonly QuotationApprovalMailer $approvalMail,
        private readonly QuotationDispatcher $dispatcher,
        private readonly QuotationPrintService $print,
        private readonly QuotationNegotiationService $negotiation,
        private readonly QuotationVersionService $versions,
        private readonly LeadAssignmentService $assignments,
        private readonly QuotationRevisionService $revisions,
    ) {}

    /** GET crm/leads/{lead}/quotations — the lead's quotations, newest first. */
    public function index(Request $request, int $leadId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);

        return response()->json([
            'data' => $this->leadQuotations->forLead($lead)->map(fn (Quotation $q) => $this->presentListItem($q))->values(),
            'meta' => [
                'can_create'  => $this->policy->canQuote($access, $lead),
                'can_approve' => $this->policy->canApproveQuotation($access, $lead),
                // false → product prices are filled in from the catalog and locked
                'can_change_price' => $this->policy->canChangeQuotationPrice($access, $lead),
                'stock_mode'  => $this->leadQuotations->stockCheck($business, [])['mode'],
                // So the editor can warn live when the discount goes over it (null = no limit set)
                'discount_limit' => $this->approvals->discountLimit($lead),
                // …and when the total goes over the value limit (null = no limit set → always approval)
                'value_limit'    => $this->approvals->valueLimit($lead),
                'lead'        => $this->presentLead($lead),
                // The signed-in user's CRM profile — a new quotation's "Prepared by"
                'prepared_by' => $this->preparedByDefaults($business, $request),
            ],
        ]);
    }

    /** POST crm/leads/{lead}/quotations — header + items[]; the lead moves to Quotation Preparing. */
    public function store(Request $request, int $leadId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canQuote($access, $lead));

        $data      = $this->validated($request, $business);
        $items     = $this->priced($request, $business, $access, $lead, $data['items']);
        $quotation = $this->leadQuotations->create($lead, $data, $items, $request->user()?->id);

        return response()->json([
            'message' => 'Quotation ' . $quotation->quote_number . ' created.',
            'data'    => $this->presentDetail($business, $access, $lead, $quotation),
        ], 201);
    }

    /**
     * POST crm/leads/{lead}/quotations/stock-check — { items[], branch_id? }: stock of each
     * product line, plus "prices": each product's standard price (product id => price) for
     * automatic pricing.
     */
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

        $productIds = array_column(array_filter($data['items'], fn (array $i) => ($i['item_type'] ?? null) === 'product'), 'product_id');

        return response()->json(['data' => $this->leadQuotations->stockCheck($business, $data['items'], $data['branch_id'] ?? null) + [
            // JSON object even when empty, keyed by product id
            'prices' => (object) app(CrmQuotationCatalogService::class)->standardPrices($business, $productIds, $this->branchHeader($request)),
        ]]);
    }

    /** GET crm/leads/{lead}/quotations/customers?q= — saved customers for the editor's customer auto-suggest. */
    public function customers(Request $request, int $leadId): JsonResponse
    {
        [$business] = $this->context($request, $leadId);

        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);

        return response()->json(['data' => $this->leadQuotations->searchCustomers((int) $business->id, (string) ($data['q'] ?? ''))]);
    }

    /** GET crm/leads/{lead}/quotations/catalog/brands — brands for the editor's Brand → Model picker. */
    public function catalogBrands(Request $request, int $leadId, CrmQuotationCatalogService $catalog): JsonResponse
    {
        [$business] = $this->context($request, $leadId);

        return response()->json(['data' => $catalog->brands($business)]);
    }

    /** GET crm/leads/{lead}/quotations/catalog/models?brand_id= — the brand's products with warranty / delivery info. */
    public function catalogModels(Request $request, int $leadId, CrmQuotationCatalogService $catalog): JsonResponse
    {
        [$business] = $this->context($request, $leadId);

        $data = $request->validate(['brand_id' => ['required', 'integer', 'min:1']]);

        return response()->json([
            'data' => $catalog->models($business, (int) $data['brand_id'], $this->branchHeader($request)),
        ]);
    }

    /** GET crm/leads/{lead}/quotations/catalog/products/{product} — model no. / warranty / delivery for a line's note. */
    public function catalogProduct(Request $request, int $leadId, int $productId, CrmQuotationCatalogService $catalog): JsonResponse
    {
        [$business] = $this->context($request, $leadId);

        $info = $catalog->productInfo($business, $productId);
        abort_if($info === null, 404);

        return response()->json(['data' => $info]);
    }

    /** GET crm/leads/{lead}/quotations/{quotation} */
    public function show(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        return response()->json(['data' => $this->presentDetail($business, $access, $lead, $quotation)]);
    }

    /** PUT crm/leads/{lead}/quotations/{quotation} — while draft or returned; an issued one needs a revision request. */
    public function update(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canQuote($access, $lead));
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        $data      = $this->validated($request, $business);
        $items     = $this->priced($request, $business, $access, $lead, $data['items']);
        $quotation = $this->leadQuotations->update($lead, $quotation, $data, $items, $request->user()?->id);

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

    /**
     * POST …/{quotation}/revise — { reason? }: a head (or admin) makes a new draft version of an
     * approved, sent or rejected quotation straight away, recorded as a revision request they
     * approved themselves. Staff send a revision request instead (requestRevision).
     */
    public function revise(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        if (! $this->policy->canApproveQuotation($access, $lead)) {
            throw new HttpResponseException(response()->json([
                'message' => 'An issued quotation cannot be changed directly — send a revision request to the department head.',
            ], 403));
        }
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        $data     = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $revision = $this->revisions->request($lead, $quotation, (int) $request->user()->id, $data['reason'] ?? 'New version made by the head.', approveNow: true);

        return response()->json([
            'message' => 'Version ' . $revision->version . ' (' . $revision->quote_number . ') created.',
            'data'    => $this->presentDetail($business, $access, $lead, $revision),
        ], 201);
    }

    /**
     * POST …/{quotation}/revision-requests — { reason }: ask the head to allow changing an issued
     * quotation. A head / admin asking is approved at once: "data" is then the new version.
     */
    public function requestRevision(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $canApprove = $this->policy->canApproveQuotation($access, $lead);
        $this->abortUnlessAllowed($canApprove || $this->policy->canQuote($access, $lead));
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        $data   = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $result = $this->revisions->request($lead, $quotation, (int) $request->user()->id, $data['reason'], approveNow: $canApprove);

        if ($result instanceof Quotation) {
            return response()->json([
                'message'  => 'Version ' . $result->version . ' (' . $result->quote_number . ') created — make the changes and submit it.',
                'approved' => true,
                'data'     => $this->presentDetail($business, $access, $lead->fresh(), $result),
            ], 201);
        }

        return response()->json([
            'message'  => 'Revision request sent to the department head.',
            'approved' => false,
            'data'     => $this->presentDetail($business, $access, $lead, $quotation->fresh()),
        ], 201);
    }

    /** POST …/{quotation}/revision-requests/{request}/approve — { comment? }: the head; the new draft version is returned. */
    public function approveRevision(Request $request, int $leadId, int $quotationId, int $requestId): JsonResponse
    {
        [$business, $access, $lead, $quotation, $revisionRequest] = $this->revisionContext($request, $leadId, $quotationId, $requestId);
        $this->abortUnlessAllowed($this->policy->canApproveQuotation($access, $lead));

        $data     = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $revision = $this->revisions->approve($lead, $revisionRequest, (int) $request->user()->id, $data['comment'] ?? null);

        return response()->json([
            'message' => 'Revision approved — version ' . $revision->version . ' (' . $revision->quote_number . ') created.',
            'data'    => $this->presentDetail($business, $access, $lead->fresh(), $revision),
        ]);
    }

    /** POST …/{quotation}/revision-requests/{request}/reject — { comment }: the head; the quotation stays as it is. */
    public function rejectRevision(Request $request, int $leadId, int $quotationId, int $requestId): JsonResponse
    {
        [$business, $access, $lead, $quotation, $revisionRequest] = $this->revisionContext($request, $leadId, $quotationId, $requestId);
        $this->abortUnlessAllowed($this->policy->canApproveQuotation($access, $lead));

        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']]);
        $this->revisions->reject($lead, $revisionRequest, (int) $request->user()->id, $data['comment']);

        return response()->json(['message' => 'Revision request rejected.', 'data' => $this->presentDetail($business, $access, $lead, $quotation->fresh())]);
    }

    /** POST …/{quotation}/revision-requests/{request}/cancel — whoever asked, or a head. */
    public function cancelRevision(Request $request, int $leadId, int $quotationId, int $requestId): JsonResponse
    {
        [$business, $access, $lead, $quotation, $revisionRequest] = $this->revisionContext($request, $leadId, $quotationId, $requestId);
        $userId = (int) $request->user()->id;
        $this->abortUnlessAllowed((int) $revisionRequest->requested_by === $userId || $this->policy->canApproveQuotation($access, $lead));

        $this->revisions->cancel($revisionRequest, $userId);

        return response()->json(['message' => 'Revision request cancelled.', 'data' => $this->presentDetail($business, $access, $lead, $quotation->fresh())]);
    }

    /** GET crm/quotation-revision-requests — revision requests waiting for this user's decision. */
    public function revisionInbox(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->access($request, $business);

        $rows = $this->revisions->inbox($business, $access)->map(fn (QuotationRevisionRequest $r) => $this->presentRevisionRequest($r) + [
            'quotation' => $this->presentListItem($r->quotation),
            'lead'      => $this->presentLead($r->quotation->lead),
        ])->values();

        return response()->json(['data' => $rows]);
    }

    /**
     * POST …/{quotation}/submit — { comment?, approver_ids[]? }: staff on the lead send it for
     * approval, emailing the ticked heads (all of them when approver_ids is left out) a link to
     * approve or reject it.
     */
    public function submit(Request $request, int $leadId, int $quotationId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canQuote($access, $lead));
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        $data = $request->validate([
            'comment'        => ['nullable', 'string', 'max:2000'],
            'approver_ids'   => ['nullable', 'array', 'max:10'],
            'approver_ids.*' => ['integer'],
        ]);
        // Within the department's discount and value limits it is issued straight away — no head to email
        $required  = $this->approvals->approvalCheck($lead, $quotation)['required'];
        $approvers = $required ? $this->approvals->pickApprovers($lead, $request->has('approver_ids') ? ($data['approver_ids'] ?? []) : null) : [];
        $quotation = $this->approvals->submit($lead, $quotation, (int) $request->user()->id, $data['comment'] ?? null);

        if ($quotation->status === Quotation::STATUS_APPROVED) {
            return response()->json([
                'message' => 'Within the approval limits — no approval needed. Ready to send to the customer.',
                'emailed' => ['sent' => [], 'failed' => []],
                'data'    => $this->presentDetail($business, $access, $lead->fresh(), $quotation),
            ]);
        }

        $mail = $this->approvalMail->send($lead, $quotation, $approvers);

        $message = 'Submitted for approval.'
            . ($mail['sent'] ? ' Emailed ' . implode(', ', $mail['sent']) . '.' : '')
            . ($mail['failed'] ? ' Could not email ' . implode(', ', $mail['failed']) . ' — they can still approve in the CRM.' : '');

        return response()->json(['message' => $message, 'emailed' => $mail, 'data' => $this->presentDetail($business, $access, $lead->fresh(), $quotation)]);
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

    /** POST …/{quotation}/send — { channel?, to, cc[]?, subject, message }: queue the approved PDF to the customer. */
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
            'value'    => $this->approvals->approvalCheck($q->lead, $q)['value'],
            'price_changes' => $this->approvals->priceChanges($q),
            'routing'  => $this->approvals->routing($q),
            'subtotal' => round((float) $q->subtotal, 2),
            'items_count' => $q->items()->count(),
        ])->values();

        return response()->json(['data' => $rows, 'meta' => ['can_approve' => ($access->unrestricted || $access->isHead()) && $access->can('quotations_approve')]]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function decide(Request $request, int $leadId, int $quotationId, string $decision): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canApproveQuotation($access, $lead));
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

    /** Automatic pricing: standard prices on the product lines, locked unless the user may change them. */
    private function priced(Request $request, Business $business, CrmAccess $access, Lead $lead, array $items): array
    {
        return app(CrmQuotationCatalogService::class)->applyStandardPrices(
            $business,
            $items,
            $this->policy->canChangeQuotationPrice($access, $lead),
            $this->branchHeader($request),
        );
    }

    /** The desktop's branch (X-Branch-Id) — prices and stock follow it when branches run separate POS. */
    private function branchHeader(Request $request): ?int
    {
        $branchId = $request->header('X-Branch-Id');

        return is_numeric($branchId) ? (int) $branchId : null;
    }

    /** @return array<string, mixed> header fields + items, validated */
    private function validated(Request $request, Business $business): array
    {
        return $request->validate([
            'branch_id'               => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'customer_id'             => ['nullable', 'integer', Rule::exists('pos_customers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'customer_address'        => ['nullable', 'string', 'max:1000'],
            // Saved to the customer master: updates customer_id's record, else finds / creates one
            'customer'                => ['nullable', 'array'],
            'customer.name'           => ['nullable', 'string', 'max:120'],
            'customer.phone'          => ['nullable', 'string', 'max:40'],
            'customer.email'          => ['nullable', 'email', 'max:160'],
            'customer.address'        => ['nullable', 'string', 'max:255'],
            'customer.industry'       => ['nullable', 'string', 'max:120'],
            'reference'              => ['nullable', 'string', 'max:120'],
            'quote_date'              => ['required', 'date'],
            'expiry_date'             => ['nullable', 'date', 'after_or_equal:quote_date'],
            'notes'                   => ['nullable', 'string', 'max:5000'],
            'discount_amount'         => ['nullable', 'numeric', 'min:0'],
            'tax_amount'              => ['nullable', 'numeric', 'min:0'],
            // "Prepared by" — left out → the signed-in user's CRM profile
            'prepared_by_name'        => ['sometimes', 'nullable', 'string', 'max:120'],
            'prepared_by_designation' => ['sometimes', 'nullable', 'string', 'max:120'],
            'prepared_by_phone'       => ['sometimes', 'nullable', 'string', 'max:40'],
            // true → also keep these as the user's CRM profile for their next quotations
            'remember_prepared_by'    => ['sometimes', 'boolean'],
            'items'                   => ['required', 'array', 'min:1', 'max:200'],
            'items.*.item_type'       => ['nullable', 'string', 'in:product,service,custom'],
            'items.*.product_id'      => ['nullable', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'items.*.service_item_id' => ['nullable', 'integer'],
            'items.*.description'     => ['nullable', 'string', 'max:255'],
            'items.*.notes'           => ['nullable', 'string', 'max:1000'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    /** @return array{name: string, designation: ?string, phone: ?string}|null */
    private function preparedByDefaults(Business $business, Request $request): ?array
    {
        $userId = $request->user()?->id;
        if (! $userId) {
            return null;
        }
        $p = app(CrmProfileService::class)->get((int) $business->id, (int) $userId);

        return ['name' => $p['name'], 'designation' => $p['designation'], 'phone' => $p['phone']];
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
            // A revision request waiting for the head (only when loaded — the lead's list loads it)
            'pending_revision' => $q->relationLoaded('pendingRevisionRequest') && $q->pendingRevisionRequest
                ? $this->presentRevisionRequest($q->pendingRevisionRequest)
                : null,
        ];
    }

    /** One revision request: who asked and why, who decided, the version it made and who changed it. */
    private function presentRevisionRequest(QuotationRevisionRequest $r): array
    {
        $r->loadMissing(['requestedBy:id,name', 'decidedBy:id,name', 'revisedBy:id,name', 'revision:id,quote_number,version']);
        $user = fn ($u) => $u ? ['id' => (int) $u->id, 'name' => $u->name] : null;

        return [
            'id'               => (int) $r->id,
            'quotation_id'     => (int) $r->quotation_id,
            'version'          => (int) $r->version,
            'status'           => $r->status,
            'label'            => $r->label(),
            'reason'           => $r->reason,
            'requested_by'     => $user($r->requestedBy),
            'requested_at'     => $r->created_at?->toIso8601String(),
            'decided_by'       => $user($r->decidedBy),
            'decided_at'       => $r->decided_at?->toIso8601String(),
            'decision_comment' => $r->decision_comment,
            'revision'         => $r->revision ? ['id' => (int) $r->revision->id, 'quote_number' => $r->revision->quote_number, 'version' => (int) $r->revision->version] : null,
            'revised_by'       => $user($r->revisedBy),
            'revised_at'       => $r->revised_at?->toIso8601String(),
        ];
    }

    /** @return array{0: Business, 1: CrmAccess, 2: Lead, 3: Quotation, 4: QuotationRevisionRequest} */
    private function revisionContext(Request $request, int $leadId, int $quotationId, int $requestId): array
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $quotation = $this->leadQuotations->quotationForLead($lead, $quotationId);

        return [$business, $access, $lead, $quotation, $this->revisions->requestForQuotation($quotation, $requestId)];
    }

    private function presentDetail(Business $business, CrmAccess $access, Lead $lead, Quotation $q): array
    {
        $q->setRelation('lead', $lead);
        $q->load(['customer', 'items.product:id,name,sku', 'approvals.byUser:id,name', 'dispatches.sentBy:id,name', 'customerResponses.recordedBy:id,name', 'submittedBy:id,name', 'approvedBy:id,name',
            'revisionRequests', 'pendingRevisionRequest', 'revisionSource']);

        $items = $q->items->map(fn (QuotationItem $i) => [
            'id'              => (int) $i->id,
            'item_type'       => $i->product_id ? 'product' : ($i->service_item_id ? 'service' : 'custom'),
            'product_id'      => $i->product_id,
            'service_item_id' => $i->service_item_id,
            'product_name'    => $i->product?->name,
            'sku'             => $i->product?->sku,
            'description'     => $i->description,
            'notes'           => $i->notes,
            'quantity'        => round((float) $i->quantity, 3),
            'unit_price'      => round((float) $i->unit_price, 2),
            'list_price'      => $i->list_price !== null ? round((float) $i->list_price, 2) : null,
            'price_changed'   => $i->isPriceChanged(),
            'discount_amount' => round((float) $i->discount_amount, 2),
            'tax_rate'        => round((float) $i->tax_rate, 3),
            'tax_amount'      => round((float) $i->tax_amount, 2),
            'line_total'      => round((float) $i->line_total, 2),
        ])->values()->all();

        $canQuote   = $this->policy->canQuote($access, $lead);
        $canApprove = $this->policy->canApproveQuotation($access, $lead);
        $pending    = $q->status === Quotation::STATUS_PENDING_APPROVAL;
        $canSubmit  = $canQuote && in_array($q->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_RETURNED], true);
        $canSend    = $canQuote && in_array($q->status, [Quotation::STATUS_APPROVED, Quotation::STATUS_SENT], true);
        $newer      = $q->isSuperseded()
            ? Quotation::query()->where('business_id', $q->business_id)->where('parent_quotation_id', $q->id)->first(['id', 'quote_number', 'version'])
            : null;
        // Revision control: an issued quotation changes only through a request the head approves
        $pendingRevision = $q->pendingRevisionRequest;
        $revisable       = $this->revisions->blocker($q) === null;
        $myId            = (int) $access->userId;

        return $this->presentListItem($q) + [
            'reference'     => $q->reference,
            'notes'         => $q->notes,
            'customer_id'   => $q->customer_id,
            'customer'      => $this->leadQuotations->presentCustomer($q->customer),
            'customer_address' => $q->customer_address,
            // Who the printed quotation is addressed to — the customer, else the lead
            'bill_to'       => $q->billTo(),
            // "Prepared by" block of the printed quotation: { name, designation, phone } or null
            'prepared_by'   => $q->preparedByDetails(),
            'branch_id'     => $q->branch_id,
            'subtotal'      => round((float) $q->subtotal, 2),
            'discount_amount' => round((float) $q->discount_amount, 2),
            'tax_amount'    => round((float) $q->tax_amount, 2),
            'header'        => $this->quotations->headerAdjustments($q),
            'items'         => $items,
            'stock'         => $this->leadQuotations->stockCheck($business, $items, $q->branch_id),
            'discount'      => $this->approvals->discountSummary($lead, $q),
            // Whether submitting needs the head (over a limit / none set) or issues it straight away
            'approval'      => $this->approvals->approvalCheck($lead, $q),
            // Issued without the head because it was within the department's limits
            'within_limits' => $q->approved_at !== null && $q->approved_by === null && in_array($q->status, [Quotation::STATUS_APPROVED, Quotation::STATUS_SENT, Quotation::STATUS_ACCEPTED], true),
            // Product lines quoted away from their standard price — shown to the approver
            'price_changes' => $this->approvals->priceChanges($q),
            // The heads the submit form offers to email for approval
            'approvers'     => $canSubmit && $this->approvals->approvalCheck($lead, $q)['required'] ? $this->approvals->approvers($lead) : [],
            'superseded_by' => $newer ? ['id' => (int) $newer->id, 'quote_number' => $newer->quote_number, 'version' => (int) $newer->version] : null,
            // Locked: issued, so changing it needs an approved revision request
            'is_locked'          => ! $q->isEditable(),
            'pending_revision'   => $pendingRevision ? $this->presentRevisionRequest($pendingRevision) : null,
            // Every revision request made on this version (approved, rejected, cancelled, pending)
            'revision_requests'  => $q->revisionRequests->map(fn (QuotationRevisionRequest $r) => $this->presentRevisionRequest($r))->values(),
            // The approved request this version was made from: who asked, who approved, who changed it
            'revision_of'        => $q->revisionSource ? $this->presentRevisionRequest($q->revisionSource) : null,
            // true → asking makes the new version at once (the user may approve it themselves)
            'revision_auto_approve' => $canApprove,
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
                'submit'  => $canSubmit,
                'approve' => $canApprove && $pending,
                'return'  => $canApprove && $pending,
                'reject'  => $canApprove && $pending,
                // A head / admin making a new version directly (recorded as a self-approved request)
                'revise'  => $canApprove && $revisable,
                'send'    => $canSend,
                'respond' => $canQuote && $q->status === Quotation::STATUS_SENT,
                'request_revision' => ($canQuote || $canApprove) && $revisable,
                'approve_revision' => $canApprove && $pendingRevision !== null,
                'reject_revision'  => $canApprove && $pendingRevision !== null,
                'cancel_revision'  => $pendingRevision !== null && ($canApprove || (int) $pendingRevision->requested_by === $myId),
            ],
            'channels'      => $canSend ? $this->dispatcher->channels() : [],
            'send_defaults' => $canSend ? $this->dispatcher->defaults($lead, $q) + ['cc_options' => $this->ccOptions($lead, $q)] : null,
        ];
    }

    /**
     * Addresses the send form offers for CC: the customer's / lead's email and the lead's
     * department members. Anything else can still be typed in.
     *
     * @return list<array{email: string, name: string, kind: string}>
     */
    private function ccOptions(Lead $lead, Quotation $q): array
    {
        $options = [];
        foreach ([$q->customer?->email, $lead->email] as $email) {
            if ($email) {
                $options[] = ['email' => $email, 'name' => $q->customer?->name ?: $lead->name, 'kind' => 'customer'];
            }
        }
        foreach ($this->assignments->assignableUsers($lead) as $user) {
            if ($user->email) {
                $options[] = ['email' => $user->email, 'name' => $user->name, 'kind' => 'member'];
            }
        }

        return array_values(collect($options)->unique(fn ($o) => mb_strtolower($o['email']))->all());
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
        $versions = $this->versions->versions($quotation)->load('revisionSource');
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
                // The approved revision request this version came from: requested / approved / revised by
                'revision'         => $v->revisionSource ? $this->presentRevisionRequest($v->revisionSource) : null,
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
            'customer_id'  => $lead->customer_id,
            // What a new quotation's address is filled with
            'customer_address' => $this->leadQuotations->customerAddress($lead),
            // What a new quotation's customer section is filled with
            'customer_defaults' => $this->leadQuotations->customerDefaults($lead),
            'project_id'  => (int) $lead->project_id,
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
        if (! $access->can('leads_view') || ! $access->can('quotations_view')) {
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
