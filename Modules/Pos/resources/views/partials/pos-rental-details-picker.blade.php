@php
    $currency = $currency ?? '';
@endphp

@once
<style>
.pos-rental-picker{position:fixed;inset:0;z-index:340;display:flex;justify-content:center;align-items:center;padding:16px;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease,visibility .2s ease;}
.pos-rental-picker.is-open{opacity:1;visibility:visible;pointer-events:auto;}
.pos-rental-picker__backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
.pos-rental-picker__panel{position:relative;z-index:1;width:min(100%,420px);display:flex;flex-direction:column;border-radius:12px;border:1px solid var(--border);background:var(--card);box-shadow:0 16px 40px rgba(0,0,0,.28);}
.pos-rental-picker__head{padding:14px 16px;border-bottom:1px solid var(--border);}
.pos-rental-picker__head h2{margin:0 0 4px;font-size:15px;font-weight:800;}
.pos-rental-picker__head p{margin:0;font-size:12px;color:var(--muted);}
.pos-rental-picker__body{padding:14px 16px;display:grid;gap:12px;}
.pos-rental-picker__field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px;}
.pos-rental-picker__field input{width:100%;box-sizing:border-box;padding:9px 10px;font-size:14px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-rental-picker__hint{margin:0;padding:10px 12px;border-radius:9px;background:color-mix(in srgb,var(--primary) 10%,transparent);border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));font-size:13px;font-weight:700;color:var(--text);display:flex;justify-content:space-between;}
.pos-rental-picker__err{margin:0;font-size:12px;color:#f87171;font-weight:600;}
.pos-rental-picker__foot{padding:10px 16px 14px;display:flex;justify-content:flex-end;gap:8px;}
.pos-rental-picker__cancel,.pos-rental-picker__confirm{padding:8px 16px;font-size:13px;font-weight:700;border-radius:8px;cursor:pointer;}
.pos-rental-picker__cancel{border:1px solid var(--border);background:transparent;color:var(--text);}
.pos-rental-picker__confirm{border:1px solid color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 16%,transparent);color:var(--text);}
html.pos-rental-picker-open,html.pos-rental-picker-open body{overflow:hidden;}
</style>
@endonce

<div id="pos-rental-picker" class="pos-rental-picker" role="dialog" aria-modal="true" aria-labelledby="pos-rental-picker-title" aria-hidden="true">
    <div class="pos-rental-picker__backdrop" data-pos-rental-picker-close tabindex="-1" aria-label="Close"></div>
    <div class="pos-rental-picker__panel">
        <div class="pos-rental-picker__head">
            <h2 id="pos-rental-picker-title">Rental details</h2>
            <p id="pos-rental-picker-subtitle"></p>
        </div>
        <div class="pos-rental-picker__body">
            <div class="pos-rental-picker__field">
                <label for="pos-rental-picker-date">Return date</label>
                <input type="date" id="pos-rental-picker-date">
            </div>
            <p class="pos-rental-picker__hint">
                <span id="pos-rental-picker-days-label">1 day</span>
                <span id="pos-rental-picker-total">0.00</span>
            </p>
            <p class="pos-rental-picker__err" id="pos-rental-picker-err" hidden></p>
        </div>
        <div class="pos-rental-picker__foot">
            <button type="button" class="pos-rental-picker__cancel" data-pos-rental-picker-close>Cancel</button>
            <button type="button" class="pos-rental-picker__confirm" id="pos-rental-picker-confirm">Add to sale</button>
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

    function modalEl() { return document.getElementById('pos-rental-picker'); }

    function money(n) { return Number(n || 0).toFixed(2) + currencySuffix; }

    function setOpen(open) {
        const modal = modalEl();
        if (!modal) return;
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-rental-picker-open', open);
        if (!open && pendingResolve) {
            const resolve = pendingResolve;
            pendingResolve = null;
            resolve(null);
        }
    }

    function daysBetween(dateStr) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const target = new Date(dateStr + 'T00:00:00');
        const diff = Math.round((target - today) / 86400000);
        return Math.max(1, diff);
    }

    function updateHint() {
        const dateInput = document.getElementById('pos-rental-picker-date');
        const daysLabel = document.getElementById('pos-rental-picker-days-label');
        const totalEl = document.getElementById('pos-rental-picker-total');
        const errEl = document.getElementById('pos-rental-picker-err');
        if (!dateInput.value) {
            daysLabel.textContent = '—';
            totalEl.textContent = money(0);
            return;
        }
        const days = daysBetween(dateInput.value);
        const rate = currentProduct ? Number(currentProduct.dailyRate) || 0 : 0;
        daysLabel.textContent = days + ' day' + (days === 1 ? '' : 's');
        totalEl.textContent = money(rate * days);
        errEl.hidden = true;
    }

    function bindPickerOnce() {
        if (pickerBound) return;
        pickerBound = true;
        const modal = modalEl();
        modal?.querySelectorAll('[data-pos-rental-picker-close]').forEach(function (el) {
            el.addEventListener('click', function () { setOpen(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal?.classList.contains('is-open')) setOpen(false);
        });
        document.getElementById('pos-rental-picker-date')?.addEventListener('change', updateHint);
        document.getElementById('pos-rental-picker-confirm')?.addEventListener('click', function () {
            const dateInput = document.getElementById('pos-rental-picker-date');
            const errEl = document.getElementById('pos-rental-picker-err');
            if (!dateInput.value) {
                errEl.textContent = 'Choose a return date.';
                errEl.hidden = false;
                return;
            }
            const days = daysBetween(dateInput.value);
            const maxDays = currentProduct ? (Number(currentProduct.maxDays) || 1) : 1;
            if (days > maxDays) {
                errEl.textContent = 'Return date exceeds the maximum rental period (' + maxDays + ' days).';
                errEl.hidden = false;
                return;
            }
            const resolve = pendingResolve;
            pendingResolve = null;
            setOpen(false);
            resolve({ returnDate: dateInput.value, days: days });
        });
    }

    window.posPickRentalDetails = function (product) {
        return new Promise(function (resolve) {
            bindPickerOnce();
            currentProduct = product || {};
            const subtitleEl = document.getElementById('pos-rental-picker-subtitle');
            const dateInput = document.getElementById('pos-rental-picker-date');
            const errEl = document.getElementById('pos-rental-picker-err');

            const maxDays = Number(currentProduct.maxDays) || 1;
            const rate = Number(currentProduct.dailyRate) || 0;
            if (subtitleEl) {
                subtitleEl.textContent = (currentProduct.name || 'Product')
                    + ' — ' + money(rate) + '/day · max ' + maxDays + ' day' + (maxDays === 1 ? '' : 's');
            }

            const today = new Date();
            const minStr = today.toISOString().slice(0, 10);
            const maxDate = new Date(today);
            maxDate.setDate(maxDate.getDate() + maxDays);
            const maxStr = maxDate.toISOString().slice(0, 10);
            dateInput.min = minStr;
            dateInput.max = maxStr;
            dateInput.value = minStr;
            errEl.hidden = true;
            updateHint();

            pendingResolve = resolve;
            setOpen(true);
        });
    };

    window.initPosRentalDetailsPicker = function (options) {
        options = options || {};
        currencySuffix = options.currencySuffix || '';
        bindPickerOnce();
    };
})();
</script>
@endonce
