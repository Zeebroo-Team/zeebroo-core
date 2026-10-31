<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Collection;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationItem;

/**
 * The versions of a quotation (QT-0007, QT-0007-v2, …, see QuotationService::revise) and what
 * changed from one to the next: lines added / removed / changed and the totals.
 */
class QuotationVersionService
{
    /** Line fields compared between versions. */
    private const LINE_FIELDS = ['quantity', 'unit_price', 'discount_amount', 'tax_rate', 'line_total'];

    /** Header fields whose change is reported. */
    private const HEADER_FIELDS = ['reference', 'expiry_date', 'notes'];

    public function __construct(private readonly QuotationService $quotations) {}

    /** @return Collection<int, Quotation> every version of the quotation, oldest first */
    public function versions(Quotation $quotation): Collection
    {
        $base = $this->quotations->baseNumber($quotation);

        return Quotation::query()
            ->where('business_id', $quotation->business_id)
            ->where(fn ($q) => $quotation->lead_id === null ? $q->whereNull('lead_id') : $q->where('lead_id', $quotation->lead_id))
            ->where(fn ($q) => $q->where('quote_number', $base)->orWhere('quote_number', 'like', $base . '-v%'))
            ->with(['customer', 'items.product:id,name,sku', 'customerResponses.recordedBy:id,name', 'submittedBy:id,name', 'approvedBy:id,name'])
            ->orderBy('version')
            ->orderBy('id')
            ->get();
    }

    /**
     * What changed from one version to another.
     *
     * @return array{from: array, to: array, lines: list<array<string, mixed>>, totals: array<string, array{from: float, to: float, delta: float}>, header: list<array{field: string, from: mixed, to: mixed}>, changed: bool}
     */
    public function diff(Quotation $from, Quotation $to): array
    {
        $from->loadMissing('items.product:id,name,sku');
        $to->loadMissing('items.product:id,name,sku');

        $old   = $this->keyedLines($from);
        $new   = $this->keyedLines($to);
        $lines = [];

        foreach ($new as $key => $line) {
            $before = $old[$key] ?? null;
            $changes = $before === null ? [] : array_values(array_filter(self::LINE_FIELDS, fn ($f) => $before[$f] !== $line[$f]));
            $lines[] = [
                'key'         => $key,
                'description' => $line['description'],
                'status'      => $before === null ? 'added' : ($changes ? 'changed' : 'same'),
                'changes'     => $changes,
                'from'        => $before,
                'to'          => $line,
            ];
        }
        foreach ($old as $key => $line) {
            if (! isset($new[$key])) {
                $lines[] = ['key' => $key, 'description' => $line['description'], 'status' => 'removed', 'changes' => [], 'from' => $line, 'to' => null];
            }
        }

        $totals = [];
        foreach (['subtotal', 'discount_amount', 'tax_amount', 'total'] as $field) {
            $totals[$field] = $this->delta((float) $from->{$field}, (float) $to->{$field});
        }
        $totals['discount_percent'] = $this->delta($from->discountPercent(), $to->discountPercent());

        $header = [];
        foreach (self::HEADER_FIELDS as $field) {
            $a = $this->headerValue($from, $field);
            $b = $this->headerValue($to, $field);
            if ($a !== $b) {
                $header[] = ['field' => $field, 'from' => $a, 'to' => $b];
            }
        }

        return [
            'from'    => ['id' => (int) $from->id, 'quote_number' => $from->quote_number, 'version' => (int) $from->version],
            'to'      => ['id' => (int) $to->id, 'quote_number' => $to->quote_number, 'version' => (int) $to->version],
            'lines'   => $lines,
            'totals'  => $totals,
            'header'  => $header,
            'changed' => $header !== []
                || collect($lines)->contains(fn ($l) => $l['status'] !== 'same')
                || collect($totals)->contains(fn ($t) => $t['delta'] != 0),
        ];
    }

    /**
     * Each line keyed by what it is — the product, service or (custom) description — so the
     * same item is matched across versions; a repeated item gets "#2", "#3" ….
     *
     * @return array<string, array<string, mixed>>
     */
    private function keyedLines(Quotation $quotation): array
    {
        $lines = [];
        foreach ($quotation->items as $item) {
            /** @var QuotationItem $item */
            $key = match (true) {
                $item->product_id !== null      => 'product:' . $item->product_id,
                $item->service_item_id !== null => 'service:' . $item->service_item_id,
                default                         => 'custom:' . mb_strtolower(trim((string) $item->description)),
            };
            $n = 1;
            while (isset($lines[$n === 1 ? $key : $key . '#' . $n])) {
                $n++;
            }

            $lines[$n === 1 ? $key : $key . '#' . $n] = [
                'description'     => $item->description ?: $item->product?->name,
                'sku'             => $item->product?->sku,
                'quantity'        => round((float) $item->quantity, 3),
                'unit_price'      => round((float) $item->unit_price, 2),
                'discount_amount' => round((float) $item->discount_amount, 2),
                'tax_rate'        => round((float) $item->tax_rate, 3),
                'line_total'      => round((float) $item->line_total, 2),
            ];
        }

        return $lines;
    }

    /** @return array{from: float, to: float, delta: float} */
    private function delta(float $from, float $to): array
    {
        return ['from' => round($from, 2), 'to' => round($to, 2), 'delta' => round($to - $from, 2)];
    }

    private function headerValue(Quotation $quotation, string $field): ?string
    {
        $value = $field === 'expiry_date' ? $quotation->expiry_date?->toDateString() : $quotation->{$field};

        return $value === null || $value === '' ? null : (string) $value;
    }
}
