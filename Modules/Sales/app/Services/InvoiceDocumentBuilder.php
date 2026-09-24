<?php

namespace Modules\Sales\Services;

use Modules\Sales\Models\Invoice;

class InvoiceDocumentBuilder
{
    /**
     * Normalizes a real Invoice (with its items/customer eager-loaded) into
     * the plain array shape every template partial renders from — the same
     * shape `dummy()` produces for the Invoice Setup live preview, so both
     * paths render through identical Blade partials.
     */
    public function fromInvoice(Invoice $invoice): array
    {
        $customer = $invoice->customer;
        $customerLines = [];
        if ($customer) {
            if (filled($customer->contact_name)) {
                $customerLines[] = $customer->contact_name;
            }
            if (filled($customer->email)) {
                $customerLines[] = $customer->email;
            }
            if (filled($customer->phone)) {
                $customerLines[] = $customer->phone;
            }
            if (filled($customer->address)) {
                $customerLines[] = $customer->address;
            }
        }

        $items = $invoice->items->values()->map(function ($item, int $i) {
            $isService = $item->service_item_id && $item->serviceItem;
            $sublabel = (! $item->serviceItem && $item->product && $item->description && $item->description !== $item->product->name)
                ? $item->description
                : null;

            return [
                'n'             => $i + 1,
                'description'   => $item->serviceItem?->name ?? $item->product?->name ?? ($item->description ?: '—'),
                'sublabel'      => $sublabel,
                'isService'     => (bool) $isService,
                'boundProducts' => $isService
                    ? $item->serviceItem->products->map(fn ($bp) => [
                        'name' => $bp->name,
                        'sku'  => $bp->sku ?? '',
                        'qty'  => (float) $bp->pivot->qty * (float) $item->quantity,
                    ])->all()
                    : [],
                'qty'            => (float) $item->quantity,
                'unitPrice'      => (float) $item->unit_price,
                'discountType'   => $item->discount_type ?: 'pct',
                'discountValue'  => (float) $item->discount_value,
                'taxType'        => $item->tax_type ?: 'pct',
                'taxValue'       => (float) $item->tax_pct,
                'lineTotal'      => (float) $item->line_total,
            ];
        })->all();

        return [
            'invNumber'     => $invoice->invoice_number,
            'issueDate'     => $invoice->issue_date?->format('M j, Y') ?? '',
            'dueDate'       => $invoice->due_date?->format('M j, Y') ?? '—',
            'customerName'  => $customer?->name ?? 'Walk-in Customer',
            'customerLines' => $customerLines,
            'statusLabel'   => $invoice->statusLabel(),
            'statusColor'   => $invoice->statusColor(),
            'notes'         => $invoice->notes ?: 'Thank you for your business!',
            'items'         => $items,
            'subtotal'      => (float) $invoice->subtotal,
            'discountAmount' => (float) $invoice->discount_amount,
            'taxAmount'     => (float) $invoice->tax_amount,
            'total'         => (float) $invoice->total,
        ];
    }

    /** Sample data for the Invoice Setup live preview — mirrors Electron's `_iTPLDummy` sample invoice. */
    public function dummy(): array
    {
        $items = [
            ['description' => 'Brand Identity Design', 'sublabel' => 'Logo, colour palette, typography kit', 'qty' => 1, 'unitPrice' => 1800],
            ['description' => 'UI / UX Design', 'sublabel' => '10 screens, mobile-first, Figma source files', 'qty' => 1, 'unitPrice' => 3500],
            ['description' => 'Frontend Development', 'sublabel' => 'React, Next.js, Tailwind CSS — 40 hrs', 'qty' => 40, 'unitPrice' => 85],
            ['description' => 'SEO Optimisation', 'sublabel' => 'On-page audit + 3-month strategy', 'qty' => 1, 'unitPrice' => 650],
            ['description' => 'Monthly Hosting & Support', 'sublabel' => 'VPS, monitoring, daily backups', 'qty' => 3, 'unitPrice' => 120],
        ];

        $items = array_map(function (array $it, int $i) {
            return [
                'n'             => $i + 1,
                'description'   => $it['description'],
                'sublabel'      => $it['sublabel'],
                'isService'     => false,
                'boundProducts' => [],
                'qty'           => $it['qty'],
                'unitPrice'     => $it['unitPrice'],
                'discountType'  => 'pct',
                'discountValue' => 0.0,
                'taxType'       => 'pct',
                'taxValue'      => 0.0,
                'lineTotal'     => $it['qty'] * $it['unitPrice'],
            ];
        }, $items, array_keys($items));

        $subtotal = array_sum(array_column($items, 'lineTotal'));
        $discountAmount = round($subtotal * 0.05, 2);
        $taxAmount = round(($subtotal - $discountAmount) * 0.15, 2);

        return [
            'invNumber'      => 'INV-0024',
            'issueDate'      => '01 Aug 2026',
            'dueDate'        => '15 Aug 2026',
            'customerName'   => 'Acme Corporation',
            'customerLines'  => ['Jennifer Walters', '45 Commerce Drive, Suite 3, New York NY 10001'],
            'statusLabel'    => 'Paid',
            'statusColor'    => '#15803d',
            'notes'          => "Payment due within 14 days.\nBank transfer only — details on file.\nThank you for your business!",
            'items'          => $items,
            'subtotal'       => round($subtotal, 2),
            'discountAmount' => $discountAmount,
            'taxAmount'      => $taxAmount,
            'total'          => round($subtotal - $discountAmount + $taxAmount, 2),
        ];
    }
}
