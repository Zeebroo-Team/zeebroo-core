@php
    $currency = $currency ?? '';
@endphp

@once
<style>
.pos-dynamic-picker{position:fixed;inset:0;z-index:340;display:flex;justify-content:center;align-items:center;padding:16px;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease,visibility .2s ease;}
.pos-dynamic-picker.is-open{opacity:1;visibility:visible;pointer-events:auto;}
.pos-dynamic-picker__backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
.pos-dynamic-picker__panel{position:relative;z-index:1;width:min(100%,380px);display:flex;flex-direction:column;border-radius:12px;border:1px solid var(--border);background:var(--card);box-shadow:0 16px 40px rgba(0,0,0,.28);}
.pos-dynamic-picker__head{padding:14px 16px;border-bottom:1px solid var(--border);}
.pos-dynamic-picker__head h2{margin:0 0 4px;font-size:15px;font-weight:800;}
.pos-dynamic-picker__head p{margin:0;font-size:12px;color:var(--muted);}
.pos-dynamic-picker__body{padding:14px 16px;display:grid;gap:10px;}
.pos-dynamic-picker__field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px;}
.pos-dynamic-picker__field input{width:100%;box-sizing:border-box;padding:10px 12px;font-size:18px;font-weight:800;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);text-align:right;}
.pos-dynamic-picker__hint{margin:0;font-size:12px;color:var(--muted);}
.pos-dynamic-picker__err{margin:0;font-size:12px;color:#f87171;font-weight:600;}
.pos-dynamic-picker__foot{padding:10px 16px 14px;display:flex;justify-content:flex-end;gap:8px;}
.pos-dynamic-picker__cancel,.pos-dynamic-picker__confirm{padding:8px 16px;font-size:13px;font-weight:700;border-radius:8px;cursor:pointer;}
.pos-dynamic-picker__cancel{border:1px solid var(--border);background:transparent;color:var(--text);}
.pos-dynamic-picker__confirm{border:1px solid color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 16%,transparent);color:var(--text);}
html.pos-dynamic-picker-open,html.pos-dynamic-picker-open body{overflow:hidden;}
</style>
@endonce

<div id="pos-dynamic-picker" class="pos-dynamic-picker" role="dialog" aria-modal="true" aria-labelledby="pos-dynamic-picker-title" aria-hidden="true">
    <div class="pos-dynamic-picker__backdrop" data-pos-dynamic-picker-close tabindex="-1" aria-label="Close"></div>
    <div class="pos-dynamic-picker__panel">
        <div class="pos-dynamic-picker__head">
            <h2 id="pos-dynamic-picker-title">Enter amount</h2>
            <p id="pos-dynamic-picker-subtitle"></p>
        </div>
        <div class="pos-dynamic-picker__body">
            <div class="pos-dynamic-picker__field">
                <label id="pos-dynamic-picker-label" for="pos-dynamic-picker-input">Amount</label>
                <input type="number" min="0.01" step="0.01" inputmode="decimal" id="pos-dynamic-picker-input" placeholder="0.00">
            </div>
            <p class="pos-dynamic-picker__hint" id="pos-dynamic-picker-hint"></p>
            <p class="pos-dynamic-picker__err" id="pos-dynamic-picker-err" hidden></p>
        </div>
        <div class="pos-dynamic-picker__foot">
            <button type="button" class="pos-dynamic-picker__cancel" data-pos-dynamic-picker-close>Cancel</button>
            <button type="button" class="pos-dynamic-picker__confirm" id="pos-dynamic-picker-confirm">Add to sale</button>
        </div>
    </div>
</div>

@once
<script>
(function () {
    let currencySuffix = '';
    let pendingResolve = null;
    let pickerBound = false;
    let currentProduct = null;

    function modalEl() { return document.getElementById('pos-dynamic-picker'); }

    function money(n) { return Number(n || 0).toFixed(2) + currencySuffix; }

    function setOpen(open) {
        const modal = modalEl();
        if (!modal) return;
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-dynamic-picker-open', open);
        if (!open && pendingResolve) {
            const resolve = pendingResolve;
            pendingResolve = null;
            resolve(null);
        }
    }

    function updateHint() {
        const input = document.getElementById('pos-dynamic-picker-input');
        const hintEl = document.getElementById('pos-dynamic-picker-hint');
        const errEl = document.getElementById('pos-dynamic-picker-err');
        errEl.hidden = true;
        const amount = parseFloat(input.value);
        if (!Number.isFinite(amount) || amount <= 0) {
            hintEl.textContent = currentProduct?.linked
                ? 'Enter the amount sold — deducts that many units from stock.'
                : 'Enter the price to charge for 1 unit.';
            return;
        }
        if (currentProduct?.linked) {
            hintEl.textContent = amount.toFixed(2) + ' units deducted · balance after: '
                + Math.max(0, (Number(currentProduct.stock) || 0) - amount).toFixed(2);
        } else {
            hintEl.textContent = 'Sell 1 × ' + money(amount);
        }
    }

    function bindPickerOnce() {
        if (pickerBound) return;
        pickerBound = true;
        const modal = modalEl();
        modal?.querySelectorAll('[data-pos-dynamic-picker-close]').forEach(function (el) {
            el.addEventListener('click', function () { setOpen(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal?.classList.contains('is-open')) setOpen(false);
        });
        document.getElementById('pos-dynamic-picker-input')?.addEventListener('input', updateHint);
        document.getElementById('pos-dynamic-picker-confirm')?.addEventListener('click', function () {
            const input = document.getElementById('pos-dynamic-picker-input');
            const errEl = document.getElementById('pos-dynamic-picker-err');
            const amount = parseFloat(input.value);
            if (!Number.isFinite(amount) || amount <= 0) {
                errEl.textContent = 'Enter an amount greater than zero.';
                errEl.hidden = false;
                return;
            }
            if (currentProduct?.linked && amount > (Number(currentProduct.stock) || 0)) {
                errEl.textContent = 'Amount exceeds available stock.';
                errEl.hidden = false;
                return;
            }
            const resolve = pendingResolve;
            pendingResolve = null;
            setOpen(false);
            resolve({ amount: amount });
        });
    }

    window.posPickDynamicPrice = function (product) {
        return new Promise(function (resolve) {
            bindPickerOnce();
            currentProduct = product || {};
            const subtitleEl = document.getElementById('pos-dynamic-picker-subtitle');
            const labelEl = document.getElementById('pos-dynamic-picker-label');
            const input = document.getElementById('pos-dynamic-picker-input');
            const errEl = document.getElementById('pos-dynamic-picker-err');

            if (subtitleEl) subtitleEl.textContent = currentProduct.name || 'Product';
            if (labelEl) labelEl.textContent = currentProduct.linked ? 'Amount' : 'Price';
            input.value = '';
            errEl.hidden = true;
            updateHint();

            pendingResolve = resolve;
            setOpen(true);
            setTimeout(function () { input.focus(); }, 50);
        });
    };

    window.initPosDynamicPricePicker = function (options) {
        options = options || {};
        currencySuffix = options.currencySuffix || '';
        bindPickerOnce();
    };
})();
</script>
@endonce
