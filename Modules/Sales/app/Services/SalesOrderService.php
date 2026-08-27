<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Services\SaleStockConsumptionService;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;

class SalesOrderService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly SaleStockConsumptionService $stockConsumption,
    ) {}

    public function listForBusiness(
        Business $business,
        ?string $search = null,
        ?string $status = null,
    ): Collection {
        $query = SalesOrder::query()
            ->where('business_id', $business->id)
            ->with('customer');

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if (filled($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->orderByDesc('order_date')->orderByDesc('id')->get();
    }

    public function create(Business $business, array $data, array $items): SalesOrder
    {
        return DB::transaction(function () use ($business, $data, $items) {
            $order = SalesOrder::create([
                'business_id'            => $business->id,
                'branch_id'              => isset($data['branch_id']) && $data['branch_id'] ? (int) $data['branch_id'] : null,
                'order_number'           => $this->nextOrderNumber($business),
                'customer_id'            => isset($data['customer_id']) && $data['customer_id'] ? (int) $data['customer_id'] : null,
                'reference'              => filled($data['reference'] ?? '') ? $data['reference'] : null,
                'order_date'             => $data['order_date'],
                'expected_delivery_date' => filled($data['expected_delivery_date'] ?? '') ? $data['expected_delivery_date'] : null,
                'status'                 => SalesOrder::STATUS_PENDING,
                'notes'                  => filled($data['notes'] ?? '') ? $data['notes'] : null,
                'subtotal'               => 0,
                'discount_amount'        => 0,
                'tax_amount'             => 0,
                'total'                  => 0,
            ]);

            $this->syncItems($order, $items);
            $this->recalculateTotals($order, $data);

            return $order;
        });
    }

    public function update(SalesOrder $order, array $data, array $items): SalesOrder
    {
        return DB::transaction(function () use ($order, $data, $items) {
            $order->update([
                'customer_id'            => isset($data['customer_id']) && $data['customer_id'] ? (int) $data['customer_id'] : null,
                'reference'              => filled($data['reference'] ?? '') ? $data['reference'] : null,
                'order_date'             => $data['order_date'],
                'expected_delivery_date' => filled($data['expected_delivery_date'] ?? '') ? $data['expected_delivery_date'] : null,
                'notes'                  => filled($data['notes'] ?? '') ? $data['notes'] : null,
            ]);

            $this->syncItems($order, $items);
            $this->recalculateTotals($order, $data);

            return $order->fresh();
        });
    }

    /**
     * Confirming a sales order locks it in: it is converted into an Invoice
     * (unpaid — payment is collected separately) and stock is decremented
     * immediately, since the order is now committed against inventory.
     */
    public function confirm(SalesOrder $order): SalesOrder
    {
        if ($order->status !== SalesOrder::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Only pending orders can be confirmed.']);
        }

        return DB::transaction(function () use ($order) {
            $order->load('items');

            $items = $order->items->map(fn (SalesOrderItem $item) => [
                'item_type'   => $item->product_id ? 'product' : null,
                'product_id'  => $item->product_id,
                'description' => $item->description,
                'quantity'    => (float) $item->quantity,
                'unit_price'  => (float) $item->unit_price,
            ])->all();

            $invoice = $this->invoices->create($order->business, [
                'branch_id'       => $order->branch_id,
                'customer_id'     => $order->customer_id,
                'reference'       => $order->order_number,
                'issue_date'      => now()->toDateString(),
                'notes'           => $order->notes,
                'discount_amount' => $order->discount_amount,
                'tax_amount'      => $order->tax_amount,
            ], $items);

            $invoice->load('items.product');
            foreach ($invoice->items as $item) {
                if (!$item->product_id || !$item->product) {
                    continue;
                }

                $qty = max(0.0, (float) $item->quantity);
                if ($qty <= 0) {
                    continue;
                }

                $this->stockConsumption->consumeFifo($item->product, $qty);
            }

            $order->update([
                'status'     => SalesOrder::STATUS_CONFIRMED,
                'invoice_id' => $invoice->id,
            ]);

            return $order->fresh();
        });
    }

    public function process(SalesOrder $order): SalesOrder
    {
        $order->update(['status' => SalesOrder::STATUS_PROCESSING]);
        return $order;
    }

    public function complete(SalesOrder $order): SalesOrder
    {
        $order->update(['status' => SalesOrder::STATUS_COMPLETED]);
        return $order;
    }

    public function cancel(SalesOrder $order): SalesOrder
    {
        $order->update(['status' => SalesOrder::STATUS_CANCELLED]);
        return $order;
    }

    public function delete(SalesOrder $order): void
    {
        DB::transaction(fn () => $order->delete());
    }

    private function syncItems(SalesOrder $order, array $items): void
    {
        $order->items()->delete();
        foreach ($items as $idx => $item) {
            $qty       = max(0.001, (float) ($item['quantity']  ?? 1));
            $unitPrice = max(0,     (float) ($item['unit_price'] ?? 0));
            SalesOrderItem::create([
                'sales_order_id' => $order->id,
                'product_id'     => isset($item['product_id']) && $item['product_id'] ? (int) $item['product_id'] : null,
                'description'    => $item['description'] ?? null,
                'quantity'       => $qty,
                'unit_price'     => $unitPrice,
                'line_total'     => round($qty * $unitPrice, 2),
                'sort_order'     => $idx,
            ]);
        }
    }

    private function recalculateTotals(SalesOrder $order, array $data): void
    {
        $subtotal = $order->items()->sum(DB::raw('quantity * unit_price'));
        $discount = max(0, (float) ($data['discount_amount'] ?? 0));
        $tax      = max(0, (float) ($data['tax_amount']      ?? 0));

        $order->update([
            'subtotal'        => round($subtotal, 2),
            'discount_amount' => round($discount, 2),
            'tax_amount'      => round($tax, 2),
            'total'           => round(max(0, $subtotal - $discount + $tax), 2),
        ]);
    }

    private function nextOrderNumber(Business $business): string
    {
        $last = SalesOrder::where('business_id', $business->id)
            ->orderByDesc('id')->value('order_number');

        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return 'SO-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
