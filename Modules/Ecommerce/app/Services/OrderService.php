<?php

namespace Modules\Ecommerce\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Ecommerce\Models\Listing;
use Modules\Ecommerce\Models\Order;
use Modules\Ecommerce\Models\Store;

class OrderService
{
    public function __construct(private readonly PaymentGatewayService $gateways) {}

    public function list(Business $business, array $filters = []): LengthAwarePaginator
    {
        $status = (string) ($filters['status'] ?? '');
        $search = trim((string) ($filters['search'] ?? ''));

        return Order::query()
            ->where('business_id', $business->id)
            ->withCount('items')
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w->where('order_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%"));
            })
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    public function update(Order $order, array $data): Order
    {
        $order->fill($data)->save();

        return $order->fresh('items');
    }

    /**
     * Place a storefront order. Prices are always re-read from the published
     * listings — the browser only sends listing ids and quantities.
     *
     * @param array<int, array{listing_id:int, quantity:numeric}> $lines
     */
    public function place(Store $store, array $customer, array $lines, string $paymentMethod): Order
    {
        $qtyByListing = [];
        foreach ($lines as $line) {
            $id  = (int) ($line['listing_id'] ?? 0);
            $qty = (float) ($line['quantity'] ?? 0);
            if ($id > 0 && $qty > 0) {
                $qtyByListing[$id] = ($qtyByListing[$id] ?? 0) + $qty;
            }
        }
        if (! $qtyByListing) {
            throw ValidationException::withMessages(['items' => 'Your cart is empty.']);
        }

        $listings = Listing::query()
            ->where('business_id', $store->business_id)
            ->where('is_published', true)
            ->whereIn('id', array_keys($qtyByListing))
            ->whereHas('product', fn ($p) => $p->where('is_active', true))
            ->with('product')
            ->get();

        if ($listings->count() !== count($qtyByListing)) {
            throw ValidationException::withMessages(['items' => 'Some items in your cart are no longer available. Please refresh and try again.']);
        }

        if (! in_array($paymentMethod, $this->allowedMethods($store), true)) {
            throw ValidationException::withMessages(['payment_method' => 'That payment method is not available.']);
        }

        $items    = [];
        $subtotal = 0.0;
        foreach ($listings as $l) {
            $qty        = $qtyByListing[$l->id];
            $price      = $l->price();
            $lineTotal  = round($price * $qty, 2);
            $subtotal  += $lineTotal;
            $items[]    = [
                'product_id' => $l->product_id,
                'name'       => $l->displayTitle(),
                'sku'        => $l->product?->sku,
                'unit_price' => $price,
                'quantity'   => $qty,
                'line_total' => $lineTotal,
            ];
        }
        $subtotal = round($subtotal, 2);

        if ($store->min_order_amount !== null && $subtotal < (float) $store->min_order_amount) {
            throw ValidationException::withMessages(['items' => 'The minimum order amount is ' . number_format((float) $store->min_order_amount, 2) . '.']);
        }

        $shipping = round($store->shippingFor($subtotal), 2);

        return DB::transaction(function () use ($store, $customer, $items, $subtotal, $shipping, $paymentMethod) {
            $order = Order::create([
                'business_id'      => $store->business_id,
                'order_no'         => $this->nextOrderNo($store->business_id),
                'access_token'     => Str::random(48),
                'customer_name'    => $customer['name'],
                'customer_email'   => $customer['email'] ?? null,
                'customer_phone'   => $customer['phone'],
                'shipping_address' => $customer['address'],
                'shipping_city'    => $customer['city'] ?? null,
                'notes'            => $customer['notes'] ?? null,
                'status'           => 'pending',
                'payment_method'   => $paymentMethod,
                'payment_status'   => 'unpaid',
                'subtotal'         => $subtotal,
                'shipping_fee'     => $shipping,
                'total'            => round($subtotal + $shipping, 2),
                'placed_at'        => now(),
            ]);
            $order->items()->createMany($items);

            return $order->load('items');
        });
    }

    /** @return string[] payment methods the storefront currently offers */
    public function allowedMethods(Store $store): array
    {
        return array_merge(
            array_keys(array_filter([
                'cod'           => $store->cod_enabled,
                'bank_transfer' => $store->bank_transfer_enabled,
            ])),
            $this->gateways->usableKeys($store->business_id),
        );
    }

    private function nextOrderNo(int $businessId): string
    {
        // Lock this business's order rows so two concurrent checkouts can't draw the same number.
        $seq = Order::where('business_id', $businessId)->lockForUpdate()->count() + 1;

        do {
            $no = 'WEB-' . str_pad((string) $seq++, 6, '0', STR_PAD_LEFT);
        } while (Order::where('business_id', $businessId)->where('order_no', $no)->exists());

        return $no;
    }

    public function format(Order $o, bool $withItems = false): array
    {
        $data = [
            'id'               => $o->id,
            'order_no'         => $o->order_no,
            'customer_name'    => $o->customer_name,
            'customer_email'   => $o->customer_email,
            'customer_phone'   => $o->customer_phone,
            'shipping_address' => $o->shipping_address,
            'shipping_city'    => $o->shipping_city,
            'notes'            => $o->notes,
            'status'           => $o->status,
            'payment_method'   => $o->payment_method,
            'payment_status'   => $o->payment_status,
            'payment_reference' => $o->payment_reference,
            'paid_at'          => $o->paid_at?->toIso8601String(),
            'subtotal'         => (float) $o->subtotal,
            'shipping_fee'     => (float) $o->shipping_fee,
            'total'            => (float) $o->total,
            'tracking_no'      => $o->tracking_no,
            'internal_note'    => $o->internal_note,
            'items_count'      => $o->items_count ?? $o->items?->count(),
            'placed_at'        => $o->placed_at?->toIso8601String(),
        ];

        if ($withItems) {
            $data['items'] = $o->items->map(fn ($i) => [
                'id'         => $i->id,
                'product_id' => $i->product_id,
                'name'       => $i->name,
                'sku'        => $i->sku,
                'unit_price' => (float) $i->unit_price,
                'quantity'   => (float) $i->quantity,
                'line_total' => (float) $i->line_total,
            ])->values();
        }

        return $data;
    }
}
