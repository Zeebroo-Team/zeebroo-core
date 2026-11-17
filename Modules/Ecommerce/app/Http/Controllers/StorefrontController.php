<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Ecommerce\Models\Order;
use Modules\Ecommerce\Models\PaymentGateway;
use Modules\Ecommerce\Models\Store;
use Modules\Ecommerce\Services\ListingService;
use Modules\Ecommerce\Services\OrderService;
use Modules\Ecommerce\Services\PaymentGatewayService;
use Modules\Ecommerce\Services\SlideService;

/** Public, unauthenticated storefront: /shop/{slug}. */
class StorefrontController extends Controller
{
    private const OFFLINE_LABELS = ['cod' => 'Cash on delivery', 'bank_transfer' => 'Bank transfer'];

    public function __construct(
        private readonly ListingService        $listings,
        private readonly OrderService          $orders,
        private readonly PaymentGatewayService $gateways,
        private readonly SlideService          $slides,
    ) {}

    public function show(string $slug): View
    {
        $store = Store::where('slug', $slug)->with('business')->firstOrFail();

        $products = $store->is_published
            ? $this->listings->storefront($store->business)->map(fn ($l) => [
                'id'               => $l->id,
                'title'            => $l->displayTitle(),
                'description'      => $l->displayDescription(),
                'image_url'        => $l->product?->imageUrl(),
                'price'            => $l->price(),
                'compare_at_price' => $l->compare_at_price !== null ? (float) $l->compare_at_price : null,
                'is_featured'      => $l->is_featured,
                'in_stock'         => (float) ($l->product?->stock_quantity ?? 0) > 0,
            ])->values()
            : collect();

        return view('ecommerce::storefront', [
            'store'      => $store,
            'template'   => isset(Store::TEMPLATES[$store->template]) ? $store->template : 'classic',
            'products'   => $products,
            'slides'     => $store->is_published ? $this->slides->list($store->business_id, true) : collect(),
            'payMethods' => $this->paymentOptions($store),
        ]);
    }

    public function checkout(Request $request, string $slug): JsonResponse
    {
        $store = Store::where('slug', $slug)->where('is_published', true)->with('business')->firstOrFail();

        $validated = $request->validate([
            'name'                 => 'required|string|max:150',
            'email'                => 'nullable|email|max:150',
            'phone'                => 'required|string|max:40',
            'address'              => 'required|string|max:500',
            'city'                 => 'nullable|string|max:120',
            'notes'                => 'nullable|string|max:1000',
            'payment_method'       => 'required|string|in:' . implode(',', Order::PAYMENT_METHODS),
            'items'                => 'required|array|min:1|max:100',
            'items.*.listing_id'   => 'required|integer',
            'items.*.quantity'     => 'required|numeric|min:1|max:999',
        ]);

        // Order + gateway session are created together: if the gateway call fails the order is rolled back.
        [$order, $payment] = DB::transaction(function () use ($store, $validated) {
            $order   = $this->orders->place($store, $validated, $validated['items'], $validated['payment_method']);
            $payment = in_array($order->payment_method, Order::ONLINE_METHODS, true)
                ? $this->gateways->start($store, $order)
                : null;

            return [$order, $payment];
        });

        return response()->json(['data' => [
            'order_no'       => $order->order_no,
            'total'          => (float) $order->total,
            'payment_method' => $order->payment_method,
            'order_url'      => route('ecommerce.storefront.order', ['slug' => $store->slug, 'token' => $order->access_token]),
            'payment'        => $payment,
        ]], 201);
    }

    /** Confirmation page — also the return URL for Stripe & PayHere. */
    public function order(Request $request, string $slug, string $token): View
    {
        $store = Store::where('slug', $slug)->with('business')->firstOrFail();
        $order = Order::where('business_id', $store->business_id)->where('access_token', $token)->with('items')->firstOrFail();

        if ($order->payment_method === 'stripe' && $request->filled('session_id')) {
            $this->gateways->confirmStripe($store, $order, (string) $request->query('session_id'));
            $order->refresh();
        }

        return view('ecommerce::order', [
            'store'     => $store,
            'template'  => isset(Store::TEMPLATES[$store->template]) ? $store->template : 'classic',
            'order'     => $order,
            'cancelled' => $request->boolean('cancelled'),
            'methodLabel' => self::OFFLINE_LABELS[$order->payment_method] ?? (PaymentGateway::CATALOG[$order->payment_method]['name'] ?? $order->payment_method),
        ]);
    }

    public function payhereNotify(Request $request, string $slug): Response
    {
        $store = Store::where('slug', $slug)->with('business')->firstOrFail();
        $ok    = $this->gateways->handlePayhereNotify($store, $request->all());

        return response($ok ? 'OK' : 'INVALID', $ok ? 200 : 400);
    }

    /** @return array<int, array{key:string,label:string,online:bool}> */
    private function paymentOptions(Store $store): array
    {
        return collect($this->orders->allowedMethods($store))->map(fn ($k) => [
            'key'    => $k,
            'label'  => self::OFFLINE_LABELS[$k] ?? ('Pay online — ' . PaymentGateway::CATALOG[$k]['name']),
            'online' => in_array($k, Order::ONLINE_METHODS, true),
        ])->values()->all();
    }
}
