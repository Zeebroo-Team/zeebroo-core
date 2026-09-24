@php
    $rsCurrencySuffix = filled($currency ?? '') ? ' '.$currency : '';
@endphp
<style>
.pos-rs-bar{display:inline-flex;align-items:center;gap:6px;}
.pos-rs-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;}
.pos-rs-pill:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pos-rs-pill--closed{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));color:#b45309;}
.pos-rs-select{padding:6px 8px;font-size:12px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-rs-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:1000;padding:16px;}
.pos-rs-modal-backdrop.is-open{display:flex;}
.pos-rs-modal{width:100%;max-width:360px;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;}
.pos-rs-modal h3{margin:0 0 10px;font-size:15px;font-weight:800;}
.pos-rs-modal label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.pos-rs-modal input{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);margin-bottom:12px;}
.pos-rs-modal-actions{display:flex;justify-content:flex-end;gap:8px;}
.pos-rs-summary{font-size:12px;color:var(--muted);margin:0 0 12px;line-height:1.5;}
</style>

<div class="pos-rs-bar">
    <select class="pos-rs-select" id="pos-rs-counter-select" title="Counter">
        <option value="">No counter</option>
    </select>
    <input type="hidden" name="pos_counter_id" id="pos-rs-counter-hidden" form="pos-checkout-form" value="">

    <button type="button" class="pos-rs-pill" id="pos-rs-drawer-pill" title="Register drawer">
        <i class="fa fa-cash-register"></i> <span id="pos-rs-drawer-label">Loading…</span>
    </button>
</div>

<div class="pos-rs-modal-backdrop" id="pos-rs-open-backdrop">
    <div class="pos-rs-modal">
        <h3>Open register</h3>
        <label for="pos-rs-opening-float">Opening cash float @if(filled($currency ?? ''))({{ $currency }})@endif</label>
        <input type="number" id="pos-rs-opening-float" min="0" step="0.01" value="0">
        <div class="pos-rs-modal-actions">
            <button type="button" class="pos-btn" data-pos-rs-close>Cancel</button>
            <button type="button" class="pos-btn pos-btn--primary" id="pos-rs-open-submit">Open register</button>
        </div>
    </div>
</div>

<div class="pos-rs-modal-backdrop" id="pos-rs-status-backdrop">
    <div class="pos-rs-modal">
        <h3>Register drawer</h3>
        <p class="pos-rs-summary" id="pos-rs-status-summary">—</p>
        <label for="pos-rs-withdraw-amount">Add withdrawal @if(filled($currency ?? ''))({{ $currency }})@endif</label>
        <input type="number" id="pos-rs-withdraw-amount" min="0.01" step="0.01" placeholder="0.00">
        <label for="pos-rs-withdraw-note">Note (optional)</label>
        <input type="text" id="pos-rs-withdraw-note" maxlength="255">
        <div class="pos-rs-modal-actions">
            <button type="button" class="pos-btn" data-pos-rs-close>Close</button>
            <button type="button" class="pos-btn pos-btn--primary" id="pos-rs-withdraw-submit">Record withdrawal</button>
        </div>
    </div>
</div>

@once
<script>
(function () {
    const currencySuffix = @json($rsCurrencySuffix ?? '');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const drawerLabel = document.getElementById('pos-rs-drawer-label');
    const drawerPill = document.getElementById('pos-rs-drawer-pill');
    const counterSelect = document.getElementById('pos-rs-counter-select');
    const counterHidden = document.getElementById('pos-rs-counter-hidden');
    const openBackdrop = document.getElementById('pos-rs-open-backdrop');
    const statusBackdrop = document.getElementById('pos-rs-status-backdrop');

    function money(n) {
        return Number(n || 0).toFixed(2) + currencySuffix;
    }

    function closeModals() {
        openBackdrop.classList.remove('is-open');
        statusBackdrop.classList.remove('is-open');
    }
    document.querySelectorAll('[data-pos-rs-close]').forEach((btn) => btn.addEventListener('click', closeModals));

    let lastStatus = null;

    function refreshDrawerStatus() {
        fetch(@json(route('pos.register-session.drawer-status')), { credentials: 'same-origin' })
            .then((r) => r.json())
            .then((res) => {
                const data = res.data || {};
                lastStatus = data;
                window.dispatchEvent(new CustomEvent('pos-drawer-status', { detail: data }));
                if (data.is_opened) {
                    drawerPill.classList.remove('pos-rs-pill--closed');
                    drawerLabel.textContent = 'Balance ' + money(data.balance);
                } else {
                    drawerPill.classList.add('pos-rs-pill--closed');
                    drawerLabel.textContent = 'Open register';
                }
            })
            .catch(() => { drawerLabel.textContent = 'Register'; });
    }

    function refreshCounters() {
        fetch(@json(route('pos.register-session.counters')), { credentials: 'same-origin' })
            .then((r) => r.json())
            .then((res) => {
                const counters = res.data || [];
                const current = counterSelect.value;
                counterSelect.querySelectorAll('option:not(:first-child)').forEach((o) => o.remove());
                counters.forEach((c) => {
                    const opt = document.createElement('option');
                    opt.value = String(c.id);
                    opt.textContent = c.name;
                    counterSelect.appendChild(opt);
                });
                if (current) counterSelect.value = current;
                counterHidden.value = counterSelect.value;
            })
            .catch(() => {});
    }

    counterSelect?.addEventListener('change', function () {
        counterHidden.value = counterSelect.value;
    });

    drawerPill?.addEventListener('click', function () {
        if (lastStatus && lastStatus.is_opened) {
            const w = lastStatus.withdrawals || [];
            const lines = [
                'Opening float: ' + money(lastStatus.opening_float),
                'Cash sales today: ' + money(lastStatus.cash_sales),
                'Withdrawals: ' + money(lastStatus.total_withdrawals) + ' (' + w.length + ')',
                'Balance: ' + money(lastStatus.balance),
            ];
            document.getElementById('pos-rs-status-summary').textContent = lines.join(' · ');
            statusBackdrop.classList.add('is-open');
        } else {
            document.getElementById('pos-rs-opening-float').value = '0';
            openBackdrop.classList.add('is-open');
        }
    });

    document.getElementById('pos-rs-open-submit')?.addEventListener('click', function () {
        const val = parseFloat(document.getElementById('pos-rs-opening-float').value) || 0;
        fetch(@json(route('pos.register-session.drawer-open')), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ opening_float: val }),
        })
            .then((r) => r.json())
            .then(() => { closeModals(); refreshDrawerStatus(); })
            .catch(() => {});
    });

    document.getElementById('pos-rs-withdraw-submit')?.addEventListener('click', function () {
        const amount = parseFloat(document.getElementById('pos-rs-withdraw-amount').value) || 0;
        if (amount <= 0) return;
        const note = document.getElementById('pos-rs-withdraw-note').value || '';
        fetch(@json(route('pos.register-session.drawer-withdraw')), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ amount: amount, note: note }),
        })
            .then((r) => r.json())
            .then(() => {
                document.getElementById('pos-rs-withdraw-amount').value = '';
                document.getElementById('pos-rs-withdraw-note').value = '';
                closeModals();
                refreshDrawerStatus();
            })
            .catch(() => {});
    });

    refreshDrawerStatus();
    refreshCounters();
})();
</script>
@endonce
