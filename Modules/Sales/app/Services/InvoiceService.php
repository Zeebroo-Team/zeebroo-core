<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AutomationEditor\Services\AutomationRunnerService;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\CustomerSubscriptionService;
use Modules\Pos\Services\SaleStockConsumptionService;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceItem;

class InvoiceService
{
    public function __construct(
        private readonly SaleStockConsumptionService $stockConsumption,
        private readonly CustomerSubscriptionService $subscriptions,
    ) {}

    public function listForBusiness(
        Business $business,
        ?string $search = null,
        ?string $status = null,
        ?int $customerId = null,
    ): Collection {
        $query = Invoice::query()
            ->select('invoices.*')
            ->selectRaw(
                '(SELECT COUNT(DISTINCT proposal_group) FROM design_studio_designs'.
                ' WHERE invoice_id = invoices.id AND proposal_group IS NOT NULL) as proposal_count'
            )
            ->where('business_id', $business->id)
            ->with('customer');

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if (filled($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        return $query->orderByDesc('issue_date')->orderByDesc('id')->get();
    }

    public function businessHasInvoices(Business $business): bool
    {
        return Invoice::query()->where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data, array $items): Invoice
    {
        $invoice = DB::transaction(function () use ($business, $data, $items) {
            $invoice = Invoice::create([
                'business_id'     => $business->id,
                'branch_id'       => $this->nullableInt($data['branch_id'] ?? null),
                'invoice_number'  => $this->nextInvoiceNumber($business),
                'customer_id'     => $this->nullableInt($data['customer_id'] ?? null),
                'reference'       => filled($data['reference'] ?? '') ? $data['reference'] : null,
                'issue_date'      => $data['issue_date'],
                'due_date'        => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
                'status'          => Invoice::STATUS_DRAFT,
                'notes'           => filled($data['notes'] ?? '') ? $data['notes'] : null,
                'payment_method'  => filled($data['payment_method'] ?? '') ? $data['payment_method'] : null,
                'subtotal'        => 0,
                'discount_amount' => 0,
                'tax_amount'      => 0,
                'total'           => 0,
            ]);

            $this->syncItems($invoice, $items);
            $this->recalculateTotals($invoice, $data);

            return $invoice;
        });

        $invoice->loadMissing('customer');
        app(AutomationRunnerService::class)->dispatch('invoice.created', $business, $this->invoicePayload($invoice));

        return $invoice;
    }

    /**
     * Auto-creates a formal Invoice from a completed POS sale, mirroring the
     * Electron app's "receipt_mode: invoice" flow (`_posCreateInvoiceFromSale`
     * in `electron_app/renderer/js/app.js`): one custom line per sale item at
     * its pre-discount unit price (so the discount is visible on the invoice
     * totals), sale-level + folded per-item discounts as the header discount,
     * and cash/card sales marked paid immediately.
     */
    public function createFromPosSale(Sale $sale): Invoice
    {
        $sale->loadMissing(['items.productRental', 'items.subscription']);

        $items = $sale->items->map(fn ($item) => [
            'item_type'  => 'custom',
            'description' => $item->product_name,
            'quantity'    => (float) $item->quantity,
            'unit_price'  => (float) $item->unit_sell_price + (float) $item->discount_amount,
            'rental_daily_rate'          => $item->productRental?->daily_rate,
            'rental_return_date'         => $item->productRental?->due_at?->toDateString(),
            'rental_late_fee_multiplier' => $item->productRental?->late_fee_multiplier,
            'warranty_type'   => $item->warranty_type,
            'warranty_date'   => $item->warranty_expires_at?->toDateString(),
            'is_subscription' => (bool) $item->subscription,
            'subscription_period' => $item->subscription?->recurring_period,
        ])->all();

        $itemDiscountsTotal = $sale->items->sum(fn ($item) => (float) $item->discount_amount * (float) $item->quantity);
        $totalDiscount = round((float) $sale->discount_amount + $itemDiscountsTotal, 2);

        $invoice = $this->create($sale->business, [
            'customer_id'     => $sale->pos_customer_id,
            'reference'       => $sale->sale_number,
            'issue_date'      => now()->toDateString(),
            'due_date'        => null,
            'notes'           => $sale->notes,
            'payment_method'  => $sale->payment_method,
            'discount_amount' => $totalDiscount,
            'tax_amount'      => 0,
        ], $items);

        if ($sale->payment_method !== Sale::PAYMENT_CREDIT) {
            $invoice = $this->markPaid($invoice);
        }

        return $invoice;
    }

    public function update(Invoice $invoice, array $data, array $items): Invoice
    {
        if (!$invoice->isEditable()) {
            throw ValidationException::withMessages(['invoice' => 'This invoice can no longer be edited.']);
        }

        return DB::transaction(function () use ($invoice, $data, $items) {
            $invoice->update([
                'branch_id'   => $this->nullableInt($data['branch_id'] ?? null),
                'customer_id' => $this->nullableInt($data['customer_id'] ?? null),
                'reference'   => filled($data['reference'] ?? '') ? $data['reference'] : null,
                'issue_date'  => $data['issue_date'],
                'due_date'       => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
                'notes'          => filled($data['notes'] ?? '') ? $data['notes'] : null,
                'payment_method' => filled($data['payment_method'] ?? '') ? $data['payment_method'] : null,
            ]);

            $this->syncItems($invoice, $items);
            $this->recalculateTotals($invoice, $data);

            return $invoice->fresh();
        });
    }

    public function markSent(Invoice $invoice): Invoice
    {
        if ($invoice->status === Invoice::STATUS_DRAFT) {
            $invoice->update(['status' => Invoice::STATUS_SENT]);
        }

        return $invoice;
    }

    public function markPaid(Invoice $invoice): Invoice
    {
        if (!in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_OVERDUE])) {
            return $invoice;
        }

        $business = $invoice->business ?? Business::find($invoice->business_id);

        $invoice = DB::transaction(function () use ($invoice, $business): Invoice {
            $invoice->update(['status' => Invoice::STATUS_PAID]);

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

                if ($business && $item->product->is_subscription) {
                    $this->subscriptions->createForInvoiceLine(
                        $business,
                        $item->product,
                        $invoice->id,
                        $item->id,
                        $invoice->customer_id,
                        (float) $item->unit_price,
                        $qty,
                    );
                }
            }

            return $invoice;
        });

