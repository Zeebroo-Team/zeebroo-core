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

        $grn->load(['purchase.supplier', 'items.product', 'items.purchaseItem', 'ledgerTransactions.deductAccount']);

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
            'items'              => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id'    => ['required', 'integer'],
            'items.*.quantity_received'   => ['nullable', 'numeric', 'min:0'],
            'items.*.selling_unit_price'  => ['nullable', 'numeric', 'min:0'],
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

        $grn->load(['purchase.supplier', 'items.product']);

        return response()->json([
            'message' => 'Goods receive note ' . $grn->grn_number . ' recorded.',
            'data'    => $this->formatDetail($grn),
        ], 201);
    }

    public function pay(Request $request, GoodsReceiveNote $grn): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $grn->business_id === (int) $business->id, 404);

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

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatSummary(GoodsReceiveNote $g): array
    {
        return [
            'id'              => $g->id,
            'grn_number'      => $g->grn_number,
            'purchase_id'     => $g->purchase_id,
            'po_number'       => $g->purchase?->po_number,
            'supplier_name'   => $g->purchase?->supplier?->name,
            'received_date'   => $g->received_date?->format('Y-m-d'),
            'total'           => round((float) $g->total, 2),
            'payment_method'  => $g->payment_method,
            'payment_status'  => $g->paymentStatus(),
            'payment_status_label' => $g->paymentStatusLabel(),
            'amount_paid'     => round($this->settlement->amountPaid($g), 2),
            'amount_outstanding' => round($this->settlement->amountOutstanding($g), 2),
        ];
    }

    private function formatDetail(GoodsReceiveNote $g): array
    {
        return array_merge($this->formatSummary($g), [
            'reference'   => $g->reference,
            'notes'       => $g->notes,
            'subtotal'    => round((float) $g->subtotal, 2),
            'items'       => ($g->relationLoaded('items') ? $g->items : collect())->map(fn ($item) => [
                'id'                 => $item->id,
                'product_id'         => $item->product_id,
                'product_name'       => $item->product?->name ?? $item->purchaseItem?->product?->name ?? '—',
                'sku'                => $item->product?->sku,
                'quantity_received'  => round((float) $item->quantity_received, 3),
                'unit_cost'          => round((float) $item->unit_cost, 2),
                'selling_unit_price' => $item->selling_unit_price ? round((float) $item->selling_unit_price, 2) : null,
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
