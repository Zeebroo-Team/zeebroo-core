<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\GoodsReceiveNoteService;
use Modules\Purchase\Services\GrnPaymentSettlementService;

class PosGoodsReceiveNoteApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly GoodsReceiveNoteService $grnService,
        private readonly GrnPaymentSettlementService $settlement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $q       = (string) $request->query('q', '');
        $payment = (string) $request->query('payment', 'all');
        $limit   = $request->query('limit') ? (int) $request->query('limit') : null;

        $notes = $this->grnService->listForBusiness(
            $business,
            $q !== '' ? $q : null,
            $payment,
        );

        if ($limit) {
            $notes = $notes->take($limit);
        }

        return response()->json([
            'data' => $notes->map(fn (GoodsReceiveNote $g) => $this->formatSummary($g))->values(),
        ]);
    }

    public function show(Request $request, GoodsReceiveNote $grn): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $grn->business_id === (int) $business->id, 404);

        $grn->load(['supplier', 'purchase.supplier', 'items.product', 'items.purchaseItem', 'ledgerTransactions.deductAccount']);

        return response()->json([
            'data' => $this->formatDetail($grn),
        ]);
    }

    /** Return PO items ready for GRN creation form */
    public function createForm(Request $request, Purchase $purchase): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $purchase->business_id === (int) $business->id, 404);
        abort_unless($purchase->canReceiveGoods(), 422);

        $purchase->load(['supplier', 'items.product', 'items.goodsReceiveNoteItems']);

        return response()->json([
            'data' => [
                'purchase' => [
                    'id'            => $purchase->id,
                    'po_number'     => $purchase->po_number,
                    'supplier_name' => $purchase->supplier?->name,
                    'status'        => $purchase->status,
                ],
                'items' => $purchase->items->map(fn ($item) => [
                    'id'                  => $item->id,
                    'product_id'          => $item->product_id,
                    'product_name'        => $item->product?->name ?? '—',
                    'sku'                 => $item->product?->sku,
                    'quantity_ordered'    => round((float) $item->quantity, 3),
                    'quantity_received'   => round($item->quantityReceived(), 3),
                    'quantity_remaining'  => round($item->quantityRemaining(), 3),
                    'unit_cost'           => round((float) $item->unit_cost, 2),
                    'selling_unit_price'  => null,
                ])->values(),
            ],
        ]);
    }

    public function store(Request $request, Purchase $purchase): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $purchase->business_id === (int) $business->id, 404);
        abort_unless($purchase->canReceiveGoods(), 422, 'This purchase cannot receive more goods.');
        $this->abortUnlessPerm($request, $business, 'inv_purchasing');

        $validated = $request->validate([
            'received_date'      => ['required', 'date'],
            'reference'          => ['nullable', 'string', 'max:120'],
            'notes'              => ['nullable', 'string', 'max:5000'],
            'payment_method'     => ['required', 'string', Rule::in(['cash', 'credit', 'cheque'])],
            'payment_reference'  => ['nullable', 'string', 'max:120'],
            'cheque_due_date'    => ['nullable', 'date'],
            'payment_option'     => ['nullable', 'string', Rule::in(['full', 'partial'])],
            'pay_amount'         => ['nullable', 'numeric', 'min:0.01'],
            'deduct_account_id'  => ['nullable', 'integer'],
            'payment_terms_days'          => ['nullable', 'integer', 'min:1', 'max:3650'],
            'expense_lines'              => ['nullable', 'array', 'max:20'],
            'expense_lines.*.name'        => ['required_with:expense_lines', 'string', 'max:80'],
            'expense_lines.*.type'        => ['required_with:expense_lines', 'string', Rule::in(['flat', 'pct'])],
            'expense_lines.*.value'       => ['required_with:expense_lines', 'numeric', 'min:0'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id'    => ['required', 'integer'],
            'items.*.quantity_received'   => ['nullable', 'numeric', 'min:0'],
            'items.*.selling_unit_price'  => ['nullable', 'numeric', 'min:0'],
            'items.*.units_per_case'      => ['nullable', 'integer', 'min:1'],
            'items.*.uom'                 => ['nullable', 'string', 'max:40'],
            'items.*.discount_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $grn = $this->grnService->createForPurchase(
                $purchase,
                $request->user() ?? abort(401),
                $validated,
                $validated['items'],
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $grn->load(['supplier', 'purchase.supplier', 'items.product']);

        return response()->json([
            'message' => 'Goods receive note ' . $grn->grn_number . ' recorded.',
            'data'    => $this->formatDetail($grn),
        ], 201);
    }

    /**
     * Create a GRN directly — without a purchase order.
     * Used when the "Purchase Order" workflow is disabled in settings.
     */
    public function storeDirect(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_purchasing');

        $validated = $request->validate([
            'supplier_id'        => ['nullable', 'integer', 'min:1'],
            'received_date'      => ['required', 'date'],
            'reference'          => ['nullable', 'string', 'max:120'],
            'notes'              => ['nullable', 'string', 'max:5000'],
            'payment_method'     => ['required', 'string', Rule::in(['cash', 'credit', 'cheque'])],
            'payment_reference'  => ['nullable', 'string', 'max:120'],
            'cheque_due_date'    => ['nullable', 'date'],
            'payment_option'     => ['nullable', 'string', Rule::in(['full', 'partial'])],
            'pay_amount'         => ['nullable', 'numeric', 'min:0.01'],
            'deduct_account_id'  => ['nullable', 'integer'],
            'payment_terms_days'         => ['nullable', 'integer', 'min:1', 'max:3650'],
            'expense_lines'              => ['nullable', 'array', 'max:20'],
            'expense_lines.*.name'        => ['required_with:expense_lines', 'string', 'max:80'],
            'expense_lines.*.type'        => ['required_with:expense_lines', 'string', Rule::in(['flat', 'pct'])],
            'expense_lines.*.value'       => ['required_with:expense_lines', 'numeric', 'min:0'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id'        => ['required', 'integer', 'min:1'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost'         => ['required', 'numeric', 'min:0'],
            'items.*.units_per_case'    => ['nullable', 'integer', 'min:1'],
            'items.*.uom'               => ['nullable', 'string', 'max:40'],
            'items.*.discount_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $grn = $this->grnService->createDirect(
                $business,
                $request->user() ?? abort(401),
                $validated,
                $validated['items'],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $grn->load(['supplier', 'items.product']);

        return response()->json([
            'message' => 'Goods receive note ' . $grn->grn_number . ' recorded.',
            'data'    => $this->formatDetail($grn),
        ], 201);
    }

    public function pay(Request $request, GoodsReceiveNote $grn): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $grn->business_id === (int) $business->id, 404);
        $this->abortUnlessPerm($request, $business, 'inv_purchasing');

        if ($this->settlement->isFullyPaid($grn)) {
            return response()->json(['message' => 'This GRN is already fully paid.'], 422);
        }

        $validated = $request->validate([
            'payment_method'     => ['required', 'string', Rule::in(['cash', 'credit', 'cheque'])],
            'deduct_account_id'  => ['required', 'integer'],
            'payment_option'     => ['required', 'string', Rule::in(['full', 'partial'])],
            'pay_amount'         => ['nullable', 'numeric', 'min:0.01'],
            'payment_reference'  => ['nullable', 'string', 'max:120'],
            'cheque_due_date'    => ['nullable', 'date'],
        ]);

        $payAmount = $validated['payment_option'] === 'partial'
            ? round((float) ($validated['pay_amount'] ?? 0), 2)
            : null;

        try {
            $this->settlement->settle(
                $grn,
                $business,
                $request->user() ?? abort(401),
                (int) $validated['deduct_account_id'],
                $payAmount,
                $validated['payment_method'],
                $validated['payment_reference'] ?? null,
                $validated['cheque_due_date'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $grn->refresh()->load(['ledgerTransactions.deductAccount']);

        return response()->json([
            'message' => 'Payment recorded.',
            'data'    => $this->formatDetail($grn),
        ]);
    }

    // ── Approval ──────────────────────────────────────────────────────────────

    public function approve(Request $request, GoodsReceiveNote $grn): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $grn->business_id === (int) $business->id, 404);
        $this->abortUnlessPerm($request, $business, 'inv_purchasing');

        try {
            $this->grnService->approveGrn($grn);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $grn->refresh()->load(['supplier', 'purchase.supplier', 'items.product', 'items.purchaseItem', 'ledgerTransactions.deductAccount']);

        return response()->json([
            'message' => 'GRN approved — stock has been applied.',
            'data'    => $this->formatDetail($grn),
        ]);
    }

    public function reject(Request $request, GoodsReceiveNote $grn): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $grn->business_id === (int) $business->id, 404);
        $this->abortUnlessPerm($request, $business, 'inv_purchasing');

        try {
            $this->grnService->rejectGrn($grn);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $grn->refresh()->load(['supplier', 'purchase.supplier', 'items.product', 'items.purchaseItem', 'ledgerTransactions.deductAccount']);

        return response()->json([
            'message' => 'GRN rejected.',
            'data'    => $this->formatDetail($grn),
        ]);
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatSummary(GoodsReceiveNote $g): array
    {
        return [
            'id'              => $g->id,
            'grn_number'      => $g->grn_number,
            'purchase_id'     => $g->purchase_id,
            'po_number'       => $g->purchase?->po_number,
            'supplier_name'   => $g->resolvedSupplierName(),
            'received_date'   => $g->received_date?->format('Y-m-d'),
            'total'           => round((float) $g->total, 2),
            'payment_method'       => $g->payment_method,
            'payment_terms_days'   => $g->payment_terms_days,
            'payment_due_date'     => $g->payment_due_date?->format('Y-m-d'),
            'payment_status'        => $g->paymentStatus(),
            'payment_status_label'  => $g->paymentStatusLabel(),
            'amount_paid'           => round($this->settlement->amountPaid($g), 2),
            'amount_outstanding'    => round($this->settlement->amountOutstanding($g), 2),
            'approval_status'       => $g->approval_status,
            'approval_status_label' => $g->approvalStatusLabel(),
        ];
    }

    private function formatDetail(GoodsReceiveNote $g): array
    {
        // Resolve supplier contact details from the loaded relation
        $supplier = $g->supplier                             // direct GRN
            ?? $g->purchase?->supplier                      // PO-linked GRN
            ?? null;

        return array_merge($this->formatSummary($g), [
            'reference'              => $g->reference,
            'notes'                  => $g->notes,
            'subtotal'               => round((float) $g->subtotal, 2),
            'expense_lines'          => $g->expense_lines ?? [],
            'supplier_contact_name'  => $supplier?->contact_name,
            'supplier_email'         => $supplier?->email,
            'supplier_phone'         => $supplier?->phone,
            'items'       => ($g->relationLoaded('items') ? $g->items : collect())->map(fn ($item) => [
                'id'                 => $item->id,
                'product_id'         => $item->product_id,
                'product_name'       => $item->product?->name ?? $item->purchaseItem?->product?->name ?? '—',
                'sku'                => $item->product?->sku,
                'quantity_received'  => round((float) $item->quantity_received, 3),
                'unit_cost'          => round((float) $item->unit_cost, 2),
                'selling_unit_price' => $item->selling_unit_price ? round((float) $item->selling_unit_price, 2) : null,
                'units_per_case'     => $item->units_per_case ? (int) $item->units_per_case : null,
                'uom'                => $item->uom,
                'discount_percent'   => $item->discount_percent !== null ? round((float) $item->discount_percent, 3) : null,
                'gross_value'        => round((float) $item->quantity_received * (float) $item->unit_cost, 2),
                'net_value'          => $item->discount_percent !== null
                    ? round((float) $item->quantity_received * (float) $item->unit_cost * (1 - (float) $item->discount_percent / 100), 2)
                    : null,
                'line_total'         => round((float) $item->line_total, 2),
            ])->values()->all(),
            'payments' => ($g->relationLoaded('ledgerTransactions') ? $g->ledgerTransactions : collect())->map(fn ($t) => [
                'id'      => $t->id,
                'amount'  => round((float) $t->amount, 2),
                'account' => $t->deductAccount?->deductOptionLabel(),
                'date'    => $t->created_at?->format('Y-m-d'),
            ])->values()->all(),
        ]);
    }
}
