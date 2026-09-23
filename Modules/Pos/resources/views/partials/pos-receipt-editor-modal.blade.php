@php
    $posSettings = $posSettings ?? [];
    $business    = $business ?? null;
    $currency    = $currency ?? '';
    $redirectUrl = url()->current();
    $paperWidth  = (string) ($posSettings['receipt_paper_width'] ?? '80');
@endphp

<div id="pos-receipt-editor-modal" class="rem-overlay" role="dialog" aria-modal="true" aria-labelledby="pos-receipt-editor-title" aria-hidden="true">
    <div class="rem-backdrop" data-rem-close tabindex="-1" aria-label="Close"></div>
    <div class="rem-dialog">
        <div class="rem-head">
            <div>
                <h2 id="pos-receipt-editor-title" class="rem-head__title"><i class="fa fa-receipt" aria-hidden="true"></i> Receipt Editor</h2>
                <p class="rem-head__subtitle">Customize what prints on your receipts, with a live preview</p>
            </div>
            <button type="button" class="rem-close" data-rem-close aria-label="Close">
                <i class="fa fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <form method="post" action="{{ $settingsFormAction ?? route('pos.settings.save') }}" class="rem-body" id="rem-form">
            @csrf
            <input type="hidden" name="redirect" value="{{ $redirectUrl }}">

            {{-- ── Settings panel ─────────────────────────────── --}}
            <div class="rem-settings">
                <div class="rem-section">
                    <p class="rem-section__label"><i class="fa fa-align-left" aria-hidden="true"></i> Header &amp; footer</p>
                    <div class="rem-card">
                        <div class="rem-field">
                            <label class="rem-field__label" for="rem-receipt-header">Header text</label>
                            <input type="text" name="receipt_header" id="rem-receipt-header" class="rem-input" maxlength="200"
                                data-rem-preview
                                value="{{ $posSettings['receipt_header'] ?? '' }}"
                                placeholder="e.g. Welcome to our store!">
                        </div>
                        <div class="rem-field" style="margin-bottom:0;">
                            <label class="rem-field__label" for="rem-receipt-footer">Footer text</label>
                            <input type="text" name="receipt_footer" id="rem-receipt-footer" class="rem-input" maxlength="200"
                                data-rem-preview
                                value="{{ $posSettings['receipt_footer'] ?? 'Thank you for your purchase!' }}"
                                placeholder="e.g. Thank you for your purchase!">
                        </div>
                    </div>
                </div>

                <div class="rem-section">
                    <p class="rem-section__label"><i class="fa fa-building" aria-hidden="true"></i> Business info</p>
                    <div class="rem-card">
                        <div class="rem-row">
                            <div class="rem-row__info">
                                <span class="rem-row__name">Show business name</span>
                                <span class="rem-row__desc">Print your business name at the top</span>
                            </div>
                            <label class="rem-switch">
                                <input type="hidden" name="show_business_name" value="0">
                                <input type="checkbox" name="show_business_name" value="1" data-rem-preview @checked($posSettings['show_business_name'] ?? true)>
                                <span class="rem-switch__track" aria-hidden="true"><span class="rem-switch__thumb"></span></span>
                            </label>
                        </div>
                        <div class="rem-row rem-row--border">
                            <div class="rem-row__info">
                                <span class="rem-row__name">Show business address</span>
                                <span class="rem-row__desc">Include your address on printed receipts</span>
                            </div>
                            <label class="rem-switch">
                                <input type="hidden" name="show_business_address" value="0">
                                <input type="checkbox" name="show_business_address" value="1" data-rem-preview @checked($posSettings['show_business_address'] ?? true)>
                                <span class="rem-switch__track" aria-hidden="true"><span class="rem-switch__thumb"></span></span>
                            </label>
                        </div>
                        <div class="rem-row rem-row--border">
                            <div class="rem-row__info">
                                <span class="rem-row__name">Show account info</span>
                                <span class="rem-row__desc">Print the deposit / credit account name</span>
                            </div>
                            <label class="rem-switch">
                                <input type="hidden" name="show_account_info" value="0">
                                <input type="checkbox" name="show_account_info" value="1" data-rem-preview @checked($posSettings['show_account_info'] ?? true)>
                                <span class="rem-switch__track" aria-hidden="true"><span class="rem-switch__thumb"></span></span>
                            </label>
                        </div>
                        <div class="rem-row rem-row--border">
                            <div class="rem-row__info">
                                <span class="rem-row__name">Show service bound products</span>
                                <span class="rem-row__desc">List attached products under each service line</span>
                            </div>
                            <label class="rem-switch">
                                <input type="hidden" name="show_service_bound_products" value="0">
                                <input type="checkbox" name="show_service_bound_products" value="1" data-rem-preview @checked($posSettings['show_service_bound_products'] ?? true)>
                                <span class="rem-switch__track" aria-hidden="true"><span class="rem-switch__thumb"></span></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="rem-section">
                    <p class="rem-section__label"><i class="fa fa-ruler-horizontal" aria-hidden="true"></i> Paper width</p>
                    <div class="rem-card">
                        <div class="rem-row" style="padding:14px 16px;">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;width:100%;">
                                <label class="rem-width-option {{ $paperWidth === '58' ? 'is-active' : '' }}" id="rem-width-58-label">
                                    <input type="radio" name="_paper_width_ui" value="58" {{ $paperWidth === '58' ? 'checked' : '' }} style="display:none;">
                                    <i class="fa fa-receipt"></i>
                                    <span class="rem-width-option__title">58mm</span>
                                    <span class="rem-width-option__desc">Compact thermal</span>
                                </label>
                                <label class="rem-width-option {{ $paperWidth === '80' ? 'is-active' : '' }}" id="rem-width-80-label">
                                    <input type="radio" name="_paper_width_ui" value="80" {{ $paperWidth === '80' ? 'checked' : '' }} style="display:none;">
                                    <i class="fa fa-receipt"></i>
                                    <span class="rem-width-option__title">80mm</span>
                                    <span class="rem-width-option__desc">Standard thermal</span>
                                </label>
                            </div>
                            <input type="hidden" name="receipt_paper_width" id="rem-paper-width-value" value="{{ $paperWidth }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Live preview panel ─────────────────────────────── --}}
            <div class="rem-preview-wrap">
                <p class="rem-preview-wrap__label"><i class="fa fa-eye" aria-hidden="true"></i> Live preview</p>
                <div class="rem-preview-paper" id="rem-preview-paper" data-width="{{ $paperWidth }}">
                    <div id="rem-preview-content"></div>
                </div>
            </div>
        </form>

        <div class="rem-footer">
            <button type="button" class="rem-btn rem-btn--ghost" data-rem-close>Cancel</button>
            <button type="submit" form="rem-form" class="rem-btn rem-btn--primary">
                <i class="fa fa-floppy-disk" aria-hidden="true"></i> Save receipt layout
            </button>
        </div>
    </div>
