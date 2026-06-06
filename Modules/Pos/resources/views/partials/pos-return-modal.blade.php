@php
    $accounts          = $accounts ?? collect();
    $currency          = $currency ?? '';
    $saleLookupUrl     = $saleLookupUrl ?? '';
    $modalReturnBaseUrl= $modalReturnBaseUrl ?? '';
    $modalReturnOpenUrl= $modalReturnOpenUrl ?? '';
    $returnsListUrl    = $returnsListUrl ?? '#';
@endphp

@once
<style>
/* ── Return modal overlay ───────────────────────────────────────── */
.pos-ret-modal{position:fixed;inset:0;z-index:400;display:flex;align-items:center;justify-content:center;padding:12px;box-sizing:border-box;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility .22s ease;}
.pos-ret-modal.is-open{opacity:1;visibility:visible;pointer-events:auto;}
.pos-ret-modal__backdrop{position:fixed;inset:0;background:rgba(10,16,28,.62);backdrop-filter:blur(4px);}
.pos-ret-modal__panel{position:relative;z-index:1;width:min(100%,720px);max-height:min(94vh,860px);display:flex;flex-direction:column;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 28px 64px rgba(0,0,0,.36);overflow:hidden;}
/* ── Header ─────────────────────────────────────────────────────── */
.pos-ret-modal__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px 12px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);flex-shrink:0;}
.pos-ret-modal__head h2{margin:0;font-size:16px;font-weight:800;color:var(--text);}
.pos-ret-modal__head-right{display:flex;align-items:center;gap:8px;}
.pos-ret-modal__view-all{font-size:11px;font-weight:700;padding:5px 10px;border-radius:7px;border:1px solid var(--border);background:transparent;color:var(--muted);text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.pos-ret-modal__view-all:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pos-ret-modal__close{width:32px;height:32px;border:1px solid var(--border);border-radius:8px;background:transparent;color:var(--text);cursor:pointer;font-size:17px;line-height:1;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.pos-ret-modal__close:hover{background:color-mix(in srgb,var(--border) 50%,transparent);}
/* ── Mode tabs ──────────────────────────────────────────────────── */
.pos-ret-tabs{display:flex;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);flex-shrink:0;}
.pos-ret-tab{flex:1;padding:10px 14px;font-size:12px;font-weight:700;border:none;border-bottom:2px solid transparent;background:transparent;color:var(--muted);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;transition:color .15s,border-color .15s;}
.pos-ret-tab:hover{color:var(--text);}
.pos-ret-tab.is-active{color:var(--text);border-bottom-color:var(--primary);}
/* ── Scrollable body ─────────────────────────────────────────────  */
.pos-ret-modal__body{flex:1;min-height:0;overflow-y:auto;padding:14px 16px;display:flex;flex-direction:column;gap:12px;}
/* ── Sale search ─────────────────────────────────────────────────  */
.pos-ret-search{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.pos-ret-search input{flex:1;min-width:180px;padding:9px 12px;font-size:13px;font-weight:600;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);box-sizing:border-box;}
.pos-ret-search input:focus{outline:none;border-color:var(--primary);}
.pos-ret-search-btn{padding:9px 14px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid color-mix(in srgb,var(--primary) 42%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--text);cursor:pointer;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;}
.pos-ret-search-btn:disabled{opacity:.5;cursor:not-allowed;}
/* ── Sale info strip ─────────────────────────────────────────────  */
.pos-ret-sale-strip{display:flex;flex-wrap:wrap;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:9px;background:color-mix(in srgb,var(--card) 94%,transparent);}
.pos-ret-sale-strip__num{font-size:14px;font-weight:800;color:var(--text);}
.pos-ret-sale-strip__chip{font-size:12px;color:var(--muted);}
.pos-ret-sale-strip__chip strong{color:var(--text);}
/* ── Alert banners ───────────────────────────────────────────────  */
.pos-ret-alert{padding:10px 12px;border-radius:9px;font-size:12px;font-weight:600;display:flex;align-items:flex-start;gap:8px;line-height:1.5;}
.pos-ret-alert--err{border:1px solid color-mix(in srgb,#ef4444 38%,var(--border));background:color-mix(in srgb,#ef4444 8%,transparent);color:color-mix(in srgb,#ef4444 80%,var(--text));}
.pos-ret-alert--warn{border:1px solid color-mix(in srgb,#f59e0b 40%,var(--border));background:color-mix(in srgb,#f59e0b 8%,transparent);color:color-mix(in srgb,#b45309 85%,var(--text));}
.pos-ret-alert--info{border:1px solid color-mix(in srgb,#3b82f6 35%,var(--border));background:color-mix(in srgb,#3b82f6 7%,transparent);color:color-mix(in srgb,#2563eb 80%,var(--text));}
/* ── Items table ─────────────────────────────────────────────────  */
.pos-ret-table-wrap{overflow-x:auto;}
.pos-ret-table{width:100%;border-collapse:collapse;font-size:13px;}
.pos-ret-table th{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);padding:0 8px 8px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
.pos-ret-table th.r{text-align:right;}
.pos-ret-table td{padding:8px;border-bottom:1px solid color-mix(in srgb,var(--border) 50%,transparent);vertical-align:middle;}
.pos-ret-table tr:last-child td{border-bottom:none;}
.pos-ret-qty{width:80px;padding:6px 8px;font-size:13px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);text-align:center;box-sizing:border-box;}
.pos-ret-qty:focus{outline:none;border-color:var(--primary);}
.pos-ret-qty:disabled{opacity:.3;cursor:not-allowed;}
.pos-ret-fully{display:inline-block;font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;border:1px solid var(--border);color:var(--muted);}
/* ── Open-mode product rows ──────────────────────────────────────  */
.pos-ret-open-badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;border:1px solid color-mix(in srgb,#a78bfa 45%,var(--border));background:color-mix(in srgb,#a78bfa 10%,transparent);color:color-mix(in srgb,#7c3aed 75%,var(--text));}
.pos-ret-add-row{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 38%,var(--border));background:transparent;color:var(--text);cursor:pointer;}
.pos-ret-add-row:hover{background:color-mix(in srgb,var(--primary) 10%,transparent);}
.pos-ret-prod-input{width:100%;min-width:140px;padding:6px 9px;font-size:12px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);box-sizing:border-box;}
.pos-ret-prod-input:focus{outline:none;border-color:var(--primary);}
.pos-ret-price-input{width:88px;padding:6px 8px;font-size:12px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);text-align:right;box-sizing:border-box;}
.pos-ret-price-input:focus{outline:none;border-color:var(--primary);}
.pos-ret-rm{width:26px;height:26px;border-radius:6px;border:1px solid color-mix(in srgb,#ef4444 35%,var(--border));background:transparent;color:color-mix(in srgb,#ef4444 70%,var(--text));cursor:pointer;font-size:12px;display:inline-flex;align-items:center;justify-content:center;padding:0;}
/* ── Shared fields ───────────────────────────────────────────────  */
.pos-ret-shared{display:grid;gap:10px;}
@media(min-width:520px){.pos-ret-shared{grid-template-columns:1fr 1fr;}}
.pos-ret-field{display:flex;flex-direction:column;gap:4px;}
.pos-ret-field--full{grid-column:1/-1;}
.pos-ret-field label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.pos-ret-field select,.pos-ret-field textarea{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-ret-field select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px;}
.pos-ret-field textarea{min-height:54px;resize:vertical;font-family:inherit;}
.pos-ret-credit-field{display:none;}
/* ── Total bar ───────────────────────────────────────────────────  */
.pos-ret-total{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border:1px solid var(--border);border-radius:9px;background:color-mix(in srgb,var(--card) 94%,transparent);}
.pos-ret-total__label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.pos-ret-total__value{font-size:20px;font-weight:800;color:var(--text);}
/* ── Footer ──────────────────────────────────────────────────────  */
.pos-ret-modal__foot{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;padding:12px 16px;border-top:1px solid var(--border);background:color-mix(in srgb,var(--card) 96%,transparent);flex-shrink:0;}
.pos-ret-btn{padding:9px 16px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;display:inline-flex;align-items:center;gap:6px;text-decoration:none;}
.pos-ret-btn:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pos-ret-btn--primary{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 16%,transparent);}
.pos-ret-btn--primary:hover{background:color-mix(in srgb,var(--primary) 26%,transparent);}
.pos-ret-btn:disabled{opacity:.45;cursor:not-allowed;}
html.pos-ret-modal-open,html.pos-ret-modal-open body{overflow:hidden;}
</style>
@endonce

<div id="posReturnModal" class="pos-ret-modal" role="dialog" aria-modal="true" aria-labelledby="posReturnModalTitle" aria-hidden="true">
    <div class="pos-ret-modal__backdrop" id="posReturnModalBackdrop"></div>
    <div class="pos-ret-modal__panel">

        {{-- Header --}}
        <div class="pos-ret-modal__head">
            <h2 id="posReturnModalTitle"><i class="fa fa-rotate-left" style="margin-right:7px;opacity:.7;"></i>Process return</h2>
            <div class="pos-ret-modal__head-right">
                <a href="{{ $returnsListUrl }}" class="pos-ret-modal__view-all">
                    <i class="fa fa-list"></i> All returns
                </a>
                <button type="button" class="pos-ret-modal__close" id="posReturnModalClose" aria-label="Close">&times;</button>
            </div>
        </div>

        {{-- Mode tabs --}}
        <div class="pos-ret-tabs" role="tablist">
            <button type="button" class="pos-ret-tab is-active" role="tab" aria-selected="true"
                data-ret-tab="ref" aria-controls="posRetPaneRef">
                <i class="fa fa-receipt"></i> With sale reference
            </button>
            <button type="button" class="pos-ret-tab" role="tab" aria-selected="false"
                data-ret-tab="open" aria-controls="posRetPaneOpen">
                <i class="fa fa-box-open"></i> Without sale reference
            </button>
        </div>

        {{-- Scrollable body --}}
        <div class="pos-ret-modal__body">

            {{-- ════════════ PANE A – With sale reference ════════════ --}}
            <div id="posRetPaneRef" role="tabpanel">
                <div class="pos-ret-search">
                    <input type="text" id="posRetSaleInput"
                        placeholder="Enter sale number — e.g. POS-0001"
                        autocomplete="off" spellcheck="false" aria-label="Sale number">
                    <button type="button" class="pos-ret-search-btn" id="posRetSaleSearchBtn">
                        <i class="fa fa-search"></i> Find sale
                    </button>
                </div>

                <div id="posRetSaleMsg" hidden></div>
                <div id="posRetSaleStrip" class="pos-ret-sale-strip" hidden></div>

                <form id="posRetRefForm" method="POST" action="">
                    @csrf
                    <div id="posRetItemsWrap" hidden>
                        <div class="pos-ret-table-wrap">
                            <table class="pos-ret-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="r">Sold</th>
                                        <th class="r">Returned</th>
                                        <th class="r">Returnable</th>
                                        <th class="r">Return qty</th>
                                        <th class="r">Unit price{{ filled($currency) ? ' ('.$currency.')' : '' }}</th>
                                        <th class="r">Line total</th>
                                    </tr>
                                </thead>
                                <tbody id="posRetItemsBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="posRetRefShared" hidden>
                        @include('pos::partials.pos-return-shared-fields', [
                            'formId'   => 'posRetRef',
                            'currency' => $currency,
                            'accounts' => $accounts,
                        ])
                    </div>
                </form>

                <div id="posRetRefHint" class="pos-ret-alert pos-ret-alert--info">
                    <i class="fa fa-circle-info" style="flex-shrink:0;margin-top:1px;"></i>
                    <span>Enter a sale number above to find items eligible for return.</span>
                </div>
            </div>

            {{-- ════════════ PANE B – Without sale reference ══════════ --}}
            <div id="posRetPaneOpen" role="tabpanel" hidden>
                <div class="pos-ret-open-badge" style="margin-bottom:12px;">
                    <i class="fa fa-box-open"></i> No sale reference — stock restored directly to product
                </div>

                <form id="posRetOpenForm" method="POST" action="{{ $modalReturnOpenUrl }}">
                    @csrf

                    <button type="button" class="pos-ret-add-row" id="posRetOpenAddBtn" style="margin-bottom:10px;">
                        <i class="fa fa-plus"></i> Add product
                    </button>

                    <div class="pos-ret-table-wrap">
                        <table class="pos-ret-table">
                            <thead>
                                <tr>
                                    <th style="min-width:160px;">Product</th>
                                    <th>SKU</th>
                                    <th class="r">Qty</th>
                                    <th class="r">Unit price{{ filled($currency) ? ' ('.$currency.')' : '' }}</th>
                                    <th class="r">Line total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="posRetOpenBody"></tbody>
                        </table>
                    </div>

                    <div id="posRetOpenEmpty" class="pos-ret-alert pos-ret-alert--info" style="margin-top:10px;display:none;">
                        <i class="fa fa-circle-info" style="flex-shrink:0;margin-top:1px;"></i>
                        <span>Click <strong>Add product</strong> to add items.</span>
                    </div>

                    @include('pos::partials.pos-return-shared-fields', [
                        'formId'   => 'posRetOpen',
                        'currency' => $currency,
                        'accounts' => $accounts,
                    ])
                </form>
            </div>

        </div>{{-- /.pos-ret-modal__body --}}

        {{-- Footer --}}
        <div class="pos-ret-modal__foot">
            <button type="button" class="pos-ret-btn" id="posReturnModalCancel">
                <i class="fa fa-arrow-left"></i> Cancel
            </button>
            <button type="submit" form="posRetRefForm" class="pos-ret-btn pos-ret-btn--primary"
                id="posRetSubmit" disabled>
                <i class="fa fa-rotate-left"></i>
                <span id="posRetSubmitLabel">Process return</span>
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var LOOKUP_URL    = @json($saleLookupUrl);
    var RET_BASE_URL  = @json($modalReturnBaseUrl);
    var CURRENCY      = @json($currency);

    /* Build product list from catalog already on the page */
    var POS_PRODS = [];
    if (typeof posProductCatalog !== 'undefined') {
        Object.values(posProductCatalog).forEach(function (p) {
            POS_PRODS.push({
                id:    p.id,
                name:  p.name,
                sku:   p.sku || '',
                price: parseFloat(p.unit_sell_price) || 0,
            });
        });
    }

    /* Element refs */
    var modal      = document.getElementById('posReturnModal');
    var backdrop   = document.getElementById('posReturnModalBackdrop');
    var openBtn    = document.getElementById('posReturnModalOpen');
    var closeBtn   = document.getElementById('posReturnModalClose');
    var cancelBtn  = document.getElementById('posReturnModalCancel');
    var tabs       = modal.querySelectorAll('[data-ret-tab]');
    var paneRef    = document.getElementById('posRetPaneRef');
    var paneOpen   = document.getElementById('posRetPaneOpen');
    var saleInput  = document.getElementById('posRetSaleInput');
    var searchBtn  = document.getElementById('posRetSaleSearchBtn');
    var saleMsg    = document.getElementById('posRetSaleMsg');
    var saleStrip  = document.getElementById('posRetSaleStrip');
    var itemsWrap  = document.getElementById('posRetItemsWrap');
    var itemsBody  = document.getElementById('posRetItemsBody');
    var refShared  = document.getElementById('posRetRefShared');
    var refHint    = document.getElementById('posRetRefHint');
    var refForm    = document.getElementById('posRetRefForm');
    var openForm   = document.getElementById('posRetOpenForm');
    var openAddBtn = document.getElementById('posRetOpenAddBtn');
    var openBody   = document.getElementById('posRetOpenBody');
    var openEmpty  = document.getElementById('posRetOpenEmpty');
    var submit     = document.getElementById('posRetSubmit');
    var submitLbl  = document.getElementById('posRetSubmitLabel');

    var currentMode = 'ref';
    var openRowIdx  = 0;

    /* ── Open / close ──────────────────────────────────────────── */
    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('pos-ret-modal-open');
        if (saleInput) saleInput.focus();
    }
    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('pos-ret-modal-open');
    }

    if (openBtn)   openBtn.addEventListener('click', openModal);
    if (closeBtn)  closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop)  backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    /* ── Tab switching ─────────────────────────────────────────── */
    function switchMode(mode) {
        currentMode = mode;
        tabs.forEach(function (btn) {
            var active = btn.getAttribute('data-ret-tab') === mode;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        paneRef.hidden  = mode !== 'ref';
        paneOpen.hidden = mode !== 'open';
        if (submit) {
            submit.setAttribute('form', mode === 'ref' ? 'posRetRefForm' : 'posRetOpenForm');
            submit.disabled = mode === 'ref' ? (!itemsWrap || itemsWrap.hidden) : false;
            submitLbl.textContent = 'Process return';
        }
        if (mode === 'open' && openBody && openBody.querySelectorAll('tr').length === 0) {
            addOpenRow(null);
        }
    }
    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () { switchMode(btn.getAttribute('data-ret-tab')); });
    });

    /* ── MODE A – Sale lookup ──────────────────────────────────── */
    function resetRefPane() {
        saleMsg.hidden = true; saleMsg.innerHTML = '';
        saleStrip.hidden = true; saleStrip.innerHTML = '';
        itemsWrap.hidden = true; itemsBody.innerHTML = '';
        refShared.hidden = true;
        refHint.hidden = false;
        refForm.action = '';
        if (submit) submit.disabled = true;
    }

    function showMsg(html, type) {
        saleMsg.hidden = false;
        saleMsg.className = 'pos-ret-alert pos-ret-alert--' + type;
        saleMsg.innerHTML = '<i class="fa fa-circle-info" style="flex-shrink:0;margin-top:1px;"></i><span>' + html + '</span>';
    }

    function fmtNum(n) { return parseFloat(n).toFixed(3).replace(/\.?0+$/, ''); }

    function buildItemsTable(items) {
        itemsBody.innerHTML = '';
        items.forEach(function (item, idx) {
            var can = item.returnable > 0;
            var tr = document.createElement('tr');
            tr.innerHTML = [
                '<td>',
                    '<input type="hidden" name="items[' + idx + '][sale_item_id]" value="' + item.id + '">',
                    '<strong style="color:var(--text);">' + escH(item.product_name) + '</strong>',
                    item.sku ? '<div style="font-size:11px;color:var(--muted);margin-top:2px;">' + escH(item.sku) + '</div>' : '',
                '</td>',
                '<td class="muted" style="text-align:right;">' + fmtNum(item.quantity) + '</td>',
                '<td class="muted" style="text-align:right;">' + (item.returned > 0 ? fmtNum(item.returned) : '—') + '</td>',
                '<td style="text-align:right;">',
                    can ? '<strong style="color:var(--text);">' + fmtNum(item.returnable) + '</strong>'
                        : '<span class="pos-ret-fully">Done</span>',
                '</td>',
                '<td style="text-align:right;">',
                    '<input type="number" name="items[' + idx + '][quantity]"',
                        ' class="pos-ret-qty" min="0" max="' + item.returnable + '" step="0.001" value="0"',
                        (can ? '' : ' disabled'),
                        ' data-max="' + item.returnable + '"',
                        ' data-unit-price="' + item.unit_sell_price + '"',
                        ' data-ref-row="' + idx + '">',
                '</td>',
                '<td class="muted" style="text-align:right;">' + item.unit_sell_price.toFixed(2) + '</td>',
                '<td style="text-align:right;" id="posRetRefLine' + idx + '">',
                    '<strong style="color:var(--text);">0.00</strong>',
                '</td>',
            ].join('');
            itemsBody.appendChild(tr);
        });
        itemsBody.addEventListener('input', function (e) {
            if (e.target.classList.contains('pos-ret-qty')) recalcRef();
        });
    }

    function recalcRef() {
        var total = 0;
        itemsBody.querySelectorAll('.pos-ret-qty').forEach(function (inp) {
            var idx   = inp.getAttribute('data-ref-row');
            var max   = parseFloat(inp.getAttribute('data-max')) || 0;
            var price = parseFloat(inp.getAttribute('data-unit-price')) || 0;
            var qty   = Math.min(Math.max(0, parseFloat(inp.value) || 0), max);
            var line  = qty * price;
            total    += line;
            var el = document.getElementById('posRetRefLine' + idx);
            if (el) el.innerHTML = '<strong style="color:var(--text);">' + line.toFixed(2) + '</strong>';
        });
        var el = document.getElementById('posRetRefTotal');
        if (el) el.textContent = total.toFixed(2);
    }

    function doLookup() {
        var num = saleInput ? saleInput.value.trim() : '';
        if (!num) return;
        resetRefPane();
        refHint.hidden = true;
        searchBtn.disabled = true;
        searchBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Searching…';

        fetch(LOOKUP_URL + '?sale=' + encodeURIComponent(num), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            searchBtn.disabled = false;
            searchBtn.innerHTML = '<i class="fa fa-search"></i> Find sale';
            if (!data.found) { showMsg('Sale <strong>' + escH(num) + '</strong> not found.', 'err'); return; }
            if (data.is_void) {
                showMsg('Sale <strong>' + escH(data.sale.sale_number) + '</strong> is voided — returns cannot be created.', 'warn');
                saleStrip.hidden = false; saleStrip.innerHTML = buildStrip(data.sale); return;
            }
            if (data.all_returned) {
                showMsg('All items in this sale have already been fully returned.', 'info');
                saleStrip.hidden = false; saleStrip.innerHTML = buildStrip(data.sale); return;
            }
            saleStrip.hidden = false; saleStrip.innerHTML = buildStrip(data.sale);
            buildItemsTable(data.items);
            itemsWrap.hidden = false;
            refShared.hidden = false;
            refHint.hidden   = true;
            saleMsg.hidden   = true;
            refForm.action   = RET_BASE_URL + '/' + data.sale.id;
            if (submit) submit.disabled = false;
        })
        .catch(function () {
            searchBtn.disabled = false;
            searchBtn.innerHTML = '<i class="fa fa-search"></i> Find sale';
            showMsg('Network error — please try again.', 'err');
        });
    }

    function buildStrip(sale) {
        return [
            '<span class="pos-ret-sale-strip__num"><i class="fa fa-receipt" style="opacity:.6;margin-right:5px;"></i>' + escH(sale.sale_number) + '</span>',
            '<span class="pos-ret-sale-strip__chip">Date: <strong>' + escH(sale.sold_at) + '</strong></span>',
            '<span class="pos-ret-sale-strip__chip">Payment: <strong>' + escH(sale.payment_method_label) + '</strong></span>',
            '<span class="pos-ret-sale-strip__chip">Total: <strong>' + parseFloat(sale.total).toFixed(2) + (CURRENCY ? ' ' + escH(CURRENCY) : '') + '</strong></span>',
        ].join('');
    }

    if (searchBtn) searchBtn.addEventListener('click', doLookup);
    if (saleInput) saleInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); doLookup(); }
    });

    if (refForm) refForm.addEventListener('submit', function (e) {
        var anyQty = false;
        itemsBody.querySelectorAll('.pos-ret-qty').forEach(function (i) { if (parseFloat(i.value) > 0) anyQty = true; });
        if (!anyQty) { e.preventDefault(); showMsg('Enter a return quantity for at least one item.', 'warn'); return; }
        if (submit) { submit.disabled = true; submitLbl.textContent = 'Processing…'; }
    });

    /* ── MODE B – Open return ──────────────────────────────────── */
    var dl = document.createElement('datalist');
    dl.id  = 'posRetOpenProdList';
    POS_PRODS.forEach(function (p) {
        var o = document.createElement('option');
        o.value = p.name + (p.sku ? ' (' + p.sku + ')' : '');
        dl.appendChild(o);
    });
    document.body.appendChild(dl);

    function findProd(val) {
        val = val.trim().toLowerCase();
        return POS_PRODS.find(function (p) {
            var lbl = p.name + (p.sku ? ' (' + p.sku + ')' : '');
            return lbl.toLowerCase() === val || p.name.toLowerCase() === val
                || (p.sku && p.sku.toLowerCase() === val);
        }) || null;
    }

    function updateOpenEmpty() {
        if (!openBody || !openEmpty) return;
        openEmpty.style.display = openBody.querySelectorAll('tr').length === 0 ? 'flex' : 'none';
    }

    function addOpenRow(product) {
        var idx = openRowIdx++;
        var tr  = document.createElement('tr');
        tr.setAttribute('data-open-row', idx);
        tr.innerHTML = [
            '<td>',
                '<input type="text" list="posRetOpenProdList" class="pos-ret-prod-input"',
                    ' placeholder="Search name or SKU…" autocomplete="off" data-row="' + idx + '"',
                    ' value="' + (product ? escH(product.name + (product.sku ? ' (' + product.sku + ')' : '')) : '') + '">',
                '<input type="hidden" name="items[' + idx + '][product_id]" class="pos-ret-pid" value="' + (product ? product.id : '') + '">',
            '</td>',
            '<td class="muted" id="posRetOpenSku' + idx + '" style="font-size:11px;">' + (product ? escH(product.sku || '—') : '—') + '</td>',
            '<td style="text-align:right;">',
                '<input type="number" name="items[' + idx + '][quantity]" class="pos-ret-qty pos-ret-open-qty"',
                    ' min="0.001" step="0.001" value="1" data-row="' + idx + '" data-unit-price="' + (product ? product.price : 0) + '">',
            '</td>',
            '<td style="text-align:right;">',
                '<input type="number" name="items[' + idx + '][unit_price]" class="pos-ret-price-input pos-ret-uprice"',
                    ' min="0" step="0.01" value="' + (product ? product.price.toFixed(2) : '0.00') + '" data-row="' + idx + '">',
            '</td>',
            '<td style="text-align:right;" id="posRetOpenLine' + idx + '">',
                '<strong style="color:var(--text);">' + (product ? product.price.toFixed(2) : '0.00') + '</strong>',
            '</td>',
            '<td style="text-align:center;">',
                '<button type="button" class="pos-ret-rm" title="Remove"><i class="fa fa-xmark"></i></button>',
            '</td>',
        ].join('');

        openBody.appendChild(tr);

        var sinp  = tr.querySelector('.pos-ret-prod-input');
        var pidInp= tr.querySelector('.pos-ret-pid');
        var skuEl = document.getElementById('posRetOpenSku' + idx);
        var pinp  = tr.querySelector('.pos-ret-uprice');
        var qinp  = tr.querySelector('.pos-ret-open-qty');

        sinp.addEventListener('input', function () {
            var f = findProd(this.value);
            if (f) {
                pidInp.value = f.id;
                pinp.value   = f.price.toFixed(2);
                pinp.setAttribute('data-unit-price', f.price);
                qinp.setAttribute('data-unit-price', f.price);
                if (skuEl) skuEl.textContent = f.sku || '—';
            } else {
                pidInp.value = '';
                if (skuEl) skuEl.textContent = '—';
            }
            recalcOpen();
        });

        tr.querySelector('.pos-ret-rm').addEventListener('click', function () {
            tr.remove(); recalcOpen(); updateOpenEmpty();
        });
        updateOpenEmpty();
    }

    function recalcOpen() {
        var total = 0;
        openBody.querySelectorAll('tr[data-open-row]').forEach(function (tr) {
            var idx  = tr.getAttribute('data-open-row');
            var q    = tr.querySelector('.pos-ret-open-qty');
            var p    = tr.querySelector('.pos-ret-uprice');
            var el   = document.getElementById('posRetOpenLine' + idx);
            var qty  = Math.max(0, parseFloat(q ? q.value : 0) || 0);
            var price= Math.max(0, parseFloat(p ? p.value : 0) || 0);
            var line = qty * price;
            total   += line;
            if (el) el.innerHTML = '<strong style="color:var(--text);">' + line.toFixed(2) + '</strong>';
        });
        var el = document.getElementById('posRetOpenTotal');
        if (el) el.textContent = total.toFixed(2);
    }

    if (openAddBtn) openAddBtn.addEventListener('click', function () { addOpenRow(null); });

    if (openBody) openBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('pos-ret-open-qty') || e.target.classList.contains('pos-ret-uprice')) recalcOpen();
    });

    if (openForm) openForm.addEventListener('submit', function (e) {
        var ok = openBody.querySelectorAll('tr').length > 0;
        if (ok) openBody.querySelectorAll('tr[data-open-row]').forEach(function (tr) {
            var pid = tr.querySelector('.pos-ret-pid');
            var qty = tr.querySelector('.pos-ret-open-qty');
            if (!pid || !pid.value || !qty || parseFloat(qty.value) <= 0) ok = false;
        });
        if (!ok) {
            e.preventDefault();
            if (openEmpty) {
                openEmpty.className = 'pos-ret-alert pos-ret-alert--warn';
                openEmpty.style.display = 'flex';
                openEmpty.innerHTML = '<i class="fa fa-circle-exclamation" style="flex-shrink:0;margin-top:1px;"></i><span>Select a product and enter a quantity for each row.</span>';
            }
            return;
        }
        if (submit) { submit.disabled = true; submitLbl.textContent = 'Processing…'; }
    });

    /* ── Credit field toggle ───────────────────────────────────── */
    function syncCredit(selId, fieldId) {
        var sel   = document.getElementById(selId);
        var field = document.getElementById(fieldId);
        if (!sel || !field) return;
        function upd() { field.style.display = sel.value === 'credit' ? 'flex' : 'none'; }
        sel.addEventListener('change', upd); upd();
    }
    syncCredit('posRetRefRefundSel', 'posRetRefCreditField');
    syncCredit('posRetOpenRefundSel', 'posRetOpenCreditField');

    function escH(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>
