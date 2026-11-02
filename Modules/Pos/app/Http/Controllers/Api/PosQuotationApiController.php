<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Services\QuotationService;

class PosQuotationApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly QuotationService $quotations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $search   = trim((string) $request->query('q', ''));
        $status   = (string) $request->query('status', 'all');

        $list = $this->quotations->listForBusiness(
            $business,
            $search !== '' ? $search : null,
            $status,
        );

        return response()->json([
            'data' => $list->map(fn ($q) => $this->formatListItem($q))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'pos_quotations');

        $validated = $this->validateHeader($request, $business);

        try {
            $quotation = $this->quotations->create(
                $business,
                $validated,
                $request->input('items', []),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $quotation->load(['customer', 'items.product']);

        return response()->json([
            'message' => 'Quotation ' . $quotation->quote_number . ' created.',
            'data'    => $this->formatDetail($quotation),
        ], 201);
    }

    public function show(Request $request, Quotation $quotation): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $quotation->business_id === (int) $business->id, 404);

        $quotation->load(['customer', 'items.product']);

        return response()->json(['data' => $this->formatDetail($quotation)]);
    }

    public function update(Request $request, Quotation $quotation): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $quotation->business_id === (int) $business->id, 404);
        $this->abortUnlessPerm($request, $business, 'pos_quotations');

        // A lead quotation is edited from its lead (stock check, approval rules): crm/leads/{lead}/quotations/{id}
        if ($quotation->isLeadQuotation()) {
            return response()->json(['message' => 'This quotation belongs to a CRM lead — edit it from the lead.'], 422);
        }

        $validated = $this->validateHeader($request, $business);

        try {
            $quotation = $this->quotations->update($quotation, $validated, $request->input('items', []));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $quotation->load(['customer', 'items.product']);

        return response()->json([
            'message' => 'Quotation updated.',
            'data'    => $this->formatDetail($quotation),
        ]);
    }

    public function markSent(Request $request, Quotation $quotation): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $quotation->business_id === (int) $business->id, 404);

        try {
            $this->quotations->markSent($quotation);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['message' => 'Marked as sent.', 'status' => $quotation->fresh()->status]);
    }

    public function markAccepted(Request $request, Quotation $quotation): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $quotation->business_id === (int) $business->id, 404);

        $this->quotations->markAccepted($quotation);

        return response()->json(['message' => 'Marked as accepted.', 'status' => $quotation->fresh()->status]);
    }

    public function markRejected(Request $request, Quotation $quotation): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $quotation->business_id === (int) $business->id, 404);

        $this->quotations->markRejected($quotation);

        return response()->json(['message' => 'Marked as rejected.', 'status' => $quotation->fresh()->status]);
    }

    public function destroy(Request $request, Quotation $quotation): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $quotation->business_id === (int) $business->id, 404);
        $this->abortUnlessPerm($request, $business, 'pos_quotations');

        // A lead quotation keeps its approval history once it left draft
        if ($quotation->isLeadQuotation() && $quotation->status !== Quotation::STATUS_DRAFT) {
            return response()->json(['message' => 'This lead quotation has been through approval and cannot be deleted.'], 422);
        }

        try {
            $this->quotations->delete($quotation);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Quotation deleted.']);
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatListItem(Quotation $q): array
    {
        return [
            'id'             => (int) $q->id,
            'quote_number'   => $q->quote_number,
            'status'         => $q->status,
            'status_label'   => $q->statusLabel(),
            'status_color'   => $q->statusColor(),
            'customer_name'  => $q->customer?->name,
            'reference'      => $q->reference,
            'quote_date'     => $q->quote_date?->toDateString(),
            'expiry_date'    => $q->expiry_date?->toDateString(),
            'total'          => round((float) $q->total, 2),
            'proposal_count' => (int) ($q->proposal_count ?? 0),
            'version'        => (int) ($q->version ?? 1),
            'lead_id'        => $q->lead_id,
        ];
    }

    private function formatDetail(Quotation $q): array
    {
        return [
            ...$this->formatListItem($q),
            'subtotal'        => round((float) $q->subtotal, 2),
            'discount_amount' => round((float) $q->discount_amount, 2),
            'tax_amount'      => round((float) $q->tax_amount, 2),
            'notes'           => $q->notes,
            'customer_id'     => $q->customer_id,
            'is_editable'     => $q->isEditable() && ! $q->isLeadQuotation(),
            // The lead it was built from — the Sales panel links back to it
            'lead'            => $q->lead ? [
                'id'         => (int) $q->lead->id,
                'name'       => $q->lead->name,
                'project_id' => (int) $q->lead->project_id,
            ] : null,
            'items'           => $q->items->map(fn ($i) => [
                'id'              => (int) $i->id,
                'product_id'      => $i->product_id,
                'item_type'       => $i->product_id ? 'product' : ($i->service_item_id ? 'service' : 'custom'),
                'description'     => $i->description,
                'quantity'        => round((float) $i->quantity, 3),
                'unit_price'      => round((float) $i->unit_price, 2),
                'discount_amount' => round((float) $i->discount_amount, 2),
                'tax_rate'        => round((float) $i->tax_rate, 3),
                'tax_amount'      => round((float) $i->tax_amount, 2),
                'line_total'      => round((float) $i->line_total, 2),
            ])->values()->all(),
        ];
    }

    private function validateHeader(Request $request, \Modules\Business\Models\Business $business): array
    {
        return $request->validate([
            'customer_id'             => ['nullable', 'integer', Rule::exists('pos_customers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'branch_id'               => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'reference'               => ['nullable', 'string', 'max:120'],
            'quote_date'              => ['required', 'date'],
            'expiry_date'             => ['nullable', 'date'],
            'notes'                   => ['nullable', 'string', 'max:5000'],
            'discount_amount'         => ['nullable', 'numeric', 'min:0'],
            'tax_amount'              => ['nullable', 'numeric', 'min:0'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.item_type'       => ['nullable', 'string', 'in:product,service,custom'],
            'items.*.product_id'      => ['nullable', 'integer'],
            'items.*.service_item_id' => ['nullable', 'integer'],
            'items.*.description'     => ['nullable', 'string', 'max:255'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
