<?php

namespace Modules\Restaurant\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Purchase\Models\Supplier;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\Ingredient;
use Modules\Restaurant\Models\IngredientPurchaseOrder;
use Modules\Restaurant\Services\IngredientPurchaseService;

class IngredientPurchaseApiController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function __construct(private readonly IngredientPurchaseService $service) {}

    private function resolveBiz(Request $request): Business|JsonResponse
    {
        $b = $this->requireBusiness($request);
        return $b instanceof Business ? $b : response()->json(['error' => 'Business not found'], 404);
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->resolveBiz($request);
        if ($business instanceof JsonResponse) return $business;

        $status = (string) $request->query('status', 'all');
        $page   = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        $query = IngredientPurchaseOrder::where('business_id', $business->id)
            ->with(['supplier'])
            ->withCount('items');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $query->orderByDesc('purchase_date')->orderByDesc('id');
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginated->map(fn ($po) => $this->formatPoSummary($po))->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->resolveBiz($request);
        if ($business instanceof JsonResponse) return $business;

        $data  = $this->validateHeader($request, $business);
        $items = $request->input('items', []);

        try {
            $po = $this->service->create($business, $data, $items);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $this->formatPoDetail($po)], 201);
    }

    public function show(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): JsonResponse
    {
        $business = $this->resolveBiz($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $ingredientPurchaseOrder->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $ingredientPurchaseOrder->load(['supplier', 'items.ingredient', 'grns.items.ingredient']);

        return response()->json(['data' => $this->formatPoDetail($ingredientPurchaseOrder)]);
    }

    public function update(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): JsonResponse
    {
        $business = $this->resolveBiz($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $ingredientPurchaseOrder->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data  = $this->validateHeader($request, $business);
        $items = $request->input('items', []);

        try {
            $po = $this->service->update($ingredientPurchaseOrder, $data, $items);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $this->formatPoDetail($po)]);
    }

    public function placeOrder(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): JsonResponse
    {
        $business = $this->resolveBiz($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $ingredientPurchaseOrder->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $po = $this->service->markOrdered($ingredientPurchaseOrder);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $this->formatPoDetail($po->load(['supplier', 'items.ingredient', 'grns.items.ingredient']))]);
    }

    public function cancel(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): JsonResponse
    {
        $business = $this->resolveBiz($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $ingredientPurchaseOrder->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $po = $this->service->cancel($ingredientPurchaseOrder);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $this->formatPoDetail($po->load(['supplier', 'items.ingredient', 'grns.items.ingredient']))]);
    }

    public function destroy(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): JsonResponse
    {
        $business = $this->resolveBiz($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $ingredientPurchaseOrder->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $this->service->delete($ingredientPurchaseOrder);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true]);
    }

    public function createGrn(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): JsonResponse
    {
        $business = $this->resolveBiz($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $ingredientPurchaseOrder->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            'received_date'  => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,cheque,credit'],
            'reference'      => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:3000'],
            'lines'          => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_item_id' => ['required', 'integer'],
            'lines.*.quantity_received'       => ['required', 'numeric', 'min:0.001'],
        ]);

        try {
            $grn = $this->service->createGrn($ingredientPurchaseOrder, $data, $data['lines']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        $ingredientPurchaseOrder->load(['supplier', 'items.ingredient', 'grns.items.ingredient']);

        return response()->json(['data' => $this->formatPoDetail($ingredientPurchaseOrder)], 201);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function formatPoSummary(IngredientPurchaseOrder $po): array
    {
        return [
            'id'                     => (int) $po->id,
            'po_number'              => $po->po_number,
            'status'                 => $po->status,
            'status_label'           => $po->statusLabel(),
            'status_color'           => $po->statusColor(),
            'purchase_date'          => $po->purchase_date?->toDateString(),
            'expected_delivery_date' => $po->expected_delivery_date?->toDateString(),
            'supplier_name'          => $po->supplier?->name,
            'total'                  => round((float) $po->total, 2),
            'items_count'            => $po->items_count ?? 0,
        ];
    }

    private function formatPoDetail(IngredientPurchaseOrder $po): array
    {
        return [
            'id'                     => (int) $po->id,
            'po_number'              => $po->po_number,
            'status'                 => $po->status,
            'status_label'           => $po->statusLabel(),
            'status_color'           => $po->statusColor(),
            'is_editable'            => $po->isEditable(),
            'can_receive'            => $po->canReceiveGoods(),
            'purchase_date'          => $po->purchase_date?->toDateString(),
            'expected_delivery_date' => $po->expected_delivery_date?->toDateString(),
            'notes'                  => $po->notes,
            'subtotal'               => round((float) $po->subtotal, 2),
            'total'                  => round((float) $po->total, 2),
            'supplier'               => $po->supplier
                ? ['id' => (int) $po->supplier->id, 'name' => $po->supplier->name]
                : null,
            'items'                  => ($po->items ?? collect())->map(fn ($i) => [
                'id'              => (int) $i->id,
                'ingredient_id'   => (int) $i->ingredient_id,
                'ingredient_name' => $i->ingredient?->name ?? '—',
                'ingredient_unit' => $i->ingredient?->unit ?? '',
                'quantity'        => round((float) $i->quantity, 3),
                'unit_cost'       => round((float) $i->unit_cost, 4),
                'line_total'      => round((float) $i->line_total, 2),
                'qty_received'    => $i->quantityReceived(),
                'qty_remaining'   => $i->quantityRemaining(),
            ])->values()->all(),
            'grns'                   => ($po->grns ?? collect())->map(fn ($g) => [
                'id'             => (int) $g->id,
                'grn_number'     => $g->grn_number,
                'received_date'  => $g->received_date?->toDateString(),
                'payment_method' => $g->payment_method,
                'payment_label'  => $g->paymentMethodLabel(),
                'reference'      => $g->reference,
                'total'          => round((float) $g->total, 2),
            ])->values()->all(),
            'created_at'             => $po->created_at?->toIso8601String(),
        ];
    }

    private function validateHeader(Request $request, Business $business): array
    {
        $supplierId = $request->input('supplier_id');
        $supplierId = ($supplierId === null || $supplierId === '' || $supplierId === '0') ? null : (int) $supplierId;

        $validated = $request->validate([
            'supplier_id'            => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'purchase_date'          => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'status'                 => ['required', 'string', Rule::in([IngredientPurchaseOrder::STATUS_DRAFT, IngredientPurchaseOrder::STATUS_ORDERED])],
            'notes'                  => ['nullable', 'string', 'max:3000'],
        ]);

        $validated['supplier_id'] = $supplierId;

        return $validated;
    }
}