</div>

@once
<style>
/* ── Overlay & dialog shell ─────────────────────────────────────── */
.rem-overlay{position:fixed;inset:0;z-index:210;display:flex;align-items:center;justify-content:center;padding:16px;visibility:hidden;opacity:0;pointer-events:none;transition:opacity .25s cubic-bezier(.4,0,.2,1),visibility .25s;}
.rem-overlay.is-open{visibility:visible;opacity:1;pointer-events:auto;}
.rem-backdrop{position:absolute;inset:0;background:rgba(2,6,23,.6);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);}
html.pos-receipt-editor-open,html.pos-receipt-editor-open body{overflow:hidden;}

.rem-dialog{position:relative;z-index:1;display:flex;flex-direction:column;width:min(100%,960px);max-height:min(92vh,720px);border-radius:20px;overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,.36),0 0 0 1px rgba(255,255,255,.06);transform:translateY(12px) scale(.97);transition:transform .28s cubic-bezier(.34,1.2,.64,1);background:var(--card);}
.rem-overlay.is-open .rem-dialog{transform:translateY(0) scale(1);}

/* ── Header / footer bars ───────────────────────────────────────── */
.rem-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:20px 24px 16px;border-bottom:1px solid var(--border);flex-shrink:0;}
.rem-head__title{margin:0;font-size:15px;font-weight:800;color:var(--text);letter-spacing:-.02em;display:flex;align-items:center;gap:8px;}
.rem-head__subtitle{margin:3px 0 0;font-size:11.5px;color:var(--muted);}
.rem-close{width:32px;height:32px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-size:13px;transition:all .15s;}
.rem-close:hover{border-color:var(--text);color:var(--text);background:color-mix(in srgb,var(--border) 40%,transparent);}
.rem-footer{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:14px 24px;border-top:1px solid var(--border);flex-shrink:0;background:color-mix(in srgb,var(--card) 94%,var(--border) 6%);}
.rem-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid transparent;cursor:pointer;transition:all .15s;}
.rem-btn--ghost{background:transparent;border-color:var(--border);color:var(--muted);}
.rem-btn--ghost:hover{border-color:var(--text);color:var(--text);background:color-mix(in srgb,var(--border) 30%,transparent);}
.rem-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.rem-btn--primary:hover{opacity:.9;box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 35%,transparent);}

