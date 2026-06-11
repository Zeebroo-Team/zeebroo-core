<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationItem;

class QuotationService
{
    public function listForBusiness(
        Business $business,
        ?string $search = null,
        ?string $status = null,
        ?int $customerId = null,
    ): Collection {
        $query = Quotation::query()
            ->where('business_id', $business->id)
            ->with('customer');

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('quote_number', 'like', "%{$search}%")
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

        return $query->orderByDesc('quote_date')->orderByDesc('id')->get();
    }

    public function businessHasQuotations(Business $business): bool
    {
        return Quotation::query()->where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data, array $items): Quotation
    {
        return DB::transaction(function () use ($business, $data, $items) {
            $quotation = Quotation::create([
                'business_id'     => $business->id,
                'branch_id'       => $this->nullableInt($data['branch_id'] ?? null),
                'quote_number'    => $this->nextQuoteNumber($business),
                'customer_id'     => $this->nullableInt($data['customer_id'] ?? null),
                'reference'       => filled($data['reference'] ?? '') ? $data['reference'] : null,
                'quote_date'      => $data['quote_date'],
                'expiry_date'     => filled($data['expiry_date'] ?? '') ? $data['expiry_date'] : null,
                'status'          => Quotation::STATUS_DRAFT,
                'notes'           => filled($data['notes'] ?? '') ? $data['notes'] : null,
                'subtotal'        => 0,
                'discount_amount' => 0,
                'tax_amount'      => 0,
                'total'           => 0,
            ]);

            $this->syncItems($quotation, $items);
            $this->recalculateTotals($quotation, $data);

            return $quotation;
        });
    }

    public function update(Quotation $quotation, array $data, array $items): Quotation
    {
        if (!$quotation->isEditable()) {
            throw ValidationException::withMessages(['quotation' => 'This quotation can no longer be edited.']);
        }

        return DB::transaction(function () use ($quotation, $data, $items) {
            $quotation->update([
                'branch_id'   => $this->nullableInt($data['branch_id'] ?? null),
                'customer_id' => $this->nullableInt($data['customer_id'] ?? null),
                'reference'   => filled($data['reference'] ?? '') ? $data['reference'] : null,
                'quote_date'  => $data['quote_date'],
                'expiry_date' => filled($data['expiry_date'] ?? '') ? $data['expiry_date'] : null,
                'notes'       => filled($data['notes'] ?? '') ? $data['notes'] : null,
            ]);

            $this->syncItems($quotation, $items);
            $this->recalculateTotals($quotation, $data);

            return $quotation->fresh();
        });
    }

    public function markSent(Quotation $quotation): Quotation
    {
        if ($quotation->status === Quotation::STATUS_DRAFT) {
            $quotation->update(['status' => Quotation::STATUS_SENT]);
        }

        return $quotation;
    }

    public function markAccepted(Quotation $quotation): Quotation
    {
        if (in_array($quotation->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_SENT])) {
            $quotation->update(['status' => Quotation::STATUS_ACCEPTED]);
        }

        return $quotation;
    }

    public function markRejected(Quotation $quotation): Quotation
    {
        if (in_array($quotation->status, [Quotation::STATUS_SENT, Quotation::STATUS_DRAFT])) {
            $quotation->update(['status' => Quotation::STATUS_REJECTED]);
        }

        return $quotation;
    }

    public function delete(Quotation $quotation): void
    {
        if ($quotation->status === Quotation::STATUS_ACCEPTED) {
            throw ValidationException::withMessages(['quotation' => 'Accepted quotations cannot be deleted.']);
        }

        $quotation->delete();
    }

    public function quotationForBusiness(Business $business, Quotation $quotation): ?Quotation
    {
        return $quotation->business_id === $business->id ? $quotation : null;
    }

    private function nextQuoteNumber(Business $business): string
    {
        $last = Quotation::query()
            ->where('business_id', $business->id)
            ->whereNotNull('quote_number')
            ->orderByDesc('id')
            ->value('quote_number');

        $seq = 1;
        if ($last && preg_match('/QT-(\d+)$/i', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'QT-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function syncItems(Quotation $quotation, array $rawItems): void
    {
        $quotation->items()->delete();

        $normalized = $this->normalizeItems($rawItems);
        foreach ($normalized as $i => $item) {
            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'product_id'   => $item['product_id'],
                'description'  => $item['description'],
                'quantity'     => $item['quantity'],
                'unit_price'   => $item['unit_price'],
                'line_total'   => $item['line_total'],
                'sort_order'   => $i,
            ]);
        }
    }

    private function normalizeItems(array $rawItems): array
    {
        $normalized = [];
        foreach ($rawItems as $item) {
            $qty   = max(0, (float) ($item['quantity']  ?? 0));
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

    private function recalculateTotals(Quotation $quotation, array $data): void
    {
        $quotation->load('items');
        $subtotal = $quotation->items->sum('line_total');

        $discountAmount = max(0, (float) ($data['discount_amount'] ?? 0));
        $taxAmount      = max(0, (float) ($data['tax_amount'] ?? 0));
        $total          = max(0, $subtotal - $discountAmount + $taxAmount);

        $quotation->update([
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
