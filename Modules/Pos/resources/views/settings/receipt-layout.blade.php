@extends('theme::layouts.app', ['title' => 'Receipt Setting', 'heading' => 'Receipt Setting'])

@section('content')
@php
    $s = $posSettings;
    $lang = in_array($s['receipt_language'] ?? 'en', ['en', 'si', 'ta'], true) ? $s['receipt_language'] : 'en';
    $marginSides = ['top' => 'Top', 'left' => 'Left', 'right' => 'Right', 'bottom' => 'Bottom'];
@endphp
<style>
.rls-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;align-items:start;}
@media (max-width:900px){.rls-layout{grid-template-columns:1fr;}}
.rls-section__label{display:flex;align-items:center;gap:7px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);margin:20px 0 4px;padding-bottom:8px;border-bottom:1px solid var(--border);}
.rls-section__label:first-child{margin-top:0;}
.rls-label{display:block;font-size:13px;font-weight:700;color:var(--text);margin:0 0 8px;}
.rls-input,.rls-textarea{width:100%;box-sizing:border-box;padding:9px 12px;font-size:13px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-family:inherit;}
.rls-textarea{resize:vertical;min-height:58px;margin-top:10px;}
.rls-input:focus,.rls-textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.rls-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:11px 0;border-bottom:1px solid var(--border);}
.rls-row__name{font-size:13px;font-weight:700;color:var(--text);}
.rls-row__desc{font-size:11.5px;color:var(--muted);margin-top:2px;}
.rls-switch{position:relative;flex-shrink:0;display:inline-flex;cursor:pointer;}
.rls-switch input{position:absolute;opacity:0;width:0;height:0;}
.rls-switch__track{display:block;width:42px;height:24px;border-radius:100px;background:color-mix(in srgb,var(--border) 80%,transparent);border:1px solid var(--border);position:relative;transition:background .2s;}
.rls-switch__thumb{position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.2);transition:transform .2s;}
.rls-switch input:checked~.rls-switch__track{background:var(--primary);border-color:var(--primary);}
.rls-switch input:checked~.rls-switch__track .rls-switch__thumb{transform:translateX(18px);}
.rls-switch input:disabled~.rls-switch__track{opacity:.55;cursor:not-allowed;}
.rls-switch input:focus-visible~.rls-switch__track{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 25%,transparent);}
.rls-logo-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.rls-logo-preview{width:72px;height:72px;border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;justify-content:center;background:#fff;color:#9ca3af;overflow:hidden;font-size:22px;}
.rls-logo-preview img{max-width:100%;max-height:100%;object-fit:contain;}
.rls-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 14px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;}
.rls-btn--danger{color:#dc2626;border-color:color-mix(in srgb,#dc2626 50%,var(--border));}
.rls-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.rls-mm{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--border);border-radius:9px;padding:0 10px;background:var(--card);}
.rls-mm input{width:56px;border:none;background:transparent;color:var(--text);font-size:13px;padding:8px 0;text-align:right;}
.rls-mm input:focus{outline:none;}
.rls-mm em{font-style:normal;font-size:11px;color:var(--muted);}
.rls-gap-row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;font-size:13px;color:var(--text);}
.rls-hint{font-size:11.5px;color:var(--muted);margin:8px 0 10px;line-height:1.45;}
.rls-margins{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;}
.rls-margins label{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:12.5px;color:var(--text);}
.rls-lang-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;}
.rls-lang-btn{padding:8px 16px;border-radius:999px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:13px;font-weight:600;cursor:pointer;}
.rls-lang-btn.is-active{background:var(--primary);border-color:var(--primary);color:#fff;}
.rls-preview-pane{position:sticky;top:16px;border:1px solid var(--border);border-radius:12px;background:color-mix(in srgb,var(--card) 92%,var(--border) 8%);padding:16px;display:flex;flex-direction:column;align-items:center;}
.rls-preview-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 12px;}
.rls-paper{--mg-top:0mm;--mg-right:0mm;--mg-bottom:0mm;--mg-left:0mm;box-sizing:border-box;width:80mm;max-width:100%;background:#fff;color:#000;font-family:'Courier New',monospace;font-size:11px;line-height:1.45;padding:calc(4mm + var(--mg-top)) calc(4mm + var(--mg-right)) calc(4mm + var(--mg-bottom)) calc(4mm + var(--mg-left));border-radius:4px;box-shadow:0 4px 18px rgba(0,0,0,.18);}
.rls-paper[data-width="58"]{width:58mm;}
.rls-paper .c{text-align:center;}
.rls-paper .biz{font-weight:bold;font-size:14px;}
.rls-paper .meta{font-size:10px;white-space:pre-line;}
.rls-paper .rule{border-top:1px dashed #000;margin:7px 0;}
.rls-paper .rule-solid{border-top:1px solid #000;margin:7px 0;}
.rls-paper .kv{display:flex;justify-content:space-between;gap:8px;}
.rls-paper .kv-total{font-weight:bold;font-size:13px;}
.rls-paper .kv-change{color:#16a34a;}
.rls-paper .items{display:grid;grid-template-columns:1fr auto auto auto;gap:2px 8px;}
.rls-paper .items .h{font-weight:bold;}
.rls-paper .logo{text-align:center;}
.rls-paper .logo img{max-width:64px;max-height:64px;}
.rls-actions{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:18px;padding-top:14px;border-top:1px solid var(--border);}
</style>

<div class="card" style="max-width:100%;padding:16px;">
    @include('pos::settings.partials.print-settings-nav')

    @if(session('status'))
        <div class="prs-ok"><i class="fa fa-check" aria-hidden="true"></i> {{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div style="margin-bottom:12px;color:#ef4444;font-size:13px;">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Design how printed bills look in Bill mode. The same layout is used by the web POS, POS Lite and the desktop app.
    </p>

    <form method="POST" action="{{ route('settings.receipt.update') }}" enctype="multipart/form-data" id="rls-form">
        @csrf
        <div class="rls-layout">
            <div>
                <p class="rls-section__label"><i class="fa fa-image" aria-hidden="true"></i> Receipt Logo</p>
                <div class="rls-logo-row">
                    <div class="rls-logo-preview" id="rls-logo-preview"><i class="fa fa-image" aria-hidden="true"></i></div>
                    <label class="rls-btn" for="rls-logo-input"><i class="fa fa-folder-open" aria-hidden="true"></i> Choose image</label>
                    <input type="file" name="receipt_logo" id="rls-logo-input" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                    <button type="button" class="rls-btn rls-btn--danger" id="rls-logo-remove" title="Remove logo" aria-label="Remove logo"><i class="fa fa-trash" aria-hidden="true"></i></button>
                    <input type="hidden" name="remove_receipt_logo" id="rls-logo-remove-flag" value="0">
                </div>
                <label class="rls-gap-row" for="rls-logo-gap">
                    <span>Gap below logo</span>
                    <span class="rls-mm"><input type="number" name="receipt_logo_gap" id="rls-logo-gap" min="0" max="20" step="0.5" value="{{ $s['receipt_logo_gap'] }}"><em>mm</em></span>
                </label>

                <p class="rls-section__label"><i class="fa fa-building" aria-hidden="true"></i> Business Info</p>
                <div class="rls-row">
                    <span class="rls-row__name">Show business name</span>
                    <label class="rls-switch"><input type="checkbox" name="show_business_name" value="1" id="rls-show-biz" @checked($s['show_business_name'])><span class="rls-switch__track"><span class="rls-switch__thumb"></span></span></label>
                </div>
                <div class="rls-row" style="border-bottom:none;">
                    <span class="rls-row__name">Show address</span>
                    <label class="rls-switch"><input type="checkbox" name="show_business_address" value="1" id="rls-show-addr" @checked($s['show_business_address'])><span class="rls-switch__track"><span class="rls-switch__thumb"></span></span></label>
                </div>
                <textarea name="receipt_address_line" id="rls-address" class="rls-textarea" rows="2" maxlength="300" placeholder="Street, City, Phone...">{{ $s['receipt_address_line'] }}</textarea>

                <p class="rls-section__label"><i class="fa fa-heading" aria-hidden="true"></i> Header Text</p>
                <textarea name="receipt_header" id="rls-header" class="rls-textarea" rows="2" maxlength="200" placeholder="Tagline, website, hours...">{{ $s['receipt_header'] }}</textarea>

                <p class="rls-section__label"><i class="fa fa-comment" aria-hidden="true"></i> Footer / Thank-you</p>
                <textarea name="receipt_footer" id="rls-footer" class="rls-textarea" rows="2" maxlength="200" placeholder="Thank you for your purchase!">{{ $s['receipt_footer'] }}</textarea>

                <p class="rls-section__label"><i class="fa fa-eye" aria-hidden="true"></i> Show on every receipt</p>
                <div class="rls-row">
                    <div><div class="rls-row__name">Cashier name</div><div class="rls-row__desc">Always included on receipts</div></div>
                    <label class="rls-switch"><input type="checkbox" checked disabled><span class="rls-switch__track"><span class="rls-switch__thumb"></span></span></label>
                </div>
                <div class="rls-row">
                    <span class="rls-row__name">Payment details</span>
                    <label class="rls-switch"><input type="checkbox" name="show_account_info" value="1" id="rls-show-payment" @checked($s['show_account_info'])><span class="rls-switch__track"><span class="rls-switch__thumb"></span></span></label>
                </div>

                <p class="rls-section__label"><i class="fa fa-expand" aria-hidden="true"></i> Print margins (mm)</p>
                <p class="rls-hint">Increase a side if your printer cuts it off or the bill prints off-centre.</p>
                <div class="rls-margins">
                    @foreach($marginSides as $side => $label)
                        <label for="rls-mg-{{ $side }}">
                            <span>{{ $label }}</span>
                            <span class="rls-mm"><input type="number" name="receipt_margin_{{ $side }}" id="rls-mg-{{ $side }}" min="0" max="20" step="0.5" value="{{ $s['receipt_margin_'.$side] }}" data-mg-side="{{ $side }}"><em>mm</em></span>
                        </label>
                    @endforeach
                </div>
                <button type="button" class="rls-btn" id="rls-mg-reset" style="margin-top:10px;"><i class="fa fa-rotate-left" aria-hidden="true"></i> Reset margins</button>

                <p class="rls-section__label"><i class="fa fa-language" aria-hidden="true"></i> Print Language</p>
                <div class="rls-lang-row" role="radiogroup" aria-label="Print language">
                    @foreach(\Modules\Pos\Support\ReceiptLabels::LANGUAGES as $code => $label)
                        <button type="button" class="rls-lang-btn @if($lang === $code) is-active @endif" data-lang="{{ $code }}" role="radio" aria-checked="{{ $lang === $code ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <input type="hidden" name="receipt_language" id="rls-lang" value="{{ $lang }}">
            </div>

            <div class="rls-preview-pane">
                <p class="rls-preview-label">Live Preview</p>
                <div class="rls-paper" id="rls-paper" data-width="{{ $s['receipt_paper_width'] }}"></div>
            </div>
        </div>

        <div class="rls-actions">
            <button type="button" class="rls-btn" id="rls-test" title="Print a sample bill with the current settings (not saved)"><i class="fa fa-print" aria-hidden="true"></i> Test print</button>
            <button type="submit" class="rls-btn rls-btn--primary"><i class="fa fa-floppy-disk" aria-hidden="true"></i> Save layout</button>
        </div>
    </form>
</div>

@include('pos::partials.pos-lite-receipt-renderer')
<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var LABELS = @json(\Modules\Pos\Support\ReceiptLabels::STRINGS);
    var state = {
        logoUrl: @json($s['receipt_logo_url'] ?: ''),
        businessName: @json($s['business_name'] ?: 'Your Business'),
        currency: @json(strtoupper($s['currency'] ?: '')),
        currencyPosition: @json($s['currency_position']),
        paperWidth: @json($s['receipt_paper_width']) === '58' ? 58 : 80,
    };
    var SIDES = ['top', 'right', 'bottom', 'left'];
    var SAMPLE = { subtotal: 155, discountPct: 3, discount: 5, total: 150, paid: 160, change: 10 };
    var ROWS = [['Product One', 2, 25, 50], ['Product Two', 1, 75, 75], ['Product Three', 3, 10, 30]];

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function L(key) {
        var dict = LABELS[$('rls-lang').value] || {};
        return Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : key;
    }
    function mm(v) {
        var n = parseFloat(v);
        return isFinite(n) ? Math.min(20, Math.max(0, n)) : 0;
    }
    function money(n) {
        var v = (Number(n) || 0).toFixed(2);
        if (!state.currency) return v;
        return state.currencyPosition === 'before' ? state.currency + ' ' + v : v + ' ' + state.currency;
    }
    function current() {
        return {
            showBiz: $('rls-show-biz').checked,
            showAddr: $('rls-show-addr').checked,
            address: $('rls-address').value.trim(),
            header: $('rls-header').value.trim(),
            footer: $('rls-footer').value.trim(),
            showPayment: $('rls-show-payment').checked,
            gap: mm($('rls-logo-gap').value),
        };
    }

    // Body shared by the live preview and the test print
    function receiptBody(c) {
        var dateLocale = { en: 'en-US', si: 'si-LK', ta: 'ta-LK' }[$('rls-lang').value];
        var now = new Date().toLocaleString(dateLocale, { dateStyle: 'short', timeStyle: 'short' });
        var items = ROWS.map(function (r) {
            return '<span>' + esc(r[0]) + '</span><span>' + r[1] + '</span><span>' + r[2].toFixed(2) + '</span><span>' + r[3].toFixed(2) + '</span>';
        }).join('');
        return (state.logoUrl ? '<div class="logo" style="margin-bottom:' + c.gap + 'mm"><img src="' + esc(state.logoUrl) + '" alt=""></div>' : '')
            + '<div class="c">'
            + (c.showBiz ? '<div class="biz">' + esc(state.businessName) + '</div>' : '')
            + (c.showAddr && c.address ? '<div class="meta">' + esc(c.address) + '</div>' : '')
            + (c.header ? '<div class="meta">' + esc(c.header) + '</div>' : '')
            + '</div><div class="rule"></div>'
            + '<div class="kv"><span>' + esc(L('Receipt #')) + '</span><span>INV-0001</span></div>'
            + '<div class="kv"><span>' + esc(L('Date & Time')) + '</span><span>' + esc(now) + '</span></div>'
            + '<div class="kv"><span>' + esc(L('Customer')) + '</span><span>' + esc(L('Walk-in Customer')) + '</span></div>'
            + '<div class="kv"><span>' + esc(L('Cashier')) + '</span><span>' + esc(L('Cashier')) + '</span></div>'
            + '<div class="rule"></div>'
            + '<div class="items"><span class="h">' + esc(L('Item')) + '</span><span class="h">' + esc(L('Qty')) + '</span><span class="h">' + esc(L('Price')) + '</span><span class="h">' + esc(L('Amount')) + '</span>' + items + '</div>'
            + '<div class="rule"></div>'
            + '<div class="kv"><span>' + esc(L('Subtotal')) + '</span><span>' + esc(money(SAMPLE.subtotal)) + '</span></div>'
            + '<div class="kv"><span>' + esc(L('Discount')) + ' (' + SAMPLE.discountPct + '%)</span><span>-' + esc(money(SAMPLE.discount)) + '</span></div>'
            + '<div class="rule-solid"></div>'
            + '<div class="kv kv-total"><span>' + esc(L('Total')) + '</span><span>' + esc(money(SAMPLE.total)) + '</span></div>'
            + (c.showPayment
                ? '<div class="kv"><span>' + esc(L('Cash Received')) + '</span><span>' + esc(money(SAMPLE.paid)) + '</span></div>'
                  + '<div class="kv kv-change"><span>' + esc(L('Change')) + '</span><span>' + esc(money(SAMPLE.change)) + '</span></div>'
                : '')
            + (c.footer ? '<div class="c" style="margin-top:8px;">' + esc(c.footer) + '</div>' : '');
    }

    function renderLogo() {
        $('rls-logo-preview').innerHTML = state.logoUrl ? '<img src="' + esc(state.logoUrl) + '" alt="Receipt logo">' : '<i class="fa fa-image" aria-hidden="true"></i>';
        $('rls-logo-remove').style.display = state.logoUrl ? '' : 'none';
    }

    function renderPreview() {
        var paper = $('rls-paper');
        paper.innerHTML = receiptBody(current());
        SIDES.forEach(function (side) { paper.style.setProperty('--mg-' + side, mm($('rls-mg-' + side).value) + 'mm'); });
    }

    $('rls-logo-input').addEventListener('change', function () {
        var f = this.files && this.files[0];
        if (!f) return;
        state.logoUrl = URL.createObjectURL(f);
        $('rls-logo-remove-flag').value = '0';
        renderLogo();
        renderPreview();
    });
    $('rls-logo-remove').addEventListener('click', function () {
        state.logoUrl = '';
        $('rls-logo-input').value = '';
        $('rls-logo-remove-flag').value = '1';
        renderLogo();
        renderPreview();
    });

    document.querySelectorAll('.rls-lang-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            $('rls-lang').value = btn.dataset.lang;
            document.querySelectorAll('.rls-lang-btn').forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-checked', on ? 'true' : 'false');
            });
            renderPreview();
        });
    });

    $('rls-mg-reset').addEventListener('click', function () {
        SIDES.forEach(function (side) { $('rls-mg-' + side).value = 0; });
        renderPreview();
    });

    $('rls-form').addEventListener('input', renderPreview);
    $('rls-form').addEventListener('change', renderPreview);

    // Test print: sample bill with the current (unsaved) layout, in the real POS bill format
    // (same as POS Lite's Test print — navbar.js testPrint), through the browser print dialog
    $('rls-test').addEventListener('click', function () {
        var w = window.open('', '_blank', 'width=420,height=640');
        if (!w) { alert('Please allow pop-ups to print.'); return; }
        var c = current();
        var margins = {};
        SIDES.forEach(function (side) { margins[side] = mm($('rls-mg-' + side).value); });
        var sample = {
            saleNumber: 'TEST-0001', soldAt: new Date().toLocaleString(), paymentLabel: L('Cash'),
            customerName: L('Walk-in Customer'), cashierName: '', notes: '',
            subtotal: SAMPLE.subtotal, discountPercent: SAMPLE.discountPct, discountAmount: SAMPLE.discount,
            total: SAMPLE.total, amountPaid: c.showPayment ? SAMPLE.paid : SAMPLE.total,
            amountTendered: c.showPayment ? SAMPLE.paid : null, changeAmount: c.showPayment ? SAMPLE.change : 0,
            items: ROWS.map(function (r) { return { name: r[0], qty: r[1], unit: r[2], line: r[3], discountPct: 0 }; }),
        };
        w.document.write(window.PosLiteReceipt.buildDocument(sample, {
            settings: {
                showName: c.showBiz, showAddr: c.showAddr, businessName: state.businessName, address: c.address,
                header: c.header, footer: c.footer, logoUrl: state.logoUrl, logoGap: c.gap,
            },
            labels: LABELS[$('rls-lang').value] || {},
            money: money,
            paperWidth: state.paperWidth,
            margins: margins,
        }));
        w.document.close();
        w.focus();
        w.addEventListener('afterprint', function () { try { w.close(); } catch (e) {} });
        // Give the logo a moment to load before the dialog snapshots the page
        setTimeout(function () {
            window.PosLiteReceipt.fitPageToContent(w.document, state.paperWidth);
            w.print();
        }, 350);
    });

    renderLogo();
    renderPreview();
})();
</script>
@endsection
