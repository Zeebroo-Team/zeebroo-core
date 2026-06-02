@php $currency = $currency ?? ''; @endphp
@once
<style>
.pos-unit-picker{position:fixed;inset:0;z-index:340;display:flex;justify-content:center;align-items:center;padding:16px;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s,visibility .2s;}
.pos-unit-picker.is-open{opacity:1;visibility:visible;pointer-events:auto;}
.pos-unit-picker__backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
.pos-unit-picker__panel{position:relative;z-index:1;width:min(100%,480px);max-height:min(82vh,520px);display:flex;flex-direction:column;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 16px 40px rgba(0,0,0,.28);}
.pos-unit-picker__head{padding:14px 16px;border-bottom:1px solid var(--border);flex-shrink:0;}
.pos-unit-picker__head h2{margin:0 0 4px;font-size:15px;font-weight:800;}
.pos-unit-picker__head p{margin:0;font-size:12px;color:var(--muted);}
.pos-unit-picker__list{flex:1;min-height:0;overflow:auto;padding:10px 14px;display:grid;gap:8px;}
.pos-unit-picker__option{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;padding:12px 14px;text-align:left;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--text);cursor:pointer;transition:border-color .15s,background .15s;}
.pos-unit-picker__option:hover,.pos-unit-picker__option:focus-visible{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);outline:none;}
.pos-unit-picker__option__main{min-width:0;}
.pos-unit-picker__option__label{font-size:14px;font-weight:700;}
.pos-unit-picker__option__meta{font-size:11px;color:var(--muted);margin-top:3px;}
.pos-unit-picker__option__right{text-align:right;flex-shrink:0;}
.pos-unit-picker__option__price{font-size:15px;font-weight:800;}
.pos-unit-picker__option__stock{font-size:11px;color:var(--muted);margin-top:2px;}
.pos-unit-picker__option--custom{border-style:dashed;}
.pos-unit-picker__foot{padding:10px 14px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;}
.pos-unit-picker__cancel{padding:8px 14px;font-size:13px;font-weight:600;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;}
html.pos-unit-picker-open,html.pos-unit-picker-open body{overflow:hidden;}
</style>
@endonce

<div id="pos-unit-picker" class="pos-unit-picker" role="dialog" aria-modal="true" aria-labelledby="pos-unit-picker-title" aria-hidden="true">
    <div class="pos-unit-picker__backdrop" data-pos-unit-picker-close tabindex="-1" aria-label="Close"></div>
    <div class="pos-unit-picker__panel">
        <div class="pos-unit-picker__head">
            <h2 id="pos-unit-picker-title">Choose selling unit</h2>
            <p id="pos-unit-picker-subtitle"></p>
        </div>
        <div class="pos-unit-picker__list" id="pos-unit-picker-list" role="listbox"></div>
        <div class="pos-unit-picker__foot">
            <button type="button" class="pos-unit-picker__cancel" data-pos-unit-picker-close>Cancel</button>
        </div>
    </div>
</div>

@once
<script>
(function () {
    let _currencySuffix = '';
    let _pendingResolve = null;
    let _bound = false;

    function el(id) { return document.getElementById(id); }
    function money(n) { return Number(n || 0).toFixed(2) + _currencySuffix; }
    function fmtQty(n) { return String((Number(n)||0).toFixed(3)).replace(/\.?0+$/, ''); }

    function setOpen(open) {
        const modal = el('pos-unit-picker');
        if (!modal) return;
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-unit-picker-open', open);
        if (!open && _pendingResolve) { const r = _pendingResolve; _pendingResolve = null; r(null); }
    }

    function bindOnce() {
        if (_bound) return; _bound = true;
        const modal = el('pos-unit-picker');
        modal?.querySelectorAll('[data-pos-unit-picker-close]').forEach(function (e) {
            e.addEventListener('click', function () { setOpen(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && el('pos-unit-picker')?.classList.contains('is-open')) setOpen(false);
        });
    }

    /**
     * Shows the picker and returns a Promise that resolves with:
     *   { id, label, conversion_factor, display_price, stock_in_units }
     *   OR null if cancelled.
     *   OR { id: null, label: null, conversion_factor: 1, display_price: basePrice, stock_in_units: stockQty } for "custom"
     */
    window.posPickSellingUnit = function (product, sellingUnits, currencySuffix) {
        _currencySuffix = currencySuffix || '';
        return new Promise(function (resolve) {
            bindOnce();
            const modal = el('pos-unit-picker');
            const list  = el('pos-unit-picker-list');
            const sub   = el('pos-unit-picker-subtitle');
            if (!modal || !list) { resolve(null); return; }
            if (!sellingUnits || sellingUnits.length === 0) { resolve(null); return; }

            _pendingResolve = resolve;
            if (sub) sub.textContent = (product?.name || 'Product') + ' — select pack size';
            list.innerHTML = '';

            sellingUnits.forEach(function (u) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pos-unit-picker__option';
                btn.setAttribute('role', 'option');
                const stockText = u.stock_in_units > 0
                    ? fmtQty(u.stock_in_units) + ' available'
                    : '<span style="color:#f87171">Out of stock</span>';
                if (u.stock_in_units <= 0) btn.disabled = true;
                btn.innerHTML =
                    '<div class="pos-unit-picker__option__main">'
                    + '<div class="pos-unit-picker__option__label">' + escHtml(u.label) + '</div>'
                    + '<div class="pos-unit-picker__option__meta">' + fmtQty(u.conversion_factor) + ' base unit each</div>'
                    + '</div>'
                    + '<div class="pos-unit-picker__option__right">'
                    + '<div class="pos-unit-picker__option__price">' + money(u.display_price) + '</div>'
                    + '<div class="pos-unit-picker__option__stock">' + stockText + '</div>'
                    + '</div>';
                btn.addEventListener('click', function () {
                    setOpen(false);
                    const r = _pendingResolve; _pendingResolve = null;
                    if (r) r(u);
                });
                list.appendChild(btn);
            });

            // Always add "Custom quantity" option
            const customBtn = document.createElement('button');
            customBtn.type = 'button';
            customBtn.className = 'pos-unit-picker__option pos-unit-picker__option--custom';
            customBtn.setAttribute('role', 'option');
            const baseUnit = product?.unit || 'unit';
            customBtn.innerHTML =
                '<div class="pos-unit-picker__option__main">'
                + '<div class="pos-unit-picker__option__label">Custom quantity</div>'
                + '<div class="pos-unit-picker__option__meta">Enter any amount in base ' + escHtml(baseUnit) + '</div>'
                + '</div>'
                + '<div class="pos-unit-picker__option__right">'
                + '<div class="pos-unit-picker__option__price">—</div>'
                + '</div>';
            customBtn.addEventListener('click', function () {
                setOpen(false);
                const r = _pendingResolve; _pendingResolve = null;
                if (r) r({ id: null, label: null, conversion_factor: 1.0, display_price: product?.unit_sell_price || 0, stock_in_units: product?.stock_quantity || 0 });
            });
            list.appendChild(customBtn);

            setOpen(true);
            list.querySelector('button:not([disabled])')?.focus();
        });
    };

    function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
})();
</script>
@endonce
