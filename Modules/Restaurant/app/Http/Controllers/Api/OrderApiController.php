<?php

namespace Modules\Restaurant\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleItem;
use Modules\Pos\Services\SalePaymentSettlementService;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\Order;
use Modules\Restaurant\Models\OrderItem;
use Modules\Restaurant\Models\RestaurantTable;
use Modules\Restaurant\Services\MenuService;
use Modules\Restaurant\Services\OrderService;

class OrderApiController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function __construct(
        private readonly OrderService                 $orders,
        private readonly MenuService                  $menu,
        private readonly SalePaymentSettlementService $payments,
    ) {}

    private function resolveBusiness(Request $request): Business|JsonResponse
    {
        $b = $this->requireBusiness($request);
        return $b instanceof Business ? $b : response()->json(['error' => 'Business not found'], 404);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;

        $tables = RestaurantTable::where('business_id', $business->id)
            ->where('status', '!=', 'inactive')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id'       => (int) $t->id,
                'name'     => $t->name,
                'capacity' => $t->capacity,
                'status'   => $t->status,
            ]);

        $categories = $this->menu->categoriesForBusiness($business)
            ->load('menuItems')
            ->map(fn ($c) => [
                'id'    => (int) $c->id,
                'name'  => $c->name,
                'items' => $c->menuItems
                    ->filter(fn ($m) => $m->is_available)
                    ->values()
                    ->map(fn ($m) => [
                        'id'        => (int) $m->id,
                        'name'      => $m->name,
                        'price'     => (float) $m->price,
                        'image_url' => $m->image_url ?: null,
                    ]),
            ]);

        return response()->json([
            'data' => [
                'tables'     => $tables->values(),
                'categories' => $categories->values(),
                'currency'   => (string) (get_settings('business.currency', '', $business) ?: ''),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;

        $status = (string) $request->query('status', 'all');
        $search = (string) $request->query('q', '');
        $page   = max(1, (int) $request->query('page', 1));

        $query = Order::where('business_id', $business->id)->with(['table', 'items']);

        if ($status === 'open') {
            $query->whereNotIn('status', ['served', 'paid', 'cancelled']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($tableId = $request->query('table_id')) {
            $query->where('table_id', (int) $tableId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $perPage   = min(100, max(1, (int) $request->query('per_page', 25)));
        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);

        $statusCounts = Order::where('business_id', $business->id)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return response()->json([
            'data'          => $paginated->map(fn ($o) => $this->formatOrder($o))->values(),
            'meta'          => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ],
            'status_counts' => $statusCounts,
            'currency'      => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;

        $data = $request->validate([
            'order_type'           => ['required', 'in:dine_in,takeaway,delivery'],
            'table_id'             => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'customer_name'        => ['nullable', 'string', 'max:255'],
            'customer_phone'       => ['nullable', 'string', 'max:30'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['nullable', 'integer'],
            'items.*.product_id'   => ['nullable', 'integer'],
            'items.*.name'         => ['required', 'string', 'max:255'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'items.*.notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->orders->create($business, $data);
        $order->load(['table', 'items']);

        return response()->json(['data' => $this->formatOrder($order)], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $order->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $order->load(['table', 'items']);

        return response()->json(['data' => $this->formatOrder($order)]);
    }

    public function transition(Request $request, Order $order): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $order->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $newStatus = $request->validate(['status' => ['required', 'string']])['status'];

        if (! $this->orders->transitionStatus($order, $newStatus)) {
            return response()->json(['error' => "Cannot transition from {$order->status} to {$newStatus}"], 422);
        }

        $order->refresh()->load(['table', 'items']);

        return response()->json(['data' => $this->formatOrder($order)]);
    }

    public function itemStatuses(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;

        $ids = array_filter(array_map('intval', (array) $request->query('ids', [])));
        if (empty($ids)) return response()->json([]);

        $statuses = OrderItem::whereIn('id', $ids)
            ->whereHas('order', fn ($q) => $q->where('business_id', $business->id))
            ->pluck('status', 'id');

        return response()->json($statuses);
    }

    public function addItems(Request $request, Order $order): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $order->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            'items'                => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['nullable', 'integer'],
            'items.*.name'         => ['required', 'string', 'max:255'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'items.*.notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $newItems = [];
        foreach ($data['items'] as $line) {
            $this->orders->addItem($order, $line);
            $newItems[] = $order->items()->latest('id')->first();
        }

        return response()->json([
            'data' => [
                'items' => collect($newItems)->map(fn ($i) => [
                    'id'         => (int) $i->id,
                    'name'       => $i->name,
                    'quantity'   => (int) $i->quantity,
                    'unit_price' => round((float) $i->unit_price, 2),
                    'notes'      => $i->notes,
                    'status'     => $i->status,
                ])->values()->all(),
            ],
        ]);
    }

    public function updateItemStatus(Request $request, Order $order, OrderItem $item): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $order->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if ((int) $item->order_id !== (int) $order->id) {
            return response()->json(['error' => 'Item not in this order'], 404);
        }

        $status = $request->validate([
            'status' => ['required', 'in:pending,preparing,ready,served'],
        ])['status'];

        $item->update(['status' => $status]);

        return response()->json(['success' => true, 'status' => $status]);
    }

    public function deleteItem(Request $request, Order $order, OrderItem $item): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $order->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if ((int) $item->order_id !== (int) $order->id) {
            return response()->json(['error' => 'Item not in this order'], 404);
        }

        $item->delete();

        if ($order->items()->count() === 0) {
            $order->delete();
        }

        return response()->json(['success' => true]);
    }

    public function completeOrder(Request $request, Order $order): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $order->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            'payment_method'    => ['required', 'string', 'in:cash,card,transfer'],
            'amount_tendered'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $order->load('items');

        DB::transaction(function () use ($order, $business, $user, $data) {
            $orderTotal    = round((float) $order->total, 2);
            $paymentMethod = $data['payment_method'] === 'transfer' ? Sale::PAYMENT_CARD : $data['payment_method'];

            $maxSeq = 0;
            foreach ($business->sales()->whereNotNull('sale_number')->pluck('sale_number') as $num) {
                if (preg_match('/^POS-(\d+)$/', (string) $num, $m)) {
                    $maxSeq = max($maxSeq, (int) $m[1]);
                }
            }
            $saleNumber = 'POS-' . str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);

            $sale = $business->sales()->create([
                'user_id'           => $user->id,
                'sale_number'       => $saleNumber,
                'status'            => Sale::STATUS_COMPLETED,
                'payment_method'    => $paymentMethod,
                'channel'           => Sale::CHANNEL_RETAIL,
                'credit_account_id' => null,
                'subtotal'          => $orderTotal,
                'total'             => $orderTotal,
                'amount_paid'       => $orderTotal,
                'amount_tendered'   => $data['amount_tendered'] ?? $orderTotal,
                'change_amount'     => max(0, round(((float) ($data['amount_tendered'] ?? $orderTotal)) - $orderTotal, 2)),
                'notes'             => 'Restaurant Order #' . $order->order_number,
                'sold_at'           => now(),
                'is_settled'        => true,
                'settled_at'        => now(),
            ]);

            $sortOrder = 0;
            foreach ($order->items as $item) {
                SaleItem::create([
                    'pos_sale_id'     => $sale->id,
                    'product_name'    => $item->name,
                    'quantity'        => $item->quantity,
                    'unit_sell_price' => $item->unit_price,
                    'line_total'      => round((float) $item->unit_price * (float) $item->quantity, 2),
                    'sort_order'      => $sortOrder++,
                ]);
            }

            $order->update(['sale_id' => $sale->id, 'status' => 'paid']);

            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)->update(['status' => 'available']);
            }

            $this->payments->settle($sale, $business, $user, null, $orderTotal, $paymentMethod);
        });

        return response()->json(['success' => true]);
    }

    public function clearOrder(Request $request, Order $order): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $order->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $order->items()->delete();
        $order->delete();

        if ($order->table_id) {
            RestaurantTable::where('id', $order->table_id)->update(['status' => 'available']);
        }

        return response()->json(['success' => true]);
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id'             => (int) $order->id,
            'order_number'   => $order->order_number,
            'order_type'     => $order->order_type,
            'type_label'     => $order->typeLabel(),
            'status'         => $order->status,
            'status_color'   => $order->statusColor(),
            'table'          => $order->table
                ? ['id' => (int) $order->table->id, 'name' => $order->table->name]
                : null,
            'customer_name'  => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'notes'          => $order->notes,
            'subtotal'       => round((float) $order->subtotal, 2),
            'discount_amount'=> round((float) $order->discount_amount, 2),
            'total'          => round((float) $order->total, 2),
            'items'          => $order->items->map(fn ($i) => [
                'id'         => (int) $i->id,
                'name'       => $i->name,
                'quantity'   => (int) $i->quantity,
                'unit_price' => round((float) $i->unit_price, 2),
                'line_total' => $i->lineTotal(),
                'notes'      => $i->notes,
                'status'     => $i->status,
            ])->values()->all(),
            'created_at'     => $order->created_at?->toIso8601String(),
        ];
    }
}
