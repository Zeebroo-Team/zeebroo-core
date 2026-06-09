@extends('theme::layouts.app', ['title' => 'Product discounts', 'heading' => 'Product discounts'])

@section('content')
@include('product::partials.catalog-hub-styles')

<style>
/* ─── Form section anatomy ─────────────────────────────────────────────── */
.pd-section{
    border:1px solid var(--border);
    border-radius:14px;
    background:color-mix(in srgb,var(--card) 98%,transparent);
    overflow:hidden;
}
.pd-section + .pd-section{ margin-top:16px; }
.pd-section__head{
    display:flex;align-items:flex-start;gap:12px;
    padding:14px 16px;
    border-bottom:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 93%,var(--border) 7%);
}
.pd-section__icon{
    width:34px;height:34px;flex-shrink:0;
    display:grid;place-items:center;
    border-radius:9px;
    border:1px solid color-mix(in srgb,var(--primary) 28%,var(--border));
    background:color-mix(in srgb,var(--primary) 10%,transparent);
    color:var(--primary);font-size:14px;
}
.pd-section__title{font-size:13px;font-weight:800;color:var(--text);line-height:1.2;}
.pd-section__sub{font-size:12px;color:var(--muted);margin-top:2px;line-height:1.35;}
.pd-section__body{padding:18px 16px;display:flex;flex-direction:column;gap:14px;}

/* ─── Two-column layout ────────────────────────────────────────────────── */
.pd-two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;}
@media(max-width:600px){.pd-two-col{grid-template-columns:1fr;}}

