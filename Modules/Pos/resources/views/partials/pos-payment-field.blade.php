@php
    $selectedPayment = old('payment_method', 'cash');
@endphp

@once
<style>
.pos-pay-field{margin-bottom:10px;}
.pos-pay-field > .pos-pay-field__label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:8px;}
.pos-pay-methods{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;}
.pos-pay-method{padding:9px 8px;font-size:11px;font-weight:700;line-height:1.25;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--muted);cursor:pointer;text-align:center;transition:border-color .15s ease,background .15s ease,color .15s ease;}
.pos-pay-method:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));color:var(--text);}
.pos-pay-method.is-active{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--text);box-shadow:0 0 0 1px color-mix(in srgb,var(--primary) 25%,transparent);}
.pos-pay-cash-panel{margin-top:10px;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 96%,transparent);display:grid;gap:8px;}
.pos-pay-cash-panel[hidden]{display:none!important;}
.pos-pay-cash-row{display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:13px;}
.pos-pay-cash-row span{color:var(--muted);}
.pos-pay-cash-row strong{color:var(--text);font-weight:800;font-size:14px;}
.pos-pay-cash-row--change strong{color:color-mix(in srgb,#22c55e 70%,var(--text));font-size:16px;}
.pos-pay-cash-input label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.pos-pay-cash-input input{width:100%;box-sizing:border-box;padding:10px 12px;font-size:18px;font-weight:800;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);text-align:right;}
.pos-pay-cash-input input:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 50%,var(--border));}
.pos-pay-cash-input input[readonly]{cursor:pointer;}
[data-pos-numpad].is-numpad-target{border-color:color-mix(in srgb,var(--primary) 55%,var(--border))!important;box-shadow:0 0 0 1px color-mix(in srgb,var(--primary) 25%,transparent);}
.pos-pay-hint{margin:0;font-size:11px;color:var(--muted);line-height:1.4;}
.pos-pay-hint--err{color:color-mix(in srgb,#f87171 85%,var(--text));}
.pos-checkout-form{display:flex;flex-direction:column;min-height:0;height:100%;}
.pos-checkout-form__scroll{flex:1;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;padding:12px;}
.pos-checkout-form__footer{flex-shrink:0;padding:10px 12px 12px;border-top:1px solid var(--border);background:color-mix(in srgb,var(--card) 98%,transparent);}
.pos-checkout-form__footer .pos-online__pay-btn,.pos-checkout-form__footer .pos-btn--primary{width:100%;margin-top:8px;box-sizing:border-box;}
</style>
@endonce

<div class="pos-pay-field" id="pos-payment-field">
    <span class="pos-pay-field__label">Payment</span>
    <input type="hidden" name="payment_method" id="pos-payment-method" value="{{ $selectedPayment }}">
    <input type="hidden" name="credit_account_id" id="pos-credit-account" value="{{ old('credit_account_id', $defaultDepositAccountId ?? '') }}">
    <input type="hidden" name="amount_paid" id="pos-amount-paid" value="{{ old('amount_paid', '0') }}">
    <input type="hidden" name="amount_tendered" id="pos-amount-tendered" value="{{ old('amount_tendered', '') }}">

    <div class="pos-pay-methods" role="group" aria-label="Payment method">
        <button type="button" class="pos-pay-method @if($selectedPayment === 'cash') is-active @endif" data-pos-pay-method="cash">
            <i class="fa fa-money-bill-wave" aria-hidden="true"></i><br>Cash
        </button>
        <button type="button" class="pos-pay-method @if($selectedPayment === 'card') is-active @endif" data-pos-pay-method="card">
            <i class="fa fa-credit-card" aria-hidden="true"></i><br>Card payment
        </button>
        <button type="button" class="pos-pay-method @if($selectedPayment === 'credit') is-active @endif" data-pos-pay-method="credit">
            <i class="fa fa-file-invoice" aria-hidden="true"></i><br>Credit payment
        </button>
    </div>

    <div id="pos-pay-cash-panel" class="pos-pay-cash-panel" @if($selectedPayment !== 'cash') hidden @endif>
        <div class="pos-pay-cash-input">
            <label for="pos-cash-tendered-input">Amount customer gave</label>
            <input type="text" id="pos-cash-tendered-input" value="{{ old('amount_tendered') }}" inputmode="none" placeholder="0.00" autocomplete="off" data-pos-numpad="money" readonly>
        </div>
        <div class="pos-pay-cash-row">
            <span>Amount due</span>
            <strong id="pos-cash-due">0.00</strong>
        </div>
        <div class="pos-pay-cash-row pos-pay-cash-row--change">
            <span>Change / balance</span>
            <strong id="pos-cash-change">0.00</strong>
        </div>
        <p id="pos-pay-cash-hint" class="pos-pay-hint" hidden></p>
    </div>

    <p id="pos-pay-card-hint" class="pos-pay-hint" @if($selectedPayment !== 'card') hidden @endif>
        Card payment is recorded to your default deposit account from POS settings.
    </p>
    <p id="pos-pay-credit-hint" class="pos-pay-hint" @if($selectedPayment !== 'credit') hidden @endif>
        Credit payment — no ledger entry. Customer pays later.
    </p>

</div>

@once
<script>
window.initPosPaymentField = function (options) {
    options = options || {};
    const currencySuffix = options.currencySuffix || '';
    const field = document.getElementById('pos-payment-field');
    if (!field) return;

    const methodInput = document.getElementById('pos-payment-method');
    const creditAccountEl = document.getElementById('pos-credit-account');
    const amountPaidEl = document.getElementById('pos-amount-paid');
    const amountTenderedEl = document.getElementById('pos-amount-tendered');
    const cashPanel = document.getElementById('pos-pay-cash-panel');
    const cashInput = document.getElementById('pos-cash-tendered-input');
    const cashDueEl = document.getElementById('pos-cash-due');
    const cashChangeEl = document.getElementById('pos-cash-change');
    const cashHintEl = document.getElementById('pos-pay-cash-hint');
    const cardHintEl = document.getElementById('pos-pay-card-hint');
    const creditHintEl = document.getElementById('pos-pay-credit-hint');
    const methodBtns = field.querySelectorAll('[data-pos-pay-method]');
    const completeBtn = options.completeBtn || document.getElementById('pos-complete-sale');
    const checkoutForm = options.checkoutForm || document.getElementById('pos-checkout-form');
    const numpadEl = document.getElementById('pos-numpad');
    const discountInput = document.getElementById('pos-discount-percent');

    let cartTotal = 0;
    let numpadTarget = null;

    function getNumpadTargets() {
        const targets = [];
        if (cashInput) targets.push(cashInput);
        if (discountInput && !discountInput.hidden) targets.push(discountInput);
        return targets;
    }

    function resolveNumpadTarget() {
        const active = document.activeElement;
        if (active && active.dataset && active.dataset.posNumpad) {
            return active;
        }
        if (numpadTarget && document.contains(numpadTarget)) {
            return numpadTarget;
        }
        if (getMethod() === 'cash' && cashInput) {
            return cashInput;
        }
        if (discountInput && !discountInput.hidden) {
            return discountInput;
        }
        return cashInput;
    }

    function highlightNumpadTarget(target) {
        getNumpadTargets().forEach(function (el) {
            el.classList.toggle('is-numpad-target', el === target);
        });
    }

    function setNumpadTarget(target) {
        numpadTarget = target || null;
        highlightNumpadTarget(resolveNumpadTarget());
    }

    function dispatchNumpadInput(el) {
        if (!el) return;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function appendNumpadDigit(digit) {
        const target = resolveNumpadTarget();
        if (!target) return;

        const mode = target.dataset.posNumpad || 'money';
        let val = String(target.value || '');

        if (digit === '.') {
            if (mode === 'percent') return;
            if (val.includes('.')) return;
            target.value = val === '' ? '0.' : val + '.';
            dispatchNumpadInput(target);
            return;
        }

        if (val === '0' && digit !== '.') {
            val = '';
        }

        const next = val + digit;
        if (mode === 'percent') {
            const num = parseFloat(next);
            if (!Number.isFinite(num) || num > 100) return;
            if (next.includes('.') && next.split('.')[1]?.length > 2) return;
        } else if (next.includes('.')) {
            const decimals = next.split('.')[1] || '';
            if (decimals.length > 2) return;
        }

        target.value = next;
        dispatchNumpadInput(target);
    }

    function numpadBackspace() {
        const target = resolveNumpadTarget();
        if (!target) return;
        target.value = String(target.value || '').slice(0, -1);
        dispatchNumpadInput(target);
    }

    function numpadClear() {
        const target = resolveNumpadTarget();
        if (!target) return;
        target.value = '';
        dispatchNumpadInput(target);
    }

    function numpadExact() {
        if (!cashInput || getMethod() !== 'cash') return;
        setNumpadTarget(cashInput);
        cashInput.value = cartTotal > 0 ? cartTotal.toFixed(2) : '';
        dispatchNumpadInput(cashInput);
    }

    function syncNumpadState(cartHasItems) {
        const enabled = cartHasItems !== false && cartTotal > 0;
        if (numpadEl) {
            numpadEl.classList.toggle('is-disabled', !enabled);
            numpadEl.querySelectorAll('.pos-numpad__key').forEach(function (btn) {
                btn.disabled = !enabled;
            });
        }
        const exactBtn = numpadEl?.querySelector('[data-pos-numpad-action="exact"]');
        if (exactBtn) {
            exactBtn.disabled = !enabled || getMethod() !== 'cash';
        }
    }

    function money(n) {
        return Number(n || 0).toFixed(2) + currencySuffix;
    }

    function getMethod() {
        return methodInput?.value || 'cash';
    }

    function setMethod(method) {
        if (!methodInput) return;
        methodInput.value = method;
        methodBtns.forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.posPayMethod === method);
        });
        if (cashPanel) cashPanel.hidden = method !== 'cash';
        if (cardHintEl) cardHintEl.hidden = method !== 'card';
        if (creditHintEl) creditHintEl.hidden = method !== 'credit';
        if (method === 'cash') {
            setNumpadTarget(cashInput);
        }
        syncCashPanel();
        validateComplete();
        syncNumpadState(cartTotal > 0);
    }

    function syncCashPanel() {
        const method = getMethod();
        const total = cartTotal;
        if (cashDueEl) cashDueEl.textContent = money(total);

        if (method !== 'cash') {
            if (amountTenderedEl) amountTenderedEl.value = '';
            return;
        }

        const tendered = parseFloat(cashInput?.value);
        const hasTendered = Number.isFinite(tendered) && tendered >= 0;
        const change = hasTendered ? Math.max(0, Math.round((tendered - total) * 100) / 100) : 0;

        if (cashChangeEl) {
            cashChangeEl.textContent = money(change);
            cashChangeEl.parentElement?.classList.toggle('pos-pay-cash-row--short', hasTendered && tendered + 0.001 < total);
        }

        if (amountTenderedEl) {
            amountTenderedEl.value = hasTendered ? tendered.toFixed(2) : '';
        }

        if (cashHintEl) {
            if (!hasTendered && total > 0) {
                cashHintEl.hidden = false;
                cashHintEl.textContent = 'Enter the amount the customer gave.';
                cashHintEl.classList.remove('pos-pay-hint--err');
            } else if (hasTendered && tendered + 0.001 < total) {
                cashHintEl.hidden = false;
                cashHintEl.textContent = 'Amount received is less than the total due.';
                cashHintEl.classList.add('pos-pay-hint--err');
            } else if (hasTendered && change > 0) {
                cashHintEl.hidden = false;
                cashHintEl.textContent = 'Give change: ' + money(change);
                cashHintEl.classList.remove('pos-pay-hint--err');
            } else {
                cashHintEl.hidden = true;
                cashHintEl.textContent = '';
                cashHintEl.classList.remove('pos-pay-hint--err');
            }
        }
    }

    function validateComplete(cartHasItems) {
        if (!completeBtn) return;
        const hasItems = cartHasItems === undefined ? cartTotal > 0 : !!cartHasItems;
        if (!hasItems || cartTotal <= 0) {
            completeBtn.disabled = true;
            completeBtn.classList.remove('is-pay-blocked');
            return;
        }
        const method = getMethod();
        let canPay = true;
        if (method === 'cash') {
            const tendered = parseFloat(cashInput?.value);
            canPay = Number.isFinite(tendered) && tendered + 0.001 >= cartTotal;
        } else if (method === 'card') {
            canPay = !!(creditAccountEl?.value);
        }
        completeBtn.disabled = !canPay;
        completeBtn.classList.toggle('is-pay-blocked', !canPay);
    }

    window.posPaymentSyncTotal = function (total, cartHasItems) {
        cartTotal = Math.max(0, Number(total) || 0);
        const hasItems = cartHasItems !== false && cartTotal > 0;
        if (amountPaidEl) amountPaidEl.value = cartTotal.toFixed(2);
        if (getMethod() === 'cash' && cashInput && hasItems && !String(cashInput.value || '').trim()) {
            cashInput.value = cartTotal.toFixed(2);
        }
        syncCashPanel();
        validateComplete(hasItems);
        syncNumpadState(hasItems);
    };

    getNumpadTargets().forEach(function (el) {
        el.addEventListener('focus', function () {
            setNumpadTarget(el);
        });
    });

    numpadEl?.querySelectorAll('[data-pos-numpad-key]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            appendNumpadDigit(btn.dataset.posNumpadKey || '');
        });
    });

    numpadEl?.querySelectorAll('[data-pos-numpad-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const action = btn.dataset.posNumpadAction;
            if (action === 'back') numpadBackspace();
            else if (action === 'clear') numpadClear();
            else if (action === 'exact') numpadExact();
        });
    });

    methodBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setMethod(btn.dataset.posPayMethod || 'cash');
        });
    });

    cashInput?.addEventListener('input', function () {
        syncCashPanel();
        validateComplete(cartTotal > 0);
    });

    checkoutForm?.addEventListener('submit', function (e) {
        const method = getMethod();
        if (method === 'cash') {
            const tendered = parseFloat(cashInput?.value);
            if (!Number.isFinite(tendered) || tendered + 0.001 < cartTotal) {
                e.preventDefault();
                syncCashPanel();
                cashInput?.focus();
                return;
            }
        }
        if (method === 'card' && !creditAccountEl?.value) {
            e.preventDefault();
            window.alert('Set a deposit account in POS settings before recording card payments.');
        }
    });

    const settingsDeposit = document.getElementById('pos-settings-deposit');
    if (settingsDeposit && creditAccountEl) {
        settingsDeposit.addEventListener('change', function () {
            creditAccountEl.value = settingsDeposit.value || '';
            validateComplete(cartTotal > 0);
        });
    }

    setMethod(getMethod());
    setNumpadTarget(cashInput);
    syncNumpadState(false);

    window.posPaymentApi = {
        setMethod: setMethod,
        getMethod: getMethod,
        focusCash: function () {
            if (getMethod() !== 'cash') setMethod('cash');
            cashInput?.focus();
            setNumpadTarget(cashInput);
        },
        focusDiscount: function () {
            if (!discountInput || discountInput.hidden) return;
            discountInput.focus();
            setNumpadTarget(discountInput);
        },
        numpadDigit: appendNumpadDigit,
        numpadBackspace: numpadBackspace,
        numpadClear: numpadClear,
        numpadExact: numpadExact,
        tryComplete: function () {
            if (!completeBtn || completeBtn.disabled || !checkoutForm) return false;
            checkoutForm.requestSubmit();
            return true;
        },
    };

    document.addEventListener('keydown', function (e) {
        const t = e.target;
        if (!t?.dataset?.posNumpad || e.ctrlKey || e.metaKey || e.altKey) return;
        if (e.key >= '0' && e.key <= '9') {
            e.preventDefault();
            appendNumpadDigit(e.key);
            return;
        }
        if (e.key === '.') {
            e.preventDefault();
            appendNumpadDigit('.');
            return;
        }
        if (e.key === 'Backspace') {
            e.preventDefault();
            numpadBackspace();
            return;
        }
        if (e.key === 'Delete') {
            e.preventDefault();
            numpadClear();
        }
    });
};
</script>
@endonce
