<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Customer;
use Modules\Sales\Http\Controllers\Concerns\ResolvesSalesBusiness;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesOrderService;

class SalesOrderController extends Controller
{
    use ResolvesSalesBusiness;

    public function __construct(
        private readonly SalesOrderService $orderService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search       = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', 'all');
        $orders       = $this->orderService->listForBusiness($business, $search !== '' ? $search : null, $statusFilter);

        return view('sales::orders.index', [
            'business'     => $business,
            'hasOrders'    => SalesOrder::where('business_id', $business->id)->exists(),
            'orders'       => $orders,
            'customers'    => Customer::where('business_id', $business->id)->orderBy('name')->get(),
            'currency'     => (string) (get_settings('business.currency', '', $business) ?: ''),
            'search'       => $search,
            'statusFilter' => $statusFilter,
            'statusTabs'   => $this->statusTabs(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $order = $this->orderService->create(
                $business,
                $this->validatedHeader($request, $business),
                $request->input('items', []),
            );
        } catch (ValidationException $e) {
            return redirect()->route('sales.orders.index')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('sales.orders.show', $order)
            ->with('status', 'Order ' . $order->order_number . ' created.');
    }

    public function show(Request $request, SalesOrder $order): View|RedirectResponse
    {
        $business = $this->requireOrder($request, $order);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $order->load(['customer', 'items.product', 'invoice']);

        return view('sales::orders.show', [
            'business' => $business,
            'order'    => $order,
            'currency' => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function edit(Request $request, SalesOrder $order): View|RedirectResponse
    {
        $business = $this->requireOrder($request, $order);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if (!$order->isEditable()) {
            return redirect()->route('sales.orders.show', $order)
                ->withErrors(['order' => 'This order can no longer be edited.']);
        }

        $order->load(['customer', 'items.product']);

        return view('sales::orders.edit', [
            'business'  => $business,
            'order'     => $order,
            'customers' => Customer::where('business_id', $business->id)->orderBy('name')->get(),
            'currency'  => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function update(Request $request, SalesOrder $order): RedirectResponse
    {
        $business = $this->requireOrder($request, $order);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if (!$order->isEditable()) {
            return redirect()->route('sales.orders.show', $order)
                ->withErrors(['order' => 'This order can no longer be edited.']);
        }

        try {
            $this->orderService->update(
                $order,
                $this->validatedHeader($request, $business),
                $request->input('items', []),
            );
        } catch (ValidationException $e) {
            return redirect()->route('sales.orders.edit', $order)
                ->withErrors($e->errors())->withInput();
        }

        return redirect()->route('sales.orders.show', $order)
            ->with('status', 'Order updated.');
    }

    public function confirm(Request $request, SalesOrder $order): RedirectResponse
    {
        $business = $this->requireOrder($request, $order);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $order = $this->orderService->confirm($order);
        } catch (ValidationException $e) {
            return redirect()->route('sales.orders.show', $order)->withErrors($e->errors());
        }

        $order->load('invoice');

        return redirect()->route('sales.orders.show', $order)
            ->with('status', 'Order confirmed and converted to invoice ' . ($order->invoice?->invoice_number ?? '') . '.');
    }

    public function process(Request $request, SalesOrder $order): RedirectResponse
    {
        $business = $this->requireOrder($request, $order);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->orderService->process($order);

        return redirect()->route('sales.orders.show', $order)->with('status', 'Order marked as processing.');
    }

    public function complete(Request $request, SalesOrder $order): RedirectResponse
    {
        $business = $this->requireOrder($request, $order);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->orderService->complete($order);

        return redirect()->route('sales.orders.show', $order)->with('status', 'Order completed.');
    }

    public function cancel(Request $request, SalesOrder $order): RedirectResponse
    {
        $business = $this->requireOrder($request, $order);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->orderService->cancel($order);

        return redirect()->route('sales.orders.show', $order)->with('status', 'Order cancelled.');
    }

    public function destroy(Request $request, SalesOrder $order): RedirectResponse
    {
        $business = $this->requireOrder($request, $order);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if (!in_array($order->status, [SalesOrder::STATUS_PENDING, SalesOrder::STATUS_CANCELLED], true)) {
            return redirect()->route('sales.orders.show', $order)
                ->withErrors(['order' => 'Only pending or cancelled orders can be deleted.']);
        }

        $this->orderService->delete($order);

        return redirect()->route('sales.orders.index')->with('status', 'Order deleted.');
    }

    private function requireOrder(Request $request, SalesOrder $order): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $order->business_id === (int) $business->id, 404);

        return $business;
    }

    private function validatedHeader(Request $request, Business $business): array
    {
        return $request->validate([
            'customer_id'             => ['nullable', 'integer', Rule::exists('pos_customers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'reference'               => ['nullable', 'string', 'max:120'],
            'order_date'              => ['required', 'date'],
            'expected_delivery_date'  => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes'                   => ['nullable', 'string', 'max:5000'],
            'discount_amount'         => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'tax_amount'              => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.product_id'      => ['nullable', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'items.*.description'     => ['nullable', 'string', 'max:255'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);
    }

    private function statusTabs(): array
    {
        return [
            'all'                        => 'All',
            SalesOrder::STATUS_PENDING    => 'Pending',
            SalesOrder::STATUS_CONFIRMED  => 'Confirmed',
            SalesOrder::STATUS_PROCESSING => 'Processing',
            SalesOrder::STATUS_COMPLETED  => 'Completed',
            SalesOrder::STATUS_CANCELLED  => 'Cancelled',
        ];
    }
}
