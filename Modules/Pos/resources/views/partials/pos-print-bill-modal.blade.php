@php
    $printSale = $printSale ?? null;
    if (!$printSale) {
        return;
    }
    $formatQty = static function (float $value): string {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    };
    $currencyLabel = filled($currency ?? null) ? ' '.$currency : '';
    $subtotal = (float) ($printSale->subtotal ?? $printSale->items->sum(fn ($i) => (float) $i->line_total));
    $discountAmount = (float) ($printSale->discount_amount ?? 0);
    $discountPercent = (float) ($printSale->discount_percent ?? 0);
    $discountPercentLabel = $discountPercent > 0
        ? ' ('.rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.').'%)'
        : '';
    $productDiscTotal = $printSale->items->sum(
        fn ($i) => round((float) $i->quantity * (float) ($i->discount_amount ?? 0), 2)
    );
    $posBillPrintData = [
        'businessName' => $business->name,
        'saleNumber' => $printSale->sale_number,
        'soldAt' => $printSale->sold_at?->format('M j, Y g:i A') ?? '',
        'payment' => $printSale->paymentMethodLabel(),
        'account' => $printSale->creditAccount?->deductOptionLabel() ?? '',
        'channel' => $printSale->channelLabel(),
        'currency' => trim($currencyLabel),
        'subtotal' => number_format($subtotal, 2, '.', ''),
        'discountPercent' => $discountPercent > 0 ? number_format($discountPercent, 2, '.', '') : '',
        'discountAmount' => $discountAmount > 0 ? number_format($discountAmount, 2, '.', '') : '',
        'productDiscountTotal' => $productDiscTotal > 0.001 ? number_format($productDiscTotal, 2, '.', '') : '',
        'total' => number_format((float) $printSale->total, 2, '.', ''),
        'amountPaid' => number_format((float) $printSale->amount_paid, 2, '.', ''),
        'amountTendered' => $printSale->amount_tendered !== null
            ? number_format((float) $printSale->amount_tendered, 2, '.', '')
            : '',
        'changeAmount' => $printSale->change_amount !== null
            ? number_format((float) $printSale->change_amount, 2, '.', '')
            : '',
        'notes' => $printSale->notes ?? '',
        'items' => $printSale->items->map(function ($item) use ($formatQty) {
            $disc = (float) ($item->discount_amount ?? 0);
            return [
                'name' => $item->product_name,
                'sku' => $item->sku ?? '',
                'qty' => $formatQty((float) $item->quantity),
                'unit' => number_format((float) $item->unit_sell_price, 2, '.', ''),
                'original_unit' => $disc > 0.001
                    ? number_format((float) $item->unit_sell_price + $disc, 2, '.', '')
                    : '',
                'discount' => $disc > 0.001
                    ? number_format($disc, 2, '.', '')
                    : '',
                'discount_line' => $disc > 0.001
                    ? number_format((float) $item->quantity * $disc, 2, '.', '')
                    : '',
                'line' => number_format((float) $item->line_total, 2, '.', ''),
            ];
        })->values()->all(),
    ];
@endphp