/* ── Body: two columns ──────────────────────────────────────────── */
.rem-body{flex:1;min-height:0;display:flex;gap:0;overflow:hidden;}
.rem-settings{flex:1 1 52%;min-width:0;overflow-y:auto;padding:20px 24px;border-right:1px solid var(--border);}
.rem-preview-wrap{flex:1 1 48%;min-width:0;overflow-y:auto;padding:20px 24px;background:color-mix(in srgb,var(--card) 92%,var(--border) 8%);display:flex;flex-direction:column;align-items:center;}
.rem-preview-wrap__label{align-self:flex-start;display:flex;align-items:center;gap:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 12px;}

/* ── Section / card / field / row (mirrors pos-settings-modal) ──── */
.rem-section{margin-bottom:20px;}
.rem-section:last-child{margin-bottom:0;}
.rem-section__label{display:flex;align-items:center;gap:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 10px;padding:0 2px;}
.rem-card{background:color-mix(in srgb,var(--card) 96%,var(--border) 4%);border:1px solid var(--border);border-radius:12px;overflow:hidden;}
.rem-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:13px 16px;}
.rem-row--border{border-top:1px solid var(--border);}
.rem-row__info{display:flex;flex-direction:column;gap:2px;min-width:0;}
.rem-row__name{font-size:13px;font-weight:600;color:var(--text);}
.rem-row__desc{font-size:11px;color:var(--muted);line-height:1.4;}
.rem-field{padding:14px 16px;border-bottom:1px solid var(--border);}
.rem-field:last-child{border-bottom:none;}
.rem-field__label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px;}
.rem-input{width:100%;box-sizing:border-box;padding:9px 12px;font-size:13px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);outline:none;transition:border-color .15s;}
.rem-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}

/* ── Toggle switch ──────────────────────────────────────────────── */
.rem-switch{position:relative;flex-shrink:0;display:inline-flex;cursor:pointer;}
.rem-switch input{position:absolute;opacity:0;width:0;height:0;pointer-events:none;}
.rem-switch__track{display:block;width:42px;height:24px;border-radius:100px;background:color-mix(in srgb,var(--border) 80%,transparent);border:1px solid var(--border);transition:background .2s,border-color .2s;position:relative;}
.rem-switch__thumb{position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.2);transition:transform .2s cubic-bezier(.34,1.3,.64,1);}
.rem-switch input:checked~.rem-switch__track{background:var(--primary);border-color:var(--primary);}
.rem-switch input:checked~.rem-switch__track .rem-switch__thumb{transform:translateX(18px);}

