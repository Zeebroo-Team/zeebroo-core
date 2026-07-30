<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesOrderService;

class PosSalesOrderApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly SalesOrderService $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $search   = trim((string) $request->query('q', ''));
        $status   = (string) $request->query('status', 'all');

        $list = $this->orders->listForBusiness(
            $business,
            $search !== '' ? $search : null,
            $status,
        );

        return response()->json([
            'data' => $list->map(fn ($o) => $this->formatListItem($o))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $this->validateRequest($request, $business);

        $order = $this->orders->create($business, $validated, $request->input('items', []));
        $order->load(['customer', 'items.product']);

        return response()->json([
            'message' => 'Order ' . $order->order_number . ' created.',
            'data'    => $this->formatDetail($order),
        ], 201);
    }

    public function show(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $salesOrder->business_id === (int) $business->id, 404);

        $salesOrder->load(['customer', 'items.product']);

        return response()->json(['data' => $this->formatDetail($salesOrder)]);
    }

    public function update(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $salesOrder->business_id === (int) $business->id, 404);
        abort_unless($salesOrder->isEditable(), 422, 'This order can no longer be edited.');

        $validated = $this->validateRequest($request, $business);
        $order = $this->orders->update($salesOrder, $validated, $request->input('items', []));
        $order->load(['customer', 'items.product']);

        return response()->json([
            'message' => 'Order updated.',
            'data'    => $this->formatDetail($order),
        ]);
    }

    public function confirm(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $salesOrder->business_id === (int) $business->id, 404);

        $this->orders->confirm($salesOrder);

        return response()->json(['message' => 'Order confirmed.', 'status' => $salesOrder->fresh()->status]);
    }

    public function process(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $salesOrder->business_id === (int) $business->id, 404);

        $this->orders->process($salesOrder);

        return response()->json(['message' => 'Order marked as processing.', 'status' => $salesOrder->fresh()->status]);
    }

    public function complete(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $salesOrder->business_id === (int) $business->id, 404);

        $this->orders->complete($salesOrder);

        return response()->json(['message' => 'Order completed.', 'status' => $salesOrder->fresh()->status]);
    }

    public function cancel(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $salesOrder->business_id === (int) $business->id, 404);

        $this->orders->cancel($salesOrder);

        return response()->json(['message' => 'Order cancelled.', 'status' => $salesOrder->fresh()->status]);
    }

    public function destroy(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $salesOrder->business_id === (int) $business->id, 404);
        abort_unless(
            in_array($salesOrder->status, [SalesOrder::STATUS_PENDING, SalesOrder::STATUS_CANCELLED]),
            422,
            'Only pending or cancelled orders can be deleted.'
        );

        $this->orders->delete($salesOrder);

        return response()->json(['message' => 'Order deleted.']);
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatListItem(SalesOrder $o): array
    {
        return [
            'id'                     => (int) $o->id,
            'order_number'           => $o->order_number,
            'status'                 => $o->status,
            'status_label'           => $o->statusLabel(),
            'status_color'           => $o->statusColor(),
            'customer_name'          => $o->customer?->name,
            'customer_id'            => $o->customer_id,
            'reference'              => $o->reference,
            'order_date'             => $o->order_date?->toDateString(),
            'expected_delivery_date' => $o->expected_delivery_date?->toDateString(),
            'total'                  => round((float) $o->total, 2),
            'item_count'             => $o->items()->count(),
        ];
    }

    private function formatDetail(SalesOrder $o): array
    {
        return [
            ...$this->formatListItem($o),
            'subtotal'        => round((float) $o->subtotal, 2),
            'discount_amount' => round((float) $o->discount_amount, 2),
            'tax_amount'      => round((float) $o->tax_amount, 2),
            'notes'           => $o->notes,
            'is_editable'     => $o->isEditable(),
            'items'           => $o->items->map(fn ($i) => [
                'id'          => (int) $i->id,
                'product_id'  => $i->product_id,
                'description' => $i->description,
                'quantity'    => round((float) $i->quantity, 3),
                'unit_price'  => round((float) $i->unit_price, 2),
                'line_total'  => round((float) $i->line_total, 2),
            ])->values()->all(),
        ];
    }

    private function validateRequest(Request $request, \Modules\Business\Models\Business $business): array
    {
        return $request->validate([
            'customer_id'             => ['nullable', 'integer', Rule::exists('pos_customers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'reference'               => ['nullable', 'string', 'max:120'],
            'order_date'              => ['required', 'date'],
            'expected_delivery_date'  => ['nullable', 'date'],
            'notes'                   => ['nullable', 'string', 'max:5000'],
            'discount_amount'         => ['nullable', 'numeric', 'min:0'],
            'tax_amount'              => ['nullable', 'numeric', 'min:0'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.product_id'      => ['nullable', 'integer'],
            'items.*.description'     => ['nullable', 'string', 'max:500'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
        ]);
    }
}
