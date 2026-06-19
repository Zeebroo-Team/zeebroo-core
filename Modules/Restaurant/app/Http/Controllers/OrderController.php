<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Account\Models\Account;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleItem;
use Modules\Pos\Services\SalePaymentSettlementService;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Modules\Restaurant\Models\Order;
use Modules\Restaurant\Models\OrderItem;
use Modules\Restaurant\Models\RestaurantTable;
use Modules\Restaurant\Services\MenuService;
use Modules\Restaurant\Services\OrderService;

class OrderController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function __construct(
        private readonly OrderService                 $orders,
        private readonly MenuService                  $menu,
        private readonly SalePaymentSettlementService $payments,
    ) {}

    public function index(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $status = (string) $request->query('status', 'all');
        $type   = (string) $request->query('type', 'all');

        $statusCounts = Order::where('business_id', $business->id)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('restaurant::orders.index', [
            'business'     => $business,
            'orders'       => $this->orders->listForBusiness($business, $status, $type),
            'statusCounts' => $statusCounts,
            'status'       => $status,
            'type'         => $type,
            'currency'     => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function create(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        return view('restaurant::orders.create', [
            'business'   => $business,
            'tables'     => RestaurantTable::where('business_id', $business->id)->where('status', '!=', 'inactive')->orderBy('name')->get(),
            'categories' => $this->menu->categoriesForBusiness($business)->load(['menuItems', 'menuItems.imageFile']),
            'accounts'   => Account::where('business_id', $business->id)->orderBy('account_name')->get(['id', 'account_name', 'category']),
            'currency'   => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $request->validate([
            'order_type'     => ['required', 'in:dine_in,takeaway,delivery'],
            'table_id'       => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'customer_name'  => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'items'          => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['nullable', 'integer'],
            'items.*.name'         => ['required', 'string', 'max:255'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'items.*.notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->orders->create($business, $data);

        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'order_id' => $order->id,
                'items'    => $order->items->map(fn($i) => [
                    'id'     => $i->id,
                    'name'   => $i->name,
                    'status' => $i->status ?? 'pending',
                ])->values(),
                'message'  => 'Order #' . $order->id . ' placed successfully.',
            ]);
        }

        return redirect()->route('restaurant.orders.show', $order)->with('status', 'Order created.');
    }

    public function show(Request $request, Order $order): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $order->business_id === (int) $business->id, 404);

        return view('restaurant::orders.show', [
            'business' => $business,
            'order'    => $order->load(['table', 'items', 'sale']),
            'currency' => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function transition(Request $request, Order $order): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $order->business_id === (int) $business->id, 404);

        $newStatus = (string) $request->input('status', '');
        $this->orders->transitionStatus($order, $newStatus);

        return redirect()->route('restaurant.orders.show', $order)->with('status', 'Order status updated.');
    }

    public function deleteItem(Request $request, Order $order, OrderItem $item): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) abort(403);
        abort_unless((int) $order->business_id === (int) $business->id, 403);
        abort_unless((int) $item->order_id === (int) $order->id, 404);

        $item->delete();

        // If no items remain, delete the order too
        if ($order->items()->count() === 0) {
            $order->delete();
        }

        return response()->json(['success' => true]);
    }

    public function updateItemStatus(Request $request, Order $order, OrderItem $item): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) abort(403);
        abort_unless((int) $order->business_id === (int) $business->id, 403);
        abort_unless((int) $item->order_id === (int) $order->id, 404);

        $status = $request->validate([
            'status' => ['required', 'in:pending,preparing,ready,served'],
        ])['status'];

        $item->update(['status' => $status]);

        return response()->json(['success' => true, 'status' => $status]);
    }

    public function kitchen(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $orders = Order::where('business_id', $business->id)
            ->whereNotIn('status', ['paid', 'cancelled', 'served'])
            ->whereDate('created_at', today())
            ->with(['table', 'items'])
            ->orderBy('created_at', 'asc')
            ->get();

        return view('restaurant::orders.kitchen', [
            'business' => $business,
            'orders'   => $orders,
            'currency' => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function itemStatuses(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) abort(403);

        $ids = array_filter(array_map('intval', (array) $request->query('ids', [])));
        if (empty($ids)) return response()->json([]);

        $statuses = OrderItem::whereIn('id', $ids)
            ->whereHas('order', fn($q) => $q->where('business_id', $business->id))
            ->pluck('status', 'id');

        return response()->json($statuses);
    }

    public function completeOrder(Request $request, Order $order): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) abort(403);
        abort_unless((int) $order->business_id === (int) $business->id, 403);

        $data = $request->validate([
            'payment_method'   => ['required', 'string', 'in:cash,card,transfer'],
            'credit_account_id'=> ['required', 'integer', 'exists:accounts,id'],
            'amount_tendered'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();

        $order->load('items');

        DB::transaction(function () use ($order, $business, $user, $data) {
            $orderTotal = round((float) $order->total, 2);

            // Map frontend method names to POS constants
            $paymentMethod = $data['payment_method'] === 'transfer' ? Sale::PAYMENT_CARD : $data['payment_method'];

            // Generate next sale number (simple lock-safe approach)
            $maxSeq = 0;
            $existing = $business->sales()->whereNotNull('sale_number')->pluck('sale_number');
            foreach ($existing as $num) {
                if (preg_match('/^POS-(\d+)$/', (string) $num, $m)) {
                    $maxSeq = max($maxSeq, (int) $m[1]);
                }
            }
            $saleNumber = 'POS-' . str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);

            // Create the POS sale record
            $sale = $business->sales()->create([
                'user_id'          => $user->id,
                'sale_number'      => $saleNumber,
                'status'           => Sale::STATUS_COMPLETED,
                'payment_method'   => $paymentMethod,
                'channel'          => Sale::CHANNEL_RETAIL,
                'credit_account_id'=> $data['credit_account_id'],
                'subtotal'         => $orderTotal,
                'total'            => $orderTotal,
                'amount_paid'      => $orderTotal,
                'amount_tendered'  => $data['amount_tendered'] ?? $orderTotal,
                'change_amount'    => max(0, round(((float) ($data['amount_tendered'] ?? $orderTotal)) - $orderTotal, 2)),
                'notes'            => 'Restaurant Order #' . $order->order_number,
                'sold_at'          => now(),
                'is_settled'       => true,
                'settled_at'       => now(),
            ]);

            // Create sale line items from order items
            $sortOrder = 0;
            foreach ($order->items as $item) {
                $lineTotal = round((float) $item->unit_price * (float) $item->quantity, 2);
                SaleItem::create([
                    'pos_sale_id'    => $sale->id,
                    'product_name'   => $item->name,
                    'quantity'       => $item->quantity,
                    'unit_sell_price'=> $item->unit_price,
                    'line_total'     => $lineTotal,
                    'sort_order'     => $sortOrder++,
                ]);
            }

            // Link sale to order and mark paid
            $order->update(['sale_id' => $sale->id, 'status' => 'paid']);

            // Free the table
            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)->update(['status' => 'available']);
            }

            // Settle payment: updates account balance + creates LedgerTransaction
            $this->payments->settle($sale, $business, $user, (int) $data['credit_account_id'], $orderTotal, $paymentMethod);
        });

        return response()->json(['success' => true]);
    }

    public function clearOrder(Request $request, Order $order): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) abort(403);
        abort_unless((int) $order->business_id === (int) $business->id, 403);

        $order->items()->delete();
        $order->delete();

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $order->business_id === (int) $business->id, 404);
        abort_unless(in_array($order->status, ['pending', 'cancelled'], true), 403);

        $order->delete();

        return redirect()->route('restaurant.orders.index')->with('status', 'Order deleted.');
    }
}
