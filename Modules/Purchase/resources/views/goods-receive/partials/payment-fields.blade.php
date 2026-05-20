@php
    use Modules\Purchase\Models\Purchase;

    $canPayByCheque = filter_var($canPayByCheque ?? false, FILTER_VALIDATE_BOOLEAN);
    $paymentMethods = Purchase::paymentMethods();
    $defaultPaymentMethod = old('payment_method', $defaultPaymentMethod ?? Purchase::PAYMENT_CREDIT);
    if (!$canPayByCheque && $defaultPaymentMethod === Purchase::PAYMENT_CHEQUE) {
        $defaultPaymentMethod = Purchase::PAYMENT_CREDIT;
    }
    $showChequeRef = $canPayByCheque && $defaultPaymentMethod === Purchase::PAYMENT_CHEQUE;
    $accounts = $accounts ?? collect();
    $hasPaymentAccounts = filter_var($hasPaymentAccounts ?? $accounts->isNotEmpty(), FILTER_VALIDATE_BOOLEAN);
    $showPayAccount = in_array($defaultPaymentMethod, [Purchase::PAYMENT_CASH, Purchase::PAYMENT_CHEQUE], true);
    $defaultPayOption = old('payment_option', 'full');
    $showPartialAmount = $showPayAccount && $defaultPayOption === 'partial';
    $currencyLabel = filled($currency ?? null) ? (string) $currency : '';
@endphp

@include('purchase::goods-receive.partials.grn-payment-styles')

<div class="pcat-field">
    <label for="grn-payment-method">Payment method</label>
    <select id="grn-payment-method" name="payment_method" required data-grn-payment-method data-can-pay-by-cheque="{{ $canPayByCheque ? '1' : '0' }}">
        @foreach($paymentMethods as $value => $label)
            <option value="{{ $value }}" @selected($defaultPaymentMethod === $value) @disabled($value === Purchase::PAYMENT_CHEQUE && !$canPayByCheque)>{{ $label }}</option>
        @endforeach
    </select>
    @error('payment_method')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @if(!$canPayByCheque)
        <p class="muted" style="margin:6px 0 0;font-size:11px;line-height:1.4;">
            Cheque requires a <strong style="color:var(--text);">current account</strong>.
            @if(Route::has('account.onboarding'))
                <a href="{{ route('account.onboarding') }}" class="pcat-link">Add one</a>
            @endif
        </p>
    @endif
</div>

