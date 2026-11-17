{{--
    Thermal bill renderer — a port of POS Lite's receipt (electron_app_pos_lite/renderer/js/pos.js
    renderBillHtml + navbar.js ReceiptPrinter.docStyles) so a bill printed from the web POS looks the
    same as one printed from POS Lite. Keep the two in step when the receipt layout changes.

    window.PosLiteReceipt.buildDocument(data, opts) → complete HTML document (preview iframe + print)
      data — Lite buildReceiptData() shape; amounts are numbers, labels already translated
      opts — { settings, labels, money, paperWidth, margins }
--}}
@once
<script>
(function () {
    function esc(s) {
        return (s == null ? '' : String(s)).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fmtQty(n) {
        return (Number(n) || 0).toFixed(3).replace(/\.?0+$/, '');
    }

    function fmtPct(pct) {
        return pct % 1 === 0 ? String(pct) : pct.toFixed(1);
    }

    function labeller(labels) {
        labels = labels || {};
        return function (key) {
            return Object.prototype.hasOwnProperty.call(labels, key) ? labels[key] : key;
        };
    }

    // ReceiptPrinter.docStyles. The @page margin is 0 so browsers have no room to print their
    // header/footer (title, URL, date, page number); the 4mm edge is body padding instead.
    // The page height is set to the content by fitPageToContent() once the document has rendered.
    function docStyles(paperWidth) {
        var widthMm = paperWidth === 58 ? 58 : 80;
        var narrow = widthMm === 58;
        return ''
            + '@page { size: ' + widthMm + 'mm auto; margin: 0; }'
            + '* { box-sizing: border-box; }'
            + 'html { margin:0; padding:0; }'
            + "body { font-family: 'Courier New', 'Nirmala UI', 'Iskoola Pota', 'Latha', 'Noto Sans Sinhala', 'Noto Sans Tamil', monospace; font-size: " + (narrow ? 10 : 12) + 'px; line-height: 1.5; color:#000; background:#fff; margin:0; padding:4mm; width:' + widthMm + 'mm; }'
            + '.rd-center { text-align:center; }'
            + '.rd-business { font-weight:bold; font-size:' + (narrow ? 12 : 13) + 'px; }'
            + '.rd-meta { font-size:10px; margin:1px 0; white-space:pre-line; }'
            + 'hr { border:none; border-top:1px dashed #000; margin:8px 0; }'
            + 'table { width:100%; border-collapse:collapse; font-size:' + (narrow ? 9 : 10) + 'px; margin:6px 0; }'
            + 'th { text-align:left; font-size:9px; padding:2px 2px 4px; border-bottom:1px solid #000; }'
            + 'td { padding:3px 2px; vertical-align:top; }'
            + '.rd-item-name { font-weight:bold; font-size:' + (narrow ? 10 : 11) + 'px; word-break:break-word; }'
            + '.rd-item-sub { font-size:9px; color:#555; margin-top:1px; }'
            + '.rd-right { text-align:right; }'
            + '.rd-center-col { text-align:center; }'
            + '.rd-row { display:flex; justify-content:space-between; margin:2px 0; font-size:' + (narrow ? 10 : 11) + 'px; }'
            + '.rd-total { font-weight:bold; font-size:' + (narrow ? 12 : 13) + 'px; border-top:1px solid #000; padding-top:4px; margin-top:4px; }'
            + '.rd-footer { text-align:center; font-size:10px; margin-top:6px; }'
            + '.rd-note { font-size:9px; padding:1px 0 4px; }'
            + '.rd-warranty { color:#0369a1; }'
            + '.rd-rental { color:#0f766e; }'
            + '.rd-extra { color:#6b7280; }'
            + '.rd-discount { color:#b45309; }'
            + (narrow ? '.rd-col-price, .rd-col-disc { display:none; }' : '');
    }

    // pos.js renderBillHtml
    function renderBillHtml(data, opts) {
        var s = opts.settings || {};
        var rt = labeller(opts.labels);
        var money = opts.money;
        var footer = s.footer || rt('Thank you for your purchase!');

        var rows = (data.items || []).map(function (it) {
            var hasDisc = it.discountPct > 0.05;
            var row = '<tr>'
                + '<td><div class="rd-item-name">' + esc(it.name) + '</div>' + (it.sku ? '<div class="rd-item-sub">' + esc(it.sku) + '</div>' : '') + '</td>'
                + '<td class="rd-center-col">' + esc(fmtQty(it.qty)) + '</td>'
                + '<td class="rd-right rd-col-price">' + esc(money(it.unit)) + '</td>'
                + '<td class="rd-center-col rd-col-disc' + (hasDisc ? ' rd-discount' : '') + '">' + (hasDisc ? esc(fmtPct(it.discountPct)) + '%' : '—') + '</td>'
                + '<td class="rd-right"><strong>' + esc(money(it.line)) + '</strong></td>'
                + '</tr>';
            if (it.warranty) row += '<tr><td colspan="5" class="rd-note rd-warranty">' + esc(it.warranty) + '</td></tr>';
            if (it.rental) row += '<tr><td colspan="5" class="rd-note rd-rental">' + esc(it.rental) + '</td></tr>';
            // Web-only line details (service bound products, subscriptions, custom requirements)
            (it.extras || []).forEach(function (x) {
                row += '<tr><td colspan="5" class="rd-note rd-extra">' + esc(x) + '</td></tr>';
            });
            return row;
        }).join('');

        var totals = '<div class="rd-row"><span>' + esc(rt('Subtotal')) + '</span><span>' + esc(money(data.subtotal)) + '</span></div>';
        if (data.discountAmount > 0.001) {
            var pctLabel = data.discountPercent ? ' (' + data.discountPercent + '%)' : '';
            totals += '<div class="rd-row"><span>' + esc(rt('Discount')) + pctLabel + '</span><span>&minus;' + esc(money(data.discountAmount)) + '</span></div>';
        }
        if (data.couponDiscount > 0.001) {
            totals += '<div class="rd-row"><span>' + esc(rt('Coupon')) + (data.couponCode ? ' ' + esc(data.couponCode) : '') + '</span><span>&minus;' + esc(money(data.couponDiscount)) + '</span></div>';
        }
        totals += '<div class="rd-row rd-total"><span>' + esc(rt('Total')) + '</span><span>' + esc(money(data.total)) + '</span></div>';
        if (data.giftCardAmount > 0.001) {
            totals += '<div class="rd-row"><span>' + esc(rt('Gift card')) + (data.giftCardCode ? ' ' + esc(data.giftCardCode) : '') + '</span><span>&minus;' + esc(money(data.giftCardAmount)) + '</span></div>';
            if (data.giftCardBalance != null) totals += '<div class="rd-row"><span>' + esc(rt('Gift card balance')) + '</span><span>' + esc(money(data.giftCardBalance)) + '</span></div>';
        }
        if (data.amountTendered != null) {
            totals += '<div class="rd-row"><span>' + esc(rt('Cash Received')) + '</span><span>' + esc(money(data.amountTendered)) + '</span></div>';
        } else {
            var paidVia = data.giftCardAmount > 0.001 ? Math.max(0, Number(data.total) - data.giftCardAmount) : (data.amountPaid != null ? data.amountPaid : data.total);
            totals += '<div class="rd-row"><span>' + esc(rt('Paid')) + (data.paymentLabel ? ' (' + esc(data.paymentLabel) + ')' : '') + '</span><span>' + esc(money(paidVia)) + '</span></div>';
        }
        if (data.amountTendered != null || Number(data.changeAmount) > 0.005) {
            totals += '<div class="rd-row"><span>' + esc(rt('Change')) + '</span><span>' + esc(money(data.changeAmount || 0)) + '</span></div>';
        }

        return ''
            + '<div class="rd-center">'
            + (s.logoUrl ? '<div style="margin-bottom:' + (Number(s.logoGap) || 0) + 'mm"><img src="' + esc(s.logoUrl) + '" alt="" style="max-width:64px;max-height:64px;display:block;margin:0 auto"></div>' : '')
            + (s.showName ? '<div class="rd-business">' + esc(s.businessName) + '</div>' : '')
            + (s.showAddr && s.address ? '<div class="rd-meta">' + esc(s.address) + '</div>' : '')
            + (s.header ? '<div class="rd-meta" style="font-style:italic">' + esc(s.header) + '</div>' : '')
            + '</div>'
            + '<hr>'
            + '<div class="rd-row"><span>' + esc(rt('Receipt #')) + '</span><strong>' + esc(data.saleNumber) + '</strong></div>'
            + '<div class="rd-row"><span>' + esc(rt('Date & Time')) + '</span><strong>' + esc(data.soldAt) + '</strong></div>'
            + '<div class="rd-row"><span>' + esc(rt('Payment')) + '</span><strong>' + esc(data.paymentLabel) + '</strong></div>'
            + (data.customerName ? '<div class="rd-row"><span>' + esc(rt('Customer')) + '</span><strong>' + esc(data.customerName) + '</strong></div>' : '')
            + (data.cashierName ? '<div class="rd-row"><span>' + esc(rt('Cashier')) + '</span><strong>' + esc(data.cashierName) + '</strong></div>' : '')
            + '<hr>'
            + '<table>'
            + '<thead><tr><th>' + esc(rt('Item')) + '</th><th class="rd-center-col">' + esc(rt('Qty')) + '</th><th class="rd-right rd-col-price">' + esc(rt('Price')) + '</th><th class="rd-center-col rd-col-disc">' + esc(rt('Disc')) + '</th><th class="rd-right">' + esc(rt('Amount')) + '</th></tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table>'
            + '<hr>'
            + totals
            + (data.notes ? '<hr><div class="rd-note">' + esc(rt('Notes')) + ': ' + esc(data.notes) + '</div>' : '')
            + '<hr>'
            + '<div class="rd-footer">' + esc(footer) + '</div>';
    }

    // pos.js buildFullReceiptDocument + ReceiptPrinter's margin padding (browser = Lite's preview path:
    // every side pads inside the fixed body width, so the receipt stays on the roll)
    function buildDocument(data, opts) {
        var paperWidth = opts.paperWidth === 58 ? 58 : 80;
        var mg = opts.margins || {};
        // Receipt margins add to the 4mm base edge
        var side = function (k) { return 4 + Math.min(20, Math.max(0, parseFloat(mg[k]) || 0)); };
        var mgCss = 'body{padding:' + side('top') + 'mm ' + side('right') + 'mm ' + side('bottom') + 'mm ' + side('left') + 'mm;}';
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + esc(data.saleNumber || '') + '</title>'
            + '<style>' + docStyles(paperWidth) + mgCss + '</style></head><body>'
            + renderBillHtml(data, opts)
            + '</body></html>';
    }

    // Money formatter matching Lite's money(): no thousands separator, currency code before/after
    function moneyFormatter(currency, position) {
        var cur = (currency || '').toUpperCase();
        return function (n) {
            var v = (Number(n) || 0).toFixed(2);
            if (!cur) return v;
            return position === 'before' ? cur + ' ' + v : v + ' ' + cur;
        };
    }

    // Browsers ignore `size: <w> auto` and print on a full-length page, so once the receipt has
    // rendered, measure it and pin the page height to the content (call right before print()).
    function fitPageToContent(doc, paperWidth) {
        if (!doc || !doc.body) return;
        var widthMm = paperWidth === 58 ? 58 : 80;
        var heightPx = Math.max(doc.body.scrollHeight, doc.body.getBoundingClientRect().height);
        var heightMm = Math.ceil(heightPx * 25.4 / 96) + 2;
        var style = doc.getElementById('rd-page-size') || doc.createElement('style');
        style.id = 'rd-page-size';
        style.textContent = '@page { size: ' + widthMm + 'mm ' + heightMm + 'mm; margin: 0; }';
        (doc.head || doc.documentElement).appendChild(style);
    }

    // Preview width on screen (≈ mm at 96dpi) — ReceiptPrinter.previewWidthPx
    function previewWidthPx(paperWidth) {
        return paperWidth === 58 ? 219 : 302;
    }

    window.PosLiteReceipt = { docStyles: docStyles, renderBillHtml: renderBillHtml, buildDocument: buildDocument, moneyFormatter: moneyFormatter, fitPageToContent: fitPageToContent, previewWidthPx: previewWidthPx };
})();
</script>
@endonce
