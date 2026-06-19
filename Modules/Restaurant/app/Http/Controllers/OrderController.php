<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        private readonly OrderService $orders,
        private readonly MenuService  $menu,
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
            'categories' => $this->menu->categoriesForBusiness($business)->load('menuItems'),
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

        // Force to paid regardless of current status (POS checkout bypasses the kitchen chain)
        $order->update(['status' => 'paid']);

        if ($order->table_id) {
            \Modules\Restaurant\Models\RestaurantTable::where('id', $order->table_id)
                ->update(['status' => 'available']);
        }

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
