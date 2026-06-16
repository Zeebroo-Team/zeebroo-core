<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceItem;

class InvoiceService
{
    public function listForBusiness(
        Business $business,
        ?string $search = null,
        ?string $status = null,
        ?int $customerId = null,
    ): Collection {
        $query = Invoice::query()
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
        return DB::transaction(function () use ($business, $data, $items) {
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
                'subtotal'        => 0,
                'discount_amount' => 0,
                'tax_amount'      => 0,
                'total'           => 0,
            ]);

            $this->syncItems($invoice, $items);
            $this->recalculateTotals($invoice, $data);

            return $invoice;
        });
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
                'due_date'    => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
                'notes'       => filled($data['notes'] ?? '') ? $data['notes'] : null,
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
        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_OVERDUE])) {
            $invoice->update(['status' => Invoice::STATUS_PAID]);
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
                'invoice_id'  => $invoice->id,
                'product_id'  => $item['product_id'],
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'line_total'  => $item['line_total'],
                'sort_order'  => $i,
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
            $normalized[] = [
                'product_id'  => $this->nullableInt($item['product_id'] ?? null),
                'description' => trim((string) ($item['description'] ?? '')),
                'quantity'    => $qty,
                'unit_price'  => $price,
                'line_total'  => round($qty * $price, 2),
            ];
        }

        return $normalized;
    }

    private function recalculateTotals(Invoice $invoice, array $data): void
    {
        $invoice->load('items');
        $subtotal = $invoice->items->sum('line_total');

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
