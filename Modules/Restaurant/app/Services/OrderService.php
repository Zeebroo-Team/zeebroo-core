<?php

namespace Modules\Restaurant\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use Modules\Pos\Services\SaleStockConsumptionService;
use Modules\Product\Models\Product;
use Modules\Restaurant\Models\Order;
use Modules\Restaurant\Models\OrderItem;
use Modules\Restaurant\Models\RestaurantTable;
use Modules\Restaurant\Services\IngredientStockService;

class OrderService
{
    public function __construct(
        private readonly SaleStockConsumptionService $stockConsumption,
    ) {}

    public function listForBusiness(Business $business, string $status = 'all', string $type = 'all'): LengthAwarePaginator
    {
        $query = Order::where('business_id', $business->id)->with(['table', 'items']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($type !== 'all') {
            $query->where('order_type', $type);
        }

        return $query->latest()->paginate(30);
    }

    public function create(Business $business, array $data): Order
    {
        $order = Order::create([
            'business_id'   => $business->id,
            'table_id'      => $data['table_id'] ?? null,
            'order_number'  => $this->generateOrderNumber($business),
            'order_type'    => $data['order_type'],
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone'=> $data['customer_phone'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'status'        => Order::STATUS_PENDING,
            'subtotal'      => 0,
            'discount_amount'=> 0,
            'total'         => 0,
        ]);

        $this->syncItems($order, $data['items'] ?? []);
        $this->recalculate($order);

        if ($order->table_id) {
            RestaurantTable::where('id', $order->table_id)->update(['status' => 'occupied']);
        }

        return $order;
    }

    public function addItem(Order $order, array $itemData): void
    {
        $productId = $itemData['product_id'] ?? null;
        $qty = max(1, (int) ($itemData['quantity'] ?? 1));

        OrderItem::create([
            'order_id'     => $order->id,
            'menu_item_id' => $itemData['menu_item_id'] ?? null,
            'product_id'   => $productId,
            'name'         => $itemData['name'],
            'quantity'     => $qty,
            'unit_price'   => $itemData['unit_price'],
            'notes'        => $itemData['notes'] ?? null,
            'status'       => $productId ? 'served' : 'pending',
        ]);

        if ($productId) {
            $product = Product::find($productId);
            if ($product) {
                $this->stockConsumption->consumeFifo($product, (float) $qty);
            }
        }

        $this->recalculate($order);
    }

    public function transitionStatus(Order $order, string $newStatus): bool
    {
        if (! $order->canTransitionTo($newStatus)) {
            return false;
        }

        $order->update(['status' => $newStatus]);

        if (in_array($newStatus, ['paid', 'served', 'cancelled'], true)) {
            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)->update(['status' => 'available']);
            }
        }

        if ($newStatus === 'served') {
            app(IngredientStockService::class)->deductForOrder($order);
        }

        return true;
    }

    public function linkSale(Order $order, int $saleId): void
    {
        $order->update(['sale_id' => $saleId, 'status' => Order::STATUS_PAID]);

        if ($order->table_id) {
            RestaurantTable::where('id', $order->table_id)->update(['status' => 'available']);
        }
    }

    private function syncItems(Order $order, array $lines): void
    {
        foreach ($lines as $line) {
            if (empty($line['name']) || (float) ($line['unit_price'] ?? 0) <= 0) {
                continue;
            }

            $productId = $line['product_id'] ?? null;
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            OrderItem::create([
                'order_id'     => $order->id,
                'menu_item_id' => $line['menu_item_id'] ?? null,
                'product_id'   => $productId,
                'name'         => $line['name'],
                'quantity'     => $qty,
                'unit_price'   => (float) $line['unit_price'],
                'notes'        => $line['notes'] ?? null,
                'status'       => $productId ? 'served' : 'pending',
            ]);

            if ($productId) {
                $product = Product::find($productId);
                if ($product) {
                    $this->stockConsumption->consumeFifo($product, (float) $qty);
                }
            }
        }
    }

    private function recalculate(Order $order): void
    {
        $order->refresh();
        $subtotal = $order->items->sum(fn ($i) => $i->unit_price * $i->quantity);

        $order->update([
            'subtotal' => $subtotal,
            'total'    => $subtotal - (float) $order->discount_amount,
        ]);
    }

    private function generateOrderNumber(Business $business): string
    {
        $prefix = 'ORD-' . date('ymd') . '-';
        $last   = Order::where('business_id', $business->id)
            ->where('order_number', 'like', $prefix . '%')
            ->max('order_number');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