<div id="pos-bill-modal" class="pos-bill-modal is-open" role="dialog" aria-modal="true" aria-labelledby="pos-bill-title" aria-hidden="false"
     data-pos-bill-print="{{ e(json_encode($posBillPrintData)) }}">
    <div class="pos-bill-modal__backdrop" data-pos-bill-close tabindex="-1" aria-label="Close"></div>
    <div class="pos-bill-modal__panel">
        <div class="pos-bill-modal__head">
            <div>
                <h2 id="pos-bill-title">Sale completed</h2>
                <p>{{ $printSale->sale_number }} · {{ $printSale->sold_at?->format('M j, Y g:i A') }}</p>
            </div>
            <button type="button" class="pos-bill-modal__close" data-pos-bill-close aria-label="Close">&times;</button>
        </div>
        <div class="pos-bill-modal__body">
            <div class="pos-bill-modal__meta">
                <div class="pos-bill-modal__meta-row"><span>Business</span><strong>{{ $business->name }}</strong></div>
                <div class="pos-bill-modal__meta-row"><span>Payment</span><strong>{{ $printSale->paymentMethodLabel() }}</strong></div>
                @if($printSale->creditAccount)
                    <div class="pos-bill-modal__meta-row"><span>Deposit account</span><strong>{{ $printSale->creditAccount->deductOptionLabel() }}</strong></div>
                @endif
                <div class="pos-bill-modal__meta-row"><span>Channel</span><strong>{{ $printSale->channelLabel() }}</strong></div>
                @if($printSale->payment_method === \Modules\Pos\Models\Sale::PAYMENT_CASH && $printSale->amount_tendered !== null)
                    <div class="pos-bill-modal__meta-row"><span>Cash received</span><strong>{{ number_format((float) $printSale->amount_tendered, 2) }}{{ $currencyLabel }}</strong></div>
                    <div class="pos-bill-modal__meta-row"><span>Change</span><strong>{{ number_format((float) ($printSale->change_amount ?? 0), 2) }}{{ $currencyLabel }}</strong></div>
                @endif
            </div>

            <table class="pos-bill-receipt-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th style="text-align:right;">Unit</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($printSale->items as $item)
                        @php $itemDisc = (float) ($item->discount_amount ?? 0); @endphp
                        <tr>
                            <td>
                                <strong style="color:var(--text);">{{ $item->product_name }}</strong>
                                @if(filled($item->sku))
                                    <div class="muted" style="font-size:10px;margin-top:2px;">{{ $item->sku }}</div>
                                @endif
                            </td>
                            <td class="muted">{{ $formatQty((float) $item->quantity) }}</td>
                            <td class="muted" style="text-align:right;">
                                @if($itemDisc > 0.001)
                                    <del style="font-size:10px;color:#999;">{{ number_format((float) $item->unit_sell_price + $itemDisc, 2) }}{{ $currencyLabel }}</del><br>
                                @endif
                                {{ number_format((float) $item->unit_sell_price, 2) }}{{ $currencyLabel }}
                            </td>
                            <td style="text-align:right;font-weight:700;color:var(--text);">{{ number_format((float) $item->line_total, 2) }}{{ $currencyLabel }}</td>
                        </tr>
                        @if($itemDisc > 0.001)
                            <tr>
                                <td colspan="3" style="font-size:11px;color:#d97706;padding-bottom:6px;">
                                    &nbsp;&nbsp;&#x21b3; Discount &minus;{{ number_format($itemDisc, 2) }}{{ $currencyLabel }} / unit
                                </td>
                                <td style="text-align:right;font-size:11px;color:#d97706;padding-bottom:6px;">
                                    &minus;{{ number_format((float) $item->quantity * $itemDisc, 2) }}{{ $currencyLabel }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    @if($productDiscTotal > 0.001 || $discountAmount > 0.001 || $subtotal > (float) $printSale->total + 0.001)
                        <tr>
                            <td colspan="3" style="text-align:right;color:var(--muted);">Subtotal</td>
                            <td style="text-align:right;font-weight:600;">{{ number_format($subtotal, 2) }}{{ $currencyLabel }}</td>
                        </tr>
                        @if($productDiscTotal > 0.001)
                            <tr>
                                <td colspan="3" style="text-align:right;color:#d97706;">Product discounts</td>
                                <td style="text-align:right;font-weight:600;color:#d97706;">−{{ number_format($productDiscTotal, 2) }}{{ $currencyLabel }}</td>
                            </tr>
                        @endif
                        @if($discountAmount > 0.001)
                            <tr>
                                <td colspan="3" style="text-align:right;color:var(--muted);">Discount{{ $discountPercentLabel }}</td>
                                <td style="text-align:right;font-weight:600;">−{{ number_format($discountAmount, 2) }}{{ $currencyLabel }}</td>
                            </tr>
                        @endif
                    @endif
                    <tr>
                        <td colspan="3" style="text-align:right;" class="pos-bill-receipt-total">Total</td>
                        <td style="text-align:right;" class="pos-bill-receipt-total">{{ number_format((float) $printSale->total, 2) }}{{ $currencyLabel }}</td>
                    </tr>
                </tfoot>
            </table>

            @php $totalSavedBill = $productDiscTotal + $discountAmount; @endphp
            @if($totalSavedBill > 0.001)
                <div style="margin:8px 0;padding:6px 10px;border:1px dashed #f59e0b;border-radius:6px;font-size:12px;font-weight:700;color:#b45309;text-align:center;">
                    &#127991; You saved {{ number_format($totalSavedBill, 2) }}{{ $currencyLabel }} on this purchase!
                </div>
            @endif

            @if(filled($printSale->notes))
                <p class="muted" style="margin:0;font-size:12px;line-height:1.45;"><strong style="color:var(--text);">Notes:</strong> {{ $printSale->notes }}</p>
            @endif
        </div>
        <div class="pos-bill-modal__foot">
            <button type="button" class="pos-bill-modal__btn pos-bill-modal__btn--primary" id="pos-bill-btn-print">
                <i class="fa fa-print" aria-hidden="true"></i> Print bill
            </button>
            <a href="{{ route('pos.sales.show', $printSale) }}" class="pos-bill-modal__btn">View receipt</a>
            <button type="button" class="pos-bill-modal__btn" data-pos-bill-close>New sale</button>
        </div>
    </div>
</div>

@once
<script>
(function () {
    const modal = document.getElementById('pos-bill-modal');
    if (!modal) return;

    let billData = {};
    try {
        billData = JSON.parse(modal.getAttribute('data-pos-bill-print') || '{}');
    } catch (e) {
        billData = {};
    }

    function escHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-bill-modal-open', open);
    }

    modal.querySelectorAll('[data-pos-bill-close]').forEach(function (el) {
        el.addEventListener('click', function () { setOpen(false); });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            setOpen(false);
        }
    });

    function buildPrintHtml() {
        const cur = billData.currency ? ' ' + billData.currency : '';
        let rows = '';
        (billData.items || []).forEach(function (item) {
            const unitPriceHtml = item.original_unit
                ? '<del style="font-size:9px;color:#999;">' + escHtml(item.original_unit) + escHtml(cur) + '</del> ' + escHtml(item.unit) + escHtml(cur)
                : escHtml(item.unit) + escHtml(cur);
            rows += '<tr>'
                + '<td>' + escHtml(item.name) + (item.sku ? '<div style="font-size:10px;color:#666;">' + escHtml(item.sku) + '</div>' : '') + '</td>'
                + '<td style="text-align:center;">' + escHtml(item.qty) + '</td>'
                + '<td style="text-align:right;">' + unitPriceHtml + '</td>'
                + '<td style="text-align:right;font-weight:600;">' + escHtml(item.line) + escHtml(cur) + '</td>'
                + '</tr>';
            if (item.discount) {
                rows += '<tr>'
                    + '<td colspan="3" style="font-size:10px;color:#d97706;padding-bottom:4px;">&nbsp;&nbsp;&#x21b3; Discount &minus;' + escHtml(item.discount) + escHtml(cur) + ' / unit</td>'
                    + '<td style="text-align:right;font-size:10px;color:#d97706;padding-bottom:4px;">&minus;' + escHtml(item.discount_line) + escHtml(cur) + '</td>'
                    + '</tr>';
            }
        });

        const hasProductDisc = billData.productDiscountTotal && parseFloat(billData.productDiscountTotal) > 0;
        let totals = '';
        if (hasProductDisc || billData.discountAmount) {
            totals += '<tr><td colspan="3" style="text-align:right;padding:4px 0;">Subtotal</td><td style="text-align:right;">' + escHtml(billData.subtotal) + escHtml(cur) + '</td></tr>';
            if (hasProductDisc) {
                totals += '<tr><td colspan="3" style="text-align:right;padding:4px 0;color:#d97706;">Product discounts</td><td style="text-align:right;color:#d97706;">&minus;' + escHtml(billData.productDiscountTotal) + escHtml(cur) + '</td></tr>';
            }
            if (billData.discountAmount) {
                totals += '<tr><td colspan="3" style="text-align:right;padding:4px 0;">Discount' + (billData.discountPercent ? ' (' + escHtml(billData.discountPercent) + '%)' : '') + '</td><td style="text-align:right;">&minus;' + escHtml(billData.discountAmount) + escHtml(cur) + '</td></tr>';
            }
        }
        totals += '<tr><td colspan="3" style="text-align:right;padding:8px 0 4px;font-size:15px;font-weight:800;">Total</td><td style="text-align:right;font-size:15px;font-weight:800;">' + escHtml(billData.total) + escHtml(cur) + '</td></tr>';

        const totalSaved = (parseFloat(billData.productDiscountTotal || '0') + parseFloat(billData.discountAmount || '0'));
        const savedBanner = totalSaved > 0
            ? '<div style="margin:10px 0;padding:6px 10px;border:1px dashed #f59e0b;border-radius:4px;font-size:11px;font-weight:700;color:#b45309;text-align:center;">&#127991; You saved ' + totalSaved.toFixed(2) + escHtml(cur) + ' on this purchase!</div>'
            : '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + escHtml(billData.saleNumber || 'POS bill') + '</title>'
            + '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:24px 28px;color:#111;line-height:1.4;max-width:420px;margin:0 auto;}h1{font-size:18px;margin:0 0 4px;}p.meta{margin:0 0 16px;font-size:12px;color:#555;}table{width:100%;border-collapse:collapse;font-size:12px;margin:12px 0;}th,td{padding:6px 4px;border-bottom:1px solid #ddd;}th{font-size:10px;text-transform:uppercase;color:#666;text-align:left;}tfoot td{border-bottom:none;}.foot{margin-top:20px;font-size:10px;color:#666;text-align:center;}</style></head><body>'
            + '<h1>' + escHtml(billData.businessName) + '</h1>'
            + '<p class="meta">' + escHtml(billData.saleNumber) + ' · ' + escHtml(billData.soldAt) + '<br>'
            + escHtml(billData.payment) + (billData.account ? ' · ' + escHtml(billData.account) : '')
            + (billData.amountTendered ? '<br>Cash received: ' + escHtml(billData.amountTendered) + escHtml(cur) + ' · Change: ' + escHtml(billData.changeAmount) + escHtml(cur) : '')
            + '</p>'
            + '<table><thead><tr><th>Item</th><th>Qty</th><th style="text-align:right;">Unit</th><th style="text-align:right;">Amount</th></tr></thead><tbody>' + rows + '</tbody><tfoot>' + totals + '</tfoot></table>'
            + savedBanner
            + (billData.notes ? '<p style="font-size:11px;"><strong>Notes:</strong> ' + escHtml(billData.notes) + '</p>' : '')
            + '<p class="foot">Thank you · Printed ' + new Date().toLocaleString() + '</p>'
            + '</body></html>';
    }

    function openPrintWindow() {
        const w = window.open('', '_blank');
        if (!w) {
            window.alert('Allow pop-ups to print the bill.');
            return;
        }
        w.document.open();
        w.document.write(buildPrintHtml());
        w.document.close();
        w.focus();
        const closeAfter = function () {
            try { w.close(); } catch (e) {}
        };
        if ('onafterprint' in w) {
            w.addEventListener('afterprint', closeAfter);
        } else {
            setTimeout(closeAfter, 800);
        }
        setTimeout(function () { w.print(); }, 150);
    }

    document.getElementById('pos-bill-btn-print')?.addEventListener('click', openPrintWindow);

    document.documentElement.classList.add('pos-bill-modal-open');
})();
</script>
@endonce