        $invoice->loadMissing('customer');
        if ($business) {
            app(AutomationRunnerService::class)->dispatch('invoice.paid', $business, $this->invoicePayload($invoice));
        }

        return $invoice;
    }

    public function markOverdue(Invoice $invoice): Invoice
    {
        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT])) {
            $invoice->update(['status' => Invoice::STATUS_OVERDUE]);
        }

        return $invoice;
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->status !== Invoice::STATUS_PAID) {
            $invoice->update(['status' => Invoice::STATUS_CANCELLED]);
        }

        return $invoice;
    }

    public function enableShare(Invoice $invoice): Invoice
    {
        if (!$invoice->share_token) {
            $invoice->update(['share_token' => bin2hex(random_bytes(24))]);
        }

        return $invoice;
    }

    public function disableShare(Invoice $invoice): Invoice
    {
        $invoice->update(['share_token' => null]);

        return $invoice;
    }

    public function delete(Invoice $invoice): void
    {
        if ($invoice->status === Invoice::STATUS_PAID) {
            throw ValidationException::withMessages(['invoice' => 'Paid invoices cannot be deleted.']);
        }

        $invoice->delete();
    }

    public function invoiceForBusiness(Business $business, Invoice $invoice): ?Invoice
    {
        return $invoice->business_id === $business->id ? $invoice : null;
    }

    private function invoicePayload(Invoice $invoice): array
    {
        $customer = $invoice->customer;
        return [
            'event'    => 'invoice.created',
            'invoice'  => [
                'id'             => $invoice->id,
                'reference'      => $invoice->invoice_number,
                'total'          => (float) $invoice->total,
                'subtotal'       => (float) $invoice->subtotal,
                'tax_amount'     => (float) $invoice->tax_amount,
                'discount_amount'=> (float) $invoice->discount_amount,
                'status'         => $invoice->status,
                'due_date'       => $invoice->due_date?->toDateString(),
                'issue_date'     => $invoice->issue_date,
                'created_at'     => $invoice->created_at?->toIso8601String(),
            ],
            'customer' => $customer ? [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ] : [],
        ];
    }

    private function nextInvoiceNumber(Business $business): string
    {
        $last = Invoice::query()
            ->where('business_id', $business->id)
            ->whereNotNull('invoice_number')
            ->orderByDesc('id')
            ->value('invoice_number');

        $seq = 1;
        if ($last && preg_match('/INV-(\d+)$/i', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'INV-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function syncItems(Invoice $invoice, array $rawItems): void
    {
        $invoice->items()->delete();

        $normalized = $this->normalizeItems($rawItems);
        foreach ($normalized as $i => $item) {
            InvoiceItem::create([
                'invoice_id'      => $invoice->id,
                'product_id'      => $item['product_id'],
                'service_item_id' => $item['service_item_id'],
                'description'     => $item['description'],
                'quantity'        => $item['quantity'],
                'unit_price'      => $item['unit_price'],
                'discount_type'   => $item['discount_type'],
                'discount_value'  => $item['discount_value'],
                'tax_pct'         => $item['tax_pct'],
                'tax_type'        => $item['tax_type'],
                'line_total'      => $item['line_total'],
                'sort_order'      => $i,
                'rental_daily_rate'          => $item['rental_daily_rate'],
                'rental_return_date'         => $item['rental_return_date'],
                'rental_late_fee_multiplier' => $item['rental_late_fee_multiplier'],
                'warranty_type'   => $item['warranty_type'],
                'warranty_date'   => $item['warranty_date'],
                'is_subscription' => $item['is_subscription'],
                'subscription_period' => $item['subscription_period'],
            ]);
        }
    }

    private function normalizeItems(array $rawItems): array
    {
        $normalized = [];
        foreach ($rawItems as $item) {
            $qty   = max(0, (float) ($item['quantity']   ?? 0));
            $price = max(0, (float) ($item['unit_price'] ?? 0));
            if ($qty <= 0 && $price <= 0 && empty($item['description'])) {
                continue;
            }
            $type      = $item['item_type'] ?? null;
            $discType  = in_array($item['discount_type'] ?? 'pct', ['pct', 'flat']) ? ($item['discount_type'] ?? 'pct') : 'pct';
            $discValue = max(0, (float) ($item['discount_value'] ?? 0));
            // Normalize 'percentage' (POS settings format) → 'pct'
            $rawTaxType = $item['tax_type'] ?? 'pct';
            $taxType    = $rawTaxType === 'percentage' ? 'pct' : (in_array($rawTaxType, ['pct', 'flat']) ? $rawTaxType : 'pct');
            $taxValue  = max(0, (float) ($item['tax_pct'] ?? 0));

            $lineGross = $qty * $price;
            $discAmt   = $discType === 'flat'
                ? min($discValue, $lineGross)
                : ($lineGross * $discValue / 100);
            $lineNet   = max(0, $lineGross - $discAmt);
            // tax_pct stores the raw rule value; tax_type says whether it's % or flat
            $taxAmt    = $taxType === 'flat'
                ? $taxValue
                : ($lineNet * $taxValue / 100);
            $lineTotal = round($lineNet + $taxAmt, 2);

            $warrantyType = in_array($item['warranty_type'] ?? null, ['lifetime', 'date'], true) ? $item['warranty_type'] : null;

            $normalized[] = [
                'product_id'      => $type === 'product' ? $this->nullableInt($item['product_id'] ?? null) : null,
                'service_item_id' => $type === 'service' ? $this->nullableInt($item['service_item_id'] ?? null) : null,
                'description'     => trim((string) ($item['description'] ?? '')),
                'quantity'        => $qty,
                'unit_price'      => $price,
                'discount_type'   => $discType,
                'discount_value'  => round($discValue, 2),
                'tax_pct'         => round($taxValue, 2),
                'tax_type'        => $taxType,
                'line_total'      => $lineTotal,
                'rental_daily_rate'          => filled($item['rental_daily_rate'] ?? null) ? round((float) $item['rental_daily_rate'], 2) : null,
                'rental_return_date'         => filled($item['rental_return_date'] ?? null) ? $item['rental_return_date'] : null,
                'rental_late_fee_multiplier' => filled($item['rental_late_fee_multiplier'] ?? null) ? round((float) $item['rental_late_fee_multiplier'], 2) : null,
                'warranty_type'   => $warrantyType,
                'warranty_date'   => $warrantyType === 'date' && filled($item['warranty_date'] ?? null) ? $item['warranty_date'] : null,
                'is_subscription' => (bool) ($item['is_subscription'] ?? false),
                'subscription_period' => filled($item['subscription_period'] ?? null) ? $item['subscription_period'] : null,
            ];
        }

        return $normalized;
    }

    private function recalculateTotals(Invoice $invoice, array $data): void
    {
        $invoice->load('items');
        // Subtotal is the sum of line_totals; each line_total already includes
        // per-line discount and tax, so we add any header-level adjustments on top.
        $subtotal = (float) $invoice->items->sum('line_total');

        $discountAmount = max(0, (float) ($data['discount_amount'] ?? 0));
        $taxAmount      = max(0, (float) ($data['tax_amount'] ?? 0));
        $total          = max(0, $subtotal - $discountAmount + $taxAmount);

        $invoice->update([
            'subtotal'        => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount'      => round($taxAmount, 2),
            'total'           => round($total, 2),
        ]);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }

        return (int) $value;
    }
}