<div id="grn-cheque-fields-wrap" style="{{ $showChequeRef ? '' : 'display:none;' }}">
<div class="pcat-field">
    <label for="grn-payment-reference">Cheque number</label>
    <input id="grn-payment-reference" type="text" name="payment_reference" value="{{ old('payment_reference') }}" maxlength="120" placeholder="e.g. 001245" @if($showChequeRef) required @endif data-grn-cheque-ref>
    @error('payment_reference')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field">
    <label for="grn-cheque-due-date">Cheque due date</label>
    <input id="grn-cheque-due-date" type="date" name="cheque_due_date" value="{{ old('cheque_due_date') }}" @if($showChequeRef) required @endif data-grn-cheque-due-date>
    @error('cheque_due_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
</div>

<div id="grn-pay-settlement-panel" class="grn-pay-settlement" style="{{ $showPayAccount ? '' : 'display:none;' }}" data-grn-pay-settlement>
    <div class="grn-pay-settlement__head">
        <div>
            <h4 class="grn-pay-settlement__title" data-grn-pay-settlement-title>Amount to pay now</h4>
            <p class="grn-pay-settlement__lead" data-grn-pay-settlement-lead data-lead-cash="Debit your account when this receipt is saved." data-lead-cheque="The account is not debited when you save. A cheque is created; deduct from account on the cheque page when it is presented.">Debit your account when this receipt is saved.</p>
        </div>
        <div class="grn-pay-settlement__total" aria-live="polite">
            <span class="grn-pay-settlement__total-label">Receipt total</span>
            <span class="grn-pay-settlement__total-value" data-grn-pay-total-display>—</span>
            @if($currencyLabel)
                <span class="muted" style="display:block;font-size:11px;margin-top:2px;">{{ $currencyLabel }}</span>
            @endif
        </div>
    </div>

    <fieldset class="grn-pay-choices" aria-label="How much to pay now">
        <label class="grn-pay-choices__card">
            <input type="radio" name="payment_option" value="full" data-grn-pay-option @checked($defaultPayOption !== 'partial')>
            <span>
                <span class="grn-pay-choices__title">Pay in full</span>
                <span class="grn-pay-choices__hint">Settle the entire receipt total in one posting.</span>
                <span class="grn-pay-choices__amount" data-grn-pay-full-amount>—</span>
            </span>
        </label>
        <label class="grn-pay-choices__card">
            <input type="radio" name="payment_option" value="partial" data-grn-pay-option @checked($defaultPayOption === 'partial')>
            <span>
                <span class="grn-pay-choices__title">Partial payment</span>
                <span class="grn-pay-choices__hint">Pay part now; add more from the GRN page later.</span>
            </span>
        </label>
    </fieldset>

    <div id="grn-pay-amount-wrap" class="grn-pay-partial-box" style="{{ $showPartialAmount ? '' : 'display:none;' }}">
        <div class="pcat-field" style="margin:0;">
            <label for="grn-pay-amount">Amount for this payment</label>
            <input id="grn-pay-amount" type="number" name="pay_amount" value="{{ old('pay_amount') }}" step="0.01" min="0.01" inputmode="decimal" placeholder="0.00" data-grn-pay-amount @if($showPartialAmount) required @endif>
            <p class="grn-pay-partial-cap" id="grn-pay-amount-cap" data-grn-pay-amount-cap></p>
            @error('pay_amount')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="pcat-field grn-pay-account-field">
        <label for="grn-deduct-account">Pay from account</label>
        @if($hasPaymentAccounts)
            <select id="grn-deduct-account" name="deduct_account_id" data-grn-deduct-account @if($showPayAccount) required @endif>
                <option value="">Select account…</option>
                @foreach($accounts as $accountRow)
                    <option value="{{ $accountRow->id }}" data-bank-type-slug="{{ $accountRow->bankType?->slug ?? '' }}" @selected((string) old('deduct_account_id') === (string) $accountRow->id)>{{ $accountRow->deductOptionLabel() }}</option>
                @endforeach
            </select>
        @else
            <p class="muted" style="margin:0;font-size:12px;">
                @if(Route::has('account.onboarding'))
                    <a href="{{ route('account.onboarding') }}" class="pcat-link">Add a bank account</a> to pay by cash or cheque.
                @else
                    Add a bank account for this business to pay by cash or cheque.
                @endif
            </p>
        @endif
        @error('deduct_account_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
</div>

<div id="grn-pay-credit-note" class="grn-pay-credit-note" style="{{ $showPayAccount ? 'display:none;' : '' }}">
    <i class="fa fa-circle-info" aria-hidden="true"></i>
    <strong style="color:var(--text);">Credit</strong> — nothing is debited on save. Record full or partial payments from the goods receive note after saving.
</div>

@once
<script>
(function () {
    if (window.__grnPaymentFieldsInit) return;
    window.__grnPaymentFieldsInit = true;

    function parseAmount(text) {
        var n = parseFloat(String(text || '').replace(/,/g, ''));
        return Number.isFinite(n) ? n : 0;
    }

    function formatMoney(amount) {
        if (amount <= 0) return '—';
        return amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function grnGrandTotal(form) {
        var el = form.querySelector('[data-grn-grand-total]');
        return el ? parseAmount(el.textContent) : 0;
    }

    function syncGrnPaymentFields(form) {
        var method = form.querySelector('[data-grn-payment-method]');
        var chequeWrap = form.querySelector('#grn-cheque-fields-wrap');
        var settlementPanel = form.querySelector('[data-grn-pay-settlement]');
        var creditNote = form.querySelector('#grn-pay-credit-note');
        var partialWrap = form.querySelector('#grn-pay-amount-wrap');
        var partialInput = form.querySelector('[data-grn-pay-amount]');
        var partialCap = form.querySelector('[data-grn-pay-amount-cap]');
        var totalDisplay = form.querySelector('[data-grn-pay-total-display]');
        var fullAmountEl = form.querySelector('[data-grn-pay-full-amount]');
        var ref = form.querySelector('[data-grn-cheque-ref]');
        var chequeDue = form.querySelector('[data-grn-cheque-due-date]');
        var accountSelect = form.querySelector('[data-grn-deduct-account]');
        if (!method) return;

        var canCheque = method.getAttribute('data-can-pay-by-cheque') === '1';
        if (!canCheque && method.value === 'cheque') {
            method.value = 'credit';
        }
        var isCheque = canCheque && method.value === 'cheque';
        var isCash = method.value === 'cash';
        var needsAccount = isCash || isCheque;
        var settlementLead = form.querySelector('[data-grn-pay-settlement-lead]');
        var settlementTitle = form.querySelector('[data-grn-pay-settlement-title]');

        if (settlementLead) {
            settlementLead.textContent = isCheque
                ? (settlementLead.getAttribute('data-lead-cheque') || settlementLead.textContent)
                : (settlementLead.getAttribute('data-lead-cash') || settlementLead.textContent);
        }
        if (settlementTitle) {
            settlementTitle.textContent = isCheque ? 'Cheque payment' : 'Amount to pay now';
        }

        if (chequeWrap) chequeWrap.style.display = isCheque ? '' : 'none';
        if (ref) {
            ref.required = isCheque;
            if (!isCheque) ref.value = '';
        }
        if (chequeDue) {
            chequeDue.required = isCheque;
            if (!isCheque) chequeDue.value = '';
        }
        if (settlementPanel) settlementPanel.style.display = needsAccount ? '' : 'none';
        if (creditNote) creditNote.style.display = needsAccount ? 'none' : '';

        if (accountSelect) {
            accountSelect.required = needsAccount;
            Array.prototype.forEach.call(accountSelect.options, function (opt) {
                if (!opt.value) return;
                var slug = opt.getAttribute('data-bank-type-slug') || '';
                if (isCheque) {
                    opt.hidden = slug !== 'current-account';
                    opt.disabled = slug !== 'current-account';
                } else {
                    opt.hidden = false;
                    opt.disabled = false;
                }
            });
            if (isCheque && accountSelect.selectedOptions[0] && accountSelect.selectedOptions[0].disabled) {
                accountSelect.selectedIndex = 0;
            }
            if (!needsAccount) accountSelect.selectedIndex = 0;
        }

        var total = grnGrandTotal(form);
        var totalText = formatMoney(total);
        if (totalDisplay) {
            totalDisplay.textContent = totalText;
            totalDisplay.classList.toggle('grn-pay-settlement__total-value--muted', total <= 0);
        }
        if (fullAmountEl) {
            fullAmountEl.textContent = total > 0 ? totalText : 'Enter quantities above';
        }

        var isPartial = needsAccount && form.querySelector('[data-grn-pay-option][value="partial"]')?.checked;
        if (partialWrap) partialWrap.style.display = isPartial ? '' : 'none';
        if (partialInput) partialInput.required = !!isPartial;
        if (partialCap && isPartial) {
            partialCap.textContent = total > 0
                ? 'Maximum for this receipt: ' + totalText + '.'
                : 'Enter line quantities above to set the receipt total.';
        }
        if (partialInput && total > 0) {
            partialInput.max = String(total);
        }
    }

    document.querySelectorAll('[data-grn-receive-form]').forEach(function (form) {
        form.querySelector('[data-grn-payment-method]')?.addEventListener('change', function () {
            syncGrnPaymentFields(form);
        });
        form.querySelectorAll('[data-grn-pay-option]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                syncGrnPaymentFields(form);
            });
        });
        form.addEventListener('input', function (e) {
            if (e.target.matches('[data-grn-qty]')) syncGrnPaymentFields(form);
        });
        syncGrnPaymentFields(form);
    });
})();
</script>
@endonce
