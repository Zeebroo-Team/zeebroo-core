<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\PurchaseItem;
use Modules\Purchase\Services\PurchaseService;

class PosPurchaseOrderApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly PurchaseService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $status = (string) $request->query('status', '');
        $search = (string) $request->query('q', '');

        $purchases = $this->service->listForBusiness(
            $business,
            $search !== '' ? $search : null,
            $status !== '' && $status !== 'all' ? $status : null,
        );

        return response()->json([
            'data' => $purchases->map(fn (Purchase $p) => $this->formatSummary($p))->values(),
        ]);
    }

    public function show(Request $request, Purchase $purchase): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $purchase->business_id === (int) $business->id, 404);

        $purchase->load(['supplier', 'items.product']);

        return response()->json([
            'data' => $this->formatDetail($purchase),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'supplier_id'            => ['nullable', 'integer', 'min:1'],
            'reference'              => ['nullable', 'string', 'max:100'],
            'purchase_date'          => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'status'                 => ['nullable', 'string', 'in:draft,ordered'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'integer', 'min:1'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost'      => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $purchase = $this->service->create($business, $validated, $validated['items']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $purchase->load(['supplier', 'items.product']);

        return response()->json([
            'message' => 'Purchase order '.$purchase->po_number.' created.',
            'data'    => $this->formatDetail($purchase),
        ], 201);
    }

    public function placeOrder(Request $request, Purchase $purchase): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $purchase->business_id === (int) $business->id, 404);

        try {
            $purchase = $this->service->markOrdered($purchase);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $purchase->load(['supplier', 'items.product']);

        return response()->json([
            'message' => 'Purchase order '.$purchase->po_number.' placed.',
            'data'    => $this->formatDetail($purchase),
        ]);
    }

    public function receive(Request $request, Purchase $purchase): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $purchase->business_id === (int) $business->id, 404);

        try {
            $this->service->markReceived($purchase, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $purchase->refresh()->load(['supplier', 'items.product']);

        return response()->json([
            'message' => 'Goods received for '.$purchase->po_number.'.',
            'data'    => $this->formatDetail($purchase),
        ]);
    }

    public function cancel(Request $request, Purchase $purchase): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $purchase->business_id === (int) $business->id, 404);

        try {
            $purchase = $this->service->cancel($purchase);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Purchase order '.$purchase->po_number.' cancelled.',
            'data'    => $this->formatSummary($purchase),
        ]);
    }

    // ── Formatters ─────────────────────────────────────────────────────────────

    private function formatSummary(Purchase $purchase): array
    {
        return [
            'id'                     => $purchase->id,
            'po_number'              => $purchase->po_number,
            'status'                 => $purchase->status,
            'status_label'           => $purchase->statusLabel(),
            'supplier_id'            => $purchase->supplier_id,
            'supplier_name'          => $purchase->supplier?->name,
            'purchase_date'          => $purchase->purchase_date?->format('Y-m-d'),
            'expected_delivery_date' => $purchase->expected_delivery_date?->format('Y-m-d'),
            'subtotal'               => (float) $purchase->subtotal,
            'total'                  => (float) $purchase->total,
            'items_count'            => $purchase->items_count ?? $purchase->items->count(),
            'notes'                  => $purchase->notes,
        ];
    }

    private function formatDetail(Purchase $purchase): array
    {
        return array_merge($this->formatSummary($purchase), [
            'items_count' => $purchase->items->count(),
            'items'       => $purchase->items->map(fn (PurchaseItem $item) => [
                'id'           => $item->id,
                'product_id'   => $item->product_id,
                'product_name' => $item->product?->name,
                'sku'          => $item->product?->sku,
                'quantity'     => (float) $item->quantity,
                'unit_cost'    => (float) $item->unit_cost,
                'line_total'   => (float) $item->line_total,
            ])->values()->all(),
        ]);
    }
}
