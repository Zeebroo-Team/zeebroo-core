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
            ->select('quotations.*')
            ->selectRaw(
                '(SELECT COUNT(DISTINCT proposal_group) FROM design_studio_designs'.
                ' WHERE quotation_id = quotations.id AND proposal_group IS NOT NULL) as proposal_count'
            )
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
                'quote_number'    => filled($data['quote_number'] ?? null) ? $data['quote_number'] : $this->nextQuoteNumber($business),
                'customer_id'     => $this->nullableInt($data['customer_id'] ?? null),
                // Only the CRM's lead quotation flow passes these
                'lead_id'             => $this->nullableInt($data['lead_id'] ?? null),
                'version'             => max(1, (int) ($data['version'] ?? 1)),
                'parent_quotation_id' => $this->nullableInt($data['parent_quotation_id'] ?? null),
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
                // Keep the original branch when the editor doesn't send one
                'branch_id'   => array_key_exists('branch_id', $data)
                    ? $this->nullableInt($data['branch_id'])
                    : $quotation->branch_id,
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

    /**
     * A lead quotation is only sent once its department head approved it (the CRM's dispatcher
     * marks it sent after the PDF went out); other quotations go from draft to sent by hand.
     */
    public function markSent(Quotation $quotation): Quotation
    {
        if ($quotation->isLeadQuotation()) {
            if (! in_array($quotation->status, [Quotation::STATUS_APPROVED, Quotation::STATUS_SENT], true)) {
                throw ValidationException::withMessages(['quotation' => 'This quotation must be approved before it is sent.']);
            }
            if ($quotation->status === Quotation::STATUS_APPROVED) {
                $quotation->update(['status' => Quotation::STATUS_SENT, 'sent_at' => $quotation->sent_at ?? now()]);
            }

            return $quotation;
        }

        if ($quotation->status === Quotation::STATUS_DRAFT) {
            $quotation->update(['status' => Quotation::STATUS_SENT]);
        }

        return $quotation;
    }

    public function markAccepted(Quotation $quotation): Quotation
    {
        $from = $quotation->isLeadQuotation() ? [Quotation::STATUS_SENT] : [Quotation::STATUS_DRAFT, Quotation::STATUS_SENT];
        if (in_array($quotation->status, $from)) {
            $quotation->update(['status' => Quotation::STATUS_ACCEPTED]);
        }

        return $quotation;
    }

    public function markRejected(Quotation $quotation): Quotation
    {
        $from = $quotation->isLeadQuotation() ? [Quotation::STATUS_SENT] : [Quotation::STATUS_SENT, Quotation::STATUS_DRAFT];
        if (in_array($quotation->status, $from)) {
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

    /**
     * A new draft version of a lead quotation that can no longer be edited (approved, sent,
     * rejected …): same header and lines, version + 1, numbered "<first number>-v<version>".
     */
    public function revise(Quotation $quotation): Quotation
    {
        if ($quotation->isEditable()) {
            throw ValidationException::withMessages(['quotation' => 'This quotation can still be edited — no new version is needed.']);
        }

        $quotation->loadMissing(['items', 'business']);
        $base    = $this->baseNumber($quotation);
        $latest  = (int) Quotation::query()
            ->where('business_id', $quotation->business_id)
            ->where(fn ($q) => $q->where('quote_number', $base)->orWhere('quote_number', 'like', $base . '-v%'))
            ->max('version');
        $version = max($latest, (int) $quotation->version) + 1;

        $items = $quotation->items->map(fn (QuotationItem $i) => [
            'item_type'       => $i->product_id ? 'product' : ($i->service_item_id ? 'service' : 'custom'),
            'product_id'      => $i->product_id,
            'service_item_id' => $i->service_item_id,
            'description'     => $i->description,
            'quantity'        => (float) $i->quantity,
            'unit_price'      => (float) $i->unit_price,
            'discount_amount' => (float) $i->discount_amount,
            'tax_rate'        => (float) $i->tax_rate,
        ])->all();

        return $this->create($quotation->business, [
            'branch_id'           => $quotation->branch_id,
            'customer_id'         => $quotation->customer_id,
            'lead_id'             => $quotation->lead_id,
            'version'             => $version,
            'parent_quotation_id' => $quotation->id,
            'quote_number'        => $base . '-v' . $version,
            'reference'           => $quotation->reference,
            'quote_date'          => now()->toDateString(),
            'expiry_date'         => $quotation->expiry_date?->toDateString(),
            'notes'               => $quotation->notes,
            ...$this->headerAdjustments($quotation),
        ], $items);
    }

    /**
     * The header discount / flat tax of a quotation, without what its lines contribute — what
     * an editor shows in its header fields and sends back on save.
     *
     * @return array{discount_amount: float, tax_amount: float}
     */
    public function headerAdjustments(Quotation $quotation): array
    {
        $quotation->loadMissing('items');

        return [
            'discount_amount' => round(max(0, (float) $quotation->discount_amount - (float) $quotation->items->sum('discount_amount')), 2),
            'tax_amount'      => round(max(0, (float) $quotation->tax_amount - (float) $quotation->items->sum('tax_amount')), 2),
        ];
    }

    /** "QT-0007" for QT-0007, QT-0007-v2, QT-0007-v3 … */
    private function baseNumber(Quotation $quotation): string
    {
        return preg_replace('/-v\d+$/i', '', (string) $quotation->quote_number);
    }

    private function nextQuoteNumber(Business $business): string
    {
        $last = Quotation::query()
            ->where('business_id', $business->id)
            ->whereNotNull('quote_number')
            // Revisions ("QT-0007-v2") reuse their first version's number
            ->whereNull('parent_quotation_id')
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
                'quotation_id'    => $quotation->id,
                'product_id'      => $item['product_id'],
                'service_item_id' => $item['service_item_id'],
                'description'     => $item['description'],
                'quantity'        => $item['quantity'],
                'unit_price'      => $item['unit_price'],
                'discount_amount' => $item['discount_amount'],
                'tax_rate'        => $item['tax_rate'],
                'tax_amount'      => $item['tax_amount'],
                'line_total'      => $item['line_total'],
                'sort_order'      => $i,
            ]);
        }
    }

    /**
     * Each line: gross = qty × price; an optional discount (amount, at most the gross); an
     * optional tax rate (%) charged on what is left. line_total = gross − discount, before tax.
     * A line without discount / tax comes out exactly as before (line_total = qty × price).
     */
    private function normalizeItems(array $rawItems): array
    {
        $normalized = [];
        foreach ($rawItems as $item) {
            $qty   = max(0, (float) ($item['quantity']  ?? 0));
            $price = max(0, (float) ($item['unit_price'] ?? 0));
            if ($qty <= 0 && $price <= 0 && empty($item['description'])) {
                continue;
            }
            $type     = $item['item_type'] ?? null;
            $gross    = round($qty * $price, 2);
            $discount = min($gross, round(max(0, (float) ($item['discount_amount'] ?? 0)), 2));
            $taxRate  = min(100, max(0, (float) ($item['tax_rate'] ?? 0)));
            $net      = round($gross - $discount, 2);
            $normalized[] = [
                'product_id'      => $type === 'product' ? $this->nullableInt($item['product_id'] ?? null) : null,
                'service_item_id' => $type === 'service' ? $this->nullableInt($item['service_item_id'] ?? null) : null,
                'description'     => trim((string) ($item['description'] ?? '')),
                'quantity'        => $qty,
                'unit_price'      => $price,
                'discount_amount' => $discount,
                'tax_rate'        => $taxRate,
                'tax_amount'      => round($net * $taxRate / 100, 2),
                'line_total'      => $net,
            ];
        }

        return $normalized;
    }

    /**
     * subtotal = Σ gross of the lines; discount = Σ line discounts + the header discount;
     * tax = Σ line tax + the header (flat) tax; total = subtotal − discount + tax. The header
     * discount does not lower the line tax. With no line discount / tax this is the original
     * Σ line_total − discount + tax.
     */
    private function recalculateTotals(Quotation $quotation, array $data): void
    {
        $quotation->load('items');
        $subtotal = $quotation->items->sum(fn (QuotationItem $i) => (float) $i->line_total + (float) $i->discount_amount);

        $discountAmount = max(0, (float) ($data['discount_amount'] ?? 0)) + (float) $quotation->items->sum('discount_amount');
        $taxAmount      = max(0, (float) ($data['tax_amount'] ?? 0)) + (float) $quotation->items->sum('tax_amount');
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