/* ── Paper width toggle ─────────────────────────────────────────── */
.rem-width-option{display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 10px;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,var(--border) 6%);cursor:pointer;transition:all .15s;text-align:center;}
.rem-width-option i{font-size:18px;color:var(--muted);}
.rem-width-option.is-active{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.rem-width-option.is-active i{color:var(--primary);}
.rem-width-option__title{font-size:12px;font-weight:700;color:var(--text);}
.rem-width-option__desc{font-size:10px;color:var(--muted);}

/* ── Live preview paper mockup ──────────────────────────────────── */
.rem-preview-paper{width:302px;max-width:100%;background:#fff;color:#000;font-family:'Courier New',monospace;font-size:11px;line-height:1.5;padding:16px 14px;border-radius:4px;box-shadow:0 4px 18px rgba(0,0,0,.18);transition:width .2s ease;}
.rem-preview-paper[data-width="58"]{width:230px;}
.rem-prev-center{text-align:center;}
.rem-prev-business{font-weight:bold;font-size:13px;margin-bottom:2px;}
.rem-prev-meta{font-size:10px;margin:1px 0;}
.rem-prev-header-text{font-size:10px;font-style:italic;margin:4px 0;}
.rem-prev-divider{border-top:1px dashed #000;margin:8px 0;}
.rem-prev-row{display:flex;justify-content:space-between;margin:2px 0;font-size:10.5px;}
.rem-prev-items{width:100%;border-collapse:collapse;margin:8px 0;font-size:10px;}
.rem-prev-items th{font-size:9px;font-weight:bold;text-align:left;padding:2px 2px 4px;border-bottom:1px solid #000;}
.rem-prev-items td{padding:3px 2px;border-bottom:1px dashed #ccc;}
.rem-prev-total-final{display:flex;justify-content:space-between;margin:6px 0 0;padding-top:4px;border-top:1px solid #000;font-size:12px;font-weight:bold;}
.rem-prev-footer{text-align:center;font-size:10px;margin-top:10px;}

/* ── Responsive ─────────────────────────────────────────────────── */
@media (max-width:760px){
    .rem-body{flex-direction:column;overflow-y:auto;}
    .rem-settings{border-right:none;border-bottom:1px solid var(--border);overflow-y:visible;}
    .rem-preview-wrap{overflow-y:visible;}
    .rem-dialog{max-height:96vh;}
}
</style>

<script>
(function () {
    var modal   = document.getElementById('pos-receipt-editor-modal');
    if (!modal) return;

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-receipt-editor-open', open);
    }

    // Exposed so other UI (e.g. the Print Layout tab in pos-settings-modal) can open this editor.
    window.openPosReceiptEditor = function () { setOpen(true); };

    document.querySelectorAll('[data-pos-receipt-editor-open]').forEach(function (el) {
        el.addEventListener('click', function () { setOpen(true); });
    });

    modal.querySelectorAll('[data-rem-close]').forEach(function (el) {
        el.addEventListener('click', function () { setOpen(false); });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) setOpen(false);
    });

    // Paper width toggle
    var width58 = document.getElementById('rem-width-58-label');
    var width80 = document.getElementById('rem-width-80-label');
    var widthValue = document.getElementById('rem-paper-width-value');
    var previewPaper = document.getElementById('rem-preview-paper');

    function applyPaperWidth(width) {
        if (widthValue) widthValue.value = width;
        if (width58) width58.classList.toggle('is-active', width === '58');
        if (width80) width80.classList.toggle('is-active', width === '80');
        if (previewPaper) previewPaper.setAttribute('data-width', width);
    }

    if (width58) width58.addEventListener('click', function () { applyPaperWidth('58'); });
    if (width80) width80.addEventListener('click', function () { applyPaperWidth('80'); });

    // ── Live preview ──────────────────────────────────────────────
    var escHtml = function (str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    var SAMPLE = {
        businessName: @json($business->name ?? 'Your Business'),
        businessAddress: @json($business->address ?? ''),
        businessPhone: @json($business->phone ?? ''),
        currency: @json(filled($currency) ? ' '.$currency : ''),
        saleNumber: 'INV-0001',
        soldAt: 'Today, 2:30 PM',
        payment: 'Cash',
        items: [
            { name: 'Sample Product A', qty: '2', unit: '5.00', line: '10.00' },
            { name: 'Sample Product B', qty: '1', unit: '15.50', line: '15.50' }
        ],
        subtotal: '25.50',
        total: '25.50'
    };

    var previewContent = document.getElementById('rem-preview-content');
    var form = document.getElementById('rem-form');

    function fieldValue(name) {
        var checkbox = form.querySelector('input[type="checkbox"][name="' + name + '"]');
        if (checkbox) return checkbox.checked;
        var el = form.querySelector('[name="' + name + '"]');
        return el ? el.value : '';
    }

    function renderPreview() {
        if (!previewContent) return;

        var header = fieldValue('receipt_header');
        var footer = fieldValue('receipt_footer') || 'Thank you for your purchase!';
        var showName = fieldValue('show_business_name');
        var showAddress = fieldValue('show_business_address');

        var html = '';
        html += '<div class="rem-prev-center">';
        if (showName) html += '<div class="rem-prev-business">' + escHtml(SAMPLE.businessName) + '</div>';
        if (showAddress && SAMPLE.businessAddress) html += '<div class="rem-prev-meta">' + escHtml(SAMPLE.businessAddress) + '</div>';
        if (SAMPLE.businessPhone) html += '<div class="rem-prev-meta">' + escHtml(SAMPLE.businessPhone) + '</div>';
        if (header) html += '<div class="rem-prev-header-text">' + escHtml(header) + '</div>';
        html += '</div>';

        html += '<div class="rem-prev-divider"></div>';
        html += '<div class="rem-prev-row"><span>Receipt #</span><strong>' + escHtml(SAMPLE.saleNumber) + '</strong></div>';
        html += '<div class="rem-prev-row"><span>Date &amp; Time</span><strong>' + escHtml(SAMPLE.soldAt) + '</strong></div>';
        html += '<div class="rem-prev-row"><span>Payment</span><strong>' + escHtml(SAMPLE.payment) + '</strong></div>';
        html += '<div class="rem-prev-divider"></div>';

        html += '<table class="rem-prev-items"><thead><tr><th>Item</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Amount</th></tr></thead><tbody>';
        SAMPLE.items.forEach(function (item) {
            html += '<tr><td>' + escHtml(item.name) + '</td><td style="text-align:center;">' + escHtml(item.qty) + '</td><td style="text-align:right;">' + escHtml(item.line) + escHtml(SAMPLE.currency) + '</td></tr>';
        });
        html += '</tbody></table>';

        html += '<div class="rem-prev-total-final"><span>Total</span><span>' + escHtml(SAMPLE.total) + escHtml(SAMPLE.currency) + '</span></div>';
        html += '<div class="rem-prev-divider"></div>';
        html += '<div class="rem-prev-footer">' + escHtml(footer) + '</div>';

        previewContent.innerHTML = html;
    }

    if (form) {
        form.addEventListener('input', renderPreview);
        form.addEventListener('change', renderPreview);
        renderPreview();
    }
})();
</script>
@endonce