/* ─── Field helpers ────────────────────────────────────────────────────── */
.pd-field-label{
    display:block;font-size:10px;font-weight:700;text-transform:uppercase;
    letter-spacing:.05em;color:var(--muted);margin-bottom:6px;
}
.pd-req{color:color-mix(in srgb,#f87171 80%,var(--text));font-weight:700;}
.pd-optional{font-size:10px;font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0;margin-left:4px;}
.pd-err{color:#f87171;font-size:12px;margin:4px 0 0;}

/* ─── Product info card ────────────────────────────────────────────────── */
.pd-info-card{
    border:1px solid color-mix(in srgb,var(--primary) 22%,var(--border));
    border-radius:11px;
    background:color-mix(in srgb,var(--primary) 5%,var(--card));
    padding:12px 14px;
}
.pd-info-card__grid{
    display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px 16px;
}
.pd-info-stat{display:flex;flex-direction:column;gap:3px;}
.pd-info-stat__lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.pd-info-stat__val{font-size:14px;font-weight:700;color:var(--text);line-height:1.2;}
.pd-info-stat--highlight .pd-info-stat__val{color:var(--primary);font-size:15px;font-weight:800;}

/* ─── Apply-to (selling unit selector) ─────────────────────────────────── */
.pd-su-list{
    border:1px solid var(--border);border-radius:10px;
    background:color-mix(in srgb,var(--card) 97%,transparent);
    overflow:hidden;
}
.pd-su-row{
    display:flex;align-items:center;gap:10px;
    padding:9px 12px;
    border-bottom:1px solid color-mix(in srgb,var(--border) 55%,transparent);
    cursor:pointer;transition:background .12s ease;
}
.pd-su-row:last-child{border-bottom:none;}
.pd-su-row:hover{background:color-mix(in srgb,var(--primary) 5%,transparent);}
.pd-su-row--selected{background:color-mix(in srgb,var(--primary) 8%,transparent);}
.pd-su-radio{accent-color:var(--primary);width:15px;height:15px;cursor:pointer;flex-shrink:0;}
.pd-su-row__name{font-size:13px;font-weight:700;color:var(--text);}
.pd-su-row__price{font-size:12px;color:var(--muted);margin-top:1px;}

/* ─── Discount type card buttons ───────────────────────────────────────── */
.pd-type-cards{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.pd-type-card{
    display:flex;align-items:center;gap:10px;
    padding:10px 12px;border-radius:10px;
    border:2px solid var(--border);
    background:color-mix(in srgb,var(--card) 97%,transparent);
    cursor:pointer;transition:border-color .15s,background .15s;
    position:relative;
}
.pd-type-card:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pd-type-card input[type="radio"]{position:absolute;opacity:0;width:0;height:0;}
.pd-type-card--selected{
    border-color:var(--primary) !important;
    background:color-mix(in srgb,var(--primary) 8%,transparent);
}
.pd-type-card__icon{
    width:32px;height:32px;flex-shrink:0;
    display:grid;place-items:center;
    border-radius:8px;font-size:13px;
    background:color-mix(in srgb,var(--card) 90%,var(--border) 10%);
    color:var(--muted);border:1px solid var(--border);
    transition:background .15s,color .15s;
}
.pd-type-card--selected .pd-type-card__icon{
    background:color-mix(in srgb,var(--primary) 15%,transparent);
    color:var(--primary);border-color:color-mix(in srgb,var(--primary) 35%,var(--border));
}
.pd-type-card__body{flex:1;min-width:0;}
.pd-type-card__label{display:block;font-size:12px;font-weight:700;color:var(--text);line-height:1.2;}
.pd-type-card__hint{display:block;font-size:10px;color:var(--muted);margin-top:1px;}
.pd-type-card__check{font-size:14px;color:var(--primary);opacity:0;transition:opacity .15s;}
.pd-type-card--selected .pd-type-card__check{opacity:1;}

/* ─── Value input with suffix ──────────────────────────────────────────── */
.pd-value-wrap{position:relative;}
.pd-value-inp{padding-right:36px!important;}
.pd-value-suffix{
    position:absolute;right:11px;top:50%;transform:translateY(-50%);
    font-size:13px;font-weight:700;color:var(--muted);pointer-events:none;
}

/* ─── Live price preview ────────────────────────────────────────────────── */
.pd-preview{margin-top:2px;}
.pd-preview__inner{
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    padding:12px 16px;border-radius:11px;
    border:1px solid color-mix(in srgb,#22c55e 38%,var(--border));
    background:color-mix(in srgb,#22c55e 6%,var(--card));
    flex-wrap:wrap;
}
.pd-preview__left{display:flex;flex-direction:column;gap:4px;}
.pd-preview__lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.pd-preview__prices{display:flex;align-items:center;gap:8px;}
.pd-preview__orig{font-size:12px;font-weight:600;color:var(--muted);text-decoration:line-through;}
.pd-preview__arrow{font-size:10px;color:var(--muted);}
.pd-preview__final{font-size:18px;font-weight:800;color:var(--text);}
.pd-preview__right{text-align:right;}
.pd-preview__saving{
    display:inline-flex;align-items:center;gap:4px;
    padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;
    background:color-mix(in srgb,#22c55e 12%,transparent);
    border:1px solid color-mix(in srgb,#22c55e 35%,var(--border));
    color:#15803d;
}
html[data-theme="night"] .pd-preview__saving,
html[data-theme="night_blue"] .pd-preview__saving,
html[data-theme="ocean"] .pd-preview__saving{color:#4ade80;}

/* ─── Status badges ─────────────────────────────────────────────────────── */
.pd-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid var(--border);}
.pd-badge--active{border-color:color-mix(in srgb,#22c55e 42%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);color:#15803d;}
.pd-badge--inactive{color:var(--muted);}
.pd-badge--expired{border-color:color-mix(in srgb,#f87171 32%,var(--border));background:color-mix(in srgb,#f87171 7%,transparent);color:#dc2626;}
html[data-theme="night"] .pd-badge--active,
html[data-theme="night_blue"] .pd-badge--active,
html[data-theme="ocean"] .pd-badge--active{color:#4ade80;}
html[data-theme="night"] .pd-badge--expired,
html[data-theme="night_blue"] .pd-badge--expired,
html[data-theme="ocean"] .pd-badge--expired{color:#f87171;}

/* ─── Discount pill (used in table) ────────────────────────────────────── */
.pd-pill{
    display:inline-flex;align-items:center;gap:3px;
    padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;
    background:color-mix(in srgb,#f59e0b 11%,transparent);
    border:1px solid color-mix(in srgb,#f59e0b 32%,var(--border));
    color:#b45309;
}
html[data-theme="night"] .pd-pill,
html[data-theme="night_blue"] .pd-pill,
html[data-theme="ocean"] .pd-pill{color:#fbbf24;}

/* ─── Table helpers ─────────────────────────────────────────────────────── */
.pd-strike{text-decoration:line-through;color:var(--muted);font-size:11px;}
</style>

{{-- ──────────────────────────────────────────────────────────────────────── --}}
<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('product::partials.product-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('discount'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('discount') }}</div>
    @endif

    <div style="margin-bottom:18px;">
        <p class="muted" style="margin:0;font-size:13px;line-height:1.5;">
            Product discounts for <strong style="color:var(--text);">{{ $business->name }}</strong> —
            apply a flat or percentage reduction to any product base price or selling unit.
        </p>
    </div>

    <div class="pcat-toolbar" style="margin-bottom:16px;">
        <span class="muted" style="font-size:13px;">
            @if($discounts->isEmpty())
                Create your <strong style="color:var(--text);">first discount</strong> below.
            @else
                {{ $discounts->count() }} {{ $discounts->count() === 1 ? 'discount' : 'discounts' }}.
            @endif
        </span>
        @if($discounts->isNotEmpty())
            <button type="button" id="pd-modal-open" class="linkbtn"
                    style="padding:8px 18px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
                <i class="fa fa-plus"></i> New discount
            </button>
        @endif
    </div>

    @if($discounts->isEmpty())

        {{-- ══════════════════════════════════════════════════════
             EMPTY STATE — inline create form
        ══════════════════════════════════════════════════════ --}}
        @if($errors->any())
            <div class="pcat-banner pcat-banner--err" style="margin-bottom:16px;">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('product.discounts.store') }}">
            @csrf
            @include('product::discounts.partials.form-fields', ['idPfx' => 'pd-inline'])
            <div style="display:flex;justify-content:flex-end;margin-top:20px;">
                <button type="submit" class="linkbtn"
                        style="padding:10px 24px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
                    <i class="fa fa-check"></i> Save discount
                </button>
            </div>
        </form>

    @else

        {{-- ══════════════════════════════════════════════════════
             DISCOUNT TABLE
        ══════════════════════════════════════════════════════ --}}
        <div class="pcat-table-wrap">
            <table class="pcat-table" style="min-width:700px;">
                <thead>
                    <tr>
                        <th>Discount</th>
                        <th>Product / unit</th>
                        <th>Original price</th>
                        <th>Cost price</th>
                        <th>Discount</th>
                        <th>Final price</th>
                        <th>Validity</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($discounts as $row)
                        @php
                            $originalPrice  = $row->originalPrice();
                            $discountAmount = $row->discountAmount();
                            $finalPrice     = $row->finalPrice();
                            $isActive       = $row->isCurrentlyActive();
                            $expired        = $row->ends_at && $row->ends_at->lt(now()->startOfDay());
                            $pData = $productsMap->get($row->product_id);
                            $cost  = $pData['cost'] ?? null;
                        @endphp
                        <tr>
                            {{-- Name --}}
                            <td>
                                <strong style="color:var(--text);font-size:13px;">{{ $row->name }}</strong>
                            </td>

                            {{-- Product / unit --}}
                            <td>
                                <div style="font-size:13px;font-weight:700;color:var(--text);">
                                    {{ $row->product?->name ?? '—' }}
                                </div>
                                @if($row->product?->sku)
                                    <div style="font-size:11px;color:var(--muted);font-family:monospace;">
                                        {{ $row->product->sku }}
                                    </div>
                                @endif
                                <div style="margin-top:3px;">
                                    @if($row->sellingUnit)
                                        <span class="pd-pill" style="font-size:10px;">
                                            <i class="fa fa-cubes" style="font-size:8px;"></i>
                                            {{ $row->sellingUnit->label }}
                                        </span>
                                    @else
                                        <span style="font-size:10px;color:var(--muted);">Base price</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Original price --}}
                            <td>
                                <span style="font-size:13px;font-weight:700;color:var(--text);">
                                    {{ number_format($originalPrice, 2) }}
                                </span>
                            </td>

                            {{-- Cost --}}
                            <td style="color:var(--muted);font-size:13px;">
                                {{ $cost !== null ? number_format($cost, 2) : '—' }}
                            </td>

                            {{-- Discount --}}
                            <td>
                                <span class="pd-pill">
                                    @if($row->discount_type === 'percentage')
                                        <i class="fa fa-percent" style="font-size:8px;"></i>
                                        {{ rtrim(rtrim(number_format((float)$row->discount_value,2),'0'),'.') }}%
                                    @else
                                        <i class="fa fa-minus" style="font-size:8px;"></i>
                                        {{ number_format((float)$row->discount_value,2) }}
                                    @endif
                                </span>
                                <div style="font-size:11px;color:var(--muted);margin-top:3px;">
                                    − {{ number_format($discountAmount,2) }}
                                </div>
                            </td>

                            {{-- Final price --}}
                            <td>
                                <div style="font-size:15px;font-weight:800;color:var(--text);">
                                    {{ number_format($finalPrice,2) }}
                                </div>
                                <div class="pd-strike">{{ number_format($originalPrice,2) }}</div>
                            </td>

                            {{-- Validity --}}
                            <td style="font-size:11px;color:var(--muted);white-space:nowrap;min-width:90px;">
                                @if($row->starts_at || $row->ends_at)
                                    @if($row->starts_at)
                                        <div><i class="fa fa-play" style="font-size:9px;opacity:.6;"></i> {{ $row->starts_at->format('d M Y') }}</div>
                                    @endif
                                    @if($row->ends_at)
                                        <div style="margin-top:2px;"><i class="fa fa-stop" style="font-size:9px;opacity:.6;"></i> {{ $row->ends_at->format('d M Y') }}</div>
                                    @endif
                                @else
                                    <span>No limit</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td>
                                @if($expired)
                                    <span class="pd-badge pd-badge--expired">
                                        <i class="fa fa-clock"></i> Expired
                                    </span>
                                @elseif($isActive)
                                    <span class="pd-badge pd-badge--active">
                                        <i class="fa fa-circle-check"></i> Active
                                    </span>
                                @else
                                    <span class="pd-badge pd-badge--inactive">Inactive</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td style="text-align:right;">
                                <form method="post" action="{{ route('product.discounts.destroy', $row) }}"
                                      style="display:inline;"
                                      onsubmit="return confirm('Delete « {{ addslashes($row->name) }} »?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="pcat-btn-del">
                                        <i class="fa fa-trash-can"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- ══════════════════════════════════════════════════════
             CREATE MODAL
        ══════════════════════════════════════════════════════ --}}
        <div id="pd-modal"
             class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}"
             role="dialog" aria-modal="true" aria-labelledby="pd-modal-title"
             aria-hidden="{{ $modalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-pd-close tabindex="-1"></div>
            <div class="pcat-modal__panel" style="max-width:620px;">
                <div class="pcat-modal__head">
                    <h2 id="pd-modal-title" style="font-size:15px;">
                        <i class="fa fa-percent" style="margin-right:6px;opacity:.7;"></i>
                        New discount
                    </h2>
                    <button type="button" class="pcat-modal__close" data-pd-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body" style="padding:20px 16px 24px;">
                    @if($errors->any())
                        <div class="pcat-banner pcat-banner--err" style="margin-bottom:16px;">{{ $errors->first() }}</div>
                    @endif
                    <form method="post" action="{{ route('product.discounts.store') }}">
                        @csrf
                        @include('product::discounts.partials.form-fields', ['idPfx' => 'pd-modal'])
                        <div style="display:flex;justify-content:flex-end;margin-top:20px;">
                            <button type="submit" class="linkbtn"
                                    style="padding:10px 24px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
                                <i class="fa fa-check"></i> Save discount
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    @endif
</div>

{{-- ──────────────────────────────────────────────────────────────────────── --}}
<script>
(function () {
    /* ── Modal open / close ─────────────────────────────────────── */
    var modal  = document.getElementById('pd-modal');
    var openBtn = document.getElementById('pd-modal-open');

    function lockScroll(on) {
        document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on));
    }
    function openModal() {
        if (!modal) return;
        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lockScroll(true);
        var first = modal.querySelector('select,input:not([type=hidden]):not([type=radio])');
        window.requestAnimationFrame(function () { if (first) first.focus(); });
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lockScroll(false);
        if (openBtn) openBtn.focus();
    }
    openBtn && openBtn.addEventListener('click', openModal);
    modal && modal.querySelectorAll('[data-pd-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('pcat-modal--open')) closeModal();
    });
    if (modal && modal.classList.contains('pcat-modal--open')) lockScroll(true);

    /* ── Product data (passed from PHP) ─────────────────────────── */
    var PRODUCTS = @json($productsMap);

    /* ── Wire every .pd-form-root on the page ───────────────────── */
    document.querySelectorAll('.pd-form-root').forEach(function (form) {
        var productSel    = form.querySelector('.pd-product-select');
        var infoCard      = form.querySelector('.pd-info-card');
        var suSection     = form.querySelector('.pd-su-section');
        var suList        = form.querySelector('.pd-su-list');
        var suHidden      = form.querySelector('.pd-su-hidden');
        var typeRadios    = form.querySelectorAll('.pd-type-radio');
        var typeCards     = form.querySelectorAll('.pd-type-card');
        var valueInput    = form.querySelector('.pd-value-inp');
        var valueSuffix   = form.querySelector('.pd-value-suffix');
        var valueLabel    = form.querySelector('.pd-value-label');
        var preview       = form.querySelector('.pd-preview');
        var previewOld    = form.querySelector('.pd-preview-old');
        var previewNew    = form.querySelector('.pd-preview-new');
        var previewSave   = form.querySelector('.pd-preview-save');

        var currentPrice = 0;

        function fmt(n) {
            return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        /* Sync card selected state with radio */
        function syncTypeCards() {
            typeRadios.forEach(function (radio, i) {
                var card = typeCards[i];
                if (!card) return;
                var sel = radio.checked;
                card.classList.toggle('pd-type-card--selected', sel);
            });
            var pct = form.querySelector('.pd-type-pct');
            var suffix = pct && pct.checked ? '%' : '';
            if (valueSuffix) valueSuffix.textContent = suffix;
            if (valueLabel)  valueLabel.textContent  = suffix;
            updatePreview();
        }

        /* Live price preview */
        function updatePreview() {
            if (!preview) return;
            var val  = parseFloat(valueInput ? valueInput.value : 0) || 0;
            var pct  = form.querySelector('.pd-type-pct');
            var type = pct && pct.checked ? 'percentage' : 'flat';
            var disc = type === 'percentage'
                ? currentPrice * val / 100
                : Math.min(val, currentPrice);
            var fin  = Math.max(0, currentPrice - disc);
            if (currentPrice <= 0 || val <= 0) { preview.style.display = 'none'; return; }
            preview.style.display = 'block';
            if (previewOld)  previewOld.textContent  = fmt(currentPrice);
            if (previewNew)  previewNew.textContent  = fmt(fin);
            if (previewSave) previewSave.textContent = '− ' + fmt(disc) + ' ('
                + (currentPrice > 0 ? (disc / currentPrice * 100).toFixed(1) : 0) + '%)';
        }

        /* Render selling-unit radio list */
        function renderSuList(product) {
            if (!suList) return;
            suList.innerHTML = '';

            var rows = [{ id: '', label: 'Base price', price: product.unit_price }];
            (product.selling_units || []).forEach(function (su) {
                rows.push({ id: su.id, label: su.label, price: su.selling_price });
            });

            rows.forEach(function (r, idx) {
                var row = document.createElement('label');
                row.className = 'pd-su-row' + (idx === 0 ? ' pd-su-row--selected' : '');
                row.innerHTML =
                    '<input type="radio" class="pd-su-radio" name="__su_radio_' + Math.random().toString(36).slice(2) + '"'
                    + (idx === 0 ? ' checked' : '') + '>'
                    + '<div style="flex:1;">'
                    + '<div class="pd-su-row__name">' + r.label + '</div>'
                    + '<div class="pd-su-row__price">' + fmt(r.price) + '</div>'
                    + '</div>';
                var radio = row.querySelector('input');
                radio.addEventListener('change', function () {
                    suList.querySelectorAll('.pd-su-row').forEach(function (el) {
                        el.classList.remove('pd-su-row--selected');
                    });
                    row.classList.add('pd-su-row--selected');
                    if (suHidden) suHidden.value = r.id;
                    currentPrice = r.price;
                    updatePreview();
                });
                suList.appendChild(row);
            });

            if (suHidden) suHidden.value = '';
            currentPrice = product.unit_price;
        }

        /* When product dropdown changes */
        function onProductChange() {
            var id = productSel ? productSel.value : '';
            if (!id || !PRODUCTS[id]) {
                if (infoCard)  infoCard.style.display  = 'none';
                if (suSection) suSection.style.display = 'none';
                if (preview)   preview.style.display   = 'none';
                currentPrice = 0;
                return;
            }
            var p = PRODUCTS[id];
            currentPrice = p.unit_price;

            /* Fill info card */
            if (infoCard) {
                infoCard.style.display = 'block';
                var el;
                el = infoCard.querySelector('.pd-ic-name'); if (el) el.textContent = p.name;
                el = infoCard.querySelector('.pd-ic-sku');  if (el) el.textContent = p.sku || '—';
                el = infoCard.querySelector('.pd-ic-sell'); if (el) el.textContent = fmt(p.unit_price);
                el = infoCard.querySelector('.pd-ic-cost'); if (el) el.textContent = p.cost !== null ? fmt(p.cost) : '—';
            }

            /* Fill SU list */
            if (suSection) {
                suSection.style.display = 'block';
                renderSuList(p);
            }
            updatePreview();
        }

        productSel && productSel.addEventListener('change', onProductChange);

        typeRadios.forEach(function (radio) {
            radio.addEventListener('change', syncTypeCards);
        });
        typeCards.forEach(function (card, i) {
            card.addEventListener('click', function () {
                if (typeRadios[i]) { typeRadios[i].checked = true; syncTypeCards(); }
            });
        });
        valueInput && valueInput.addEventListener('input', updatePreview);

        /* Init card states on load */
        syncTypeCards();
    });
})();
</script>
@endsection
