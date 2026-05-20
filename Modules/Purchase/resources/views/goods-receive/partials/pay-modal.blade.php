@php
    use Modules\Purchase\Models\Purchase;

    $hasPaymentAccounts = $hasPaymentAccounts ?? false;
    $canPayByCheque = filter_var($canPayByCheque ?? false, FILTER_VALIDATE_BOOLEAN);
    $openPayGrnId = (int) ($openPayGrnId ?? 0);
    $recordPayOption = old('payment_option', 'full');
    $showRecordPartial = $recordPayOption === 'partial';
    $defaultPaymentMethod = old('payment_method', Purchase::PAYMENT_CASH);
    if (! $canPayByCheque && $defaultPaymentMethod === Purchase::PAYMENT_CHEQUE) {
        $defaultPaymentMethod = Purchase::PAYMENT_CASH;
    }
    $showChequeRef = $canPayByCheque && $defaultPaymentMethod === Purchase::PAYMENT_CHEQUE;
    $paymentMethods = Purchase::paymentMethods();
@endphp
@if($hasPaymentAccounts)
    <div
        id="grn-pay-modal"
        class="pcat-modal grn-pay-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="grn-pay-modal-title"
        aria-hidden="true"
        data-grn-pay-modal
        data-can-pay-by-cheque="{{ $canPayByCheque ? '1' : '0' }}"
    >
        <div class="pcat-modal__backdrop" data-grn-pay-close tabindex="-1"></div>
        <div class="pcat-modal__panel grn-pay-modal__panel">
            <div class="pcat-modal__head">
                <h2 id="grn-pay-modal-title">Make payment</h2>
                <button type="button" class="pcat-modal__close" data-grn-pay-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <p class="muted grn-pay-modal__lead" data-grn-pay-lead style="margin:0 0 12px;font-size:12px;line-height:1.45;"></p>

                <dl class="grn-pay-modal__summary" data-grn-pay-summary>
                    <div>
                        <dt>GRN total</dt>
                        <dd data-grn-pay-summary-total>—</dd>
                    </div>
                    <div>
                        <dt>Paid</dt>
                        <dd data-grn-pay-summary-paid>—</dd>
                    </div>
                    <div>
                        <dt>Outstanding</dt>
                        <dd data-grn-pay-summary-outstanding style="color:var(--text);">—</dd>
                    </div>
                </dl>

                <form method="post" action="" class="grn-record-payment-panel" data-grn-record-payment data-grn-pay-form>
                    @csrf
                    <input type="hidden" name="return_to" value="" data-grn-pay-return-to>
                    <input type="hidden" name="return_view" value="" data-grn-pay-return-view>

                    <div class="pcat-field" style="margin-bottom:12px;">
                        <label for="grn-pay-modal-method">Payment method</label>
                        <select id="grn-pay-modal-method" name="payment_method" required data-grn-pay-modal-method>
                            <option value="{{ Purchase::PAYMENT_CASH }}" @selected($defaultPaymentMethod === Purchase::PAYMENT_CASH)>{{ $paymentMethods[Purchase::PAYMENT_CASH] }}</option>
                            <option value="{{ Purchase::PAYMENT_CHEQUE }}" @selected($defaultPaymentMethod === Purchase::PAYMENT_CHEQUE) @disabled(! $canPayByCheque)>{{ $paymentMethods[Purchase::PAYMENT_CHEQUE] }}</option>
                        </select>
                        @error('payment_method')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                        @if(! $canPayByCheque)
                            <p class="muted" style="margin:6px 0 0;font-size:11px;line-height:1.4;">
                                Cheque requires a <strong style="color:var(--text);">current account</strong>.
                                @if(Route::has('account.onboarding'))
                                    <a href="{{ route('account.onboarding') }}" class="pcat-link">Add one</a>
                                @endif
                            </p>
                        @endif
                    </div>

                    <div id="grn-pay-modal-cheque-wrap" style="margin-bottom:12px;{{ $showChequeRef ? '' : 'display:none;' }}">
                        <div class="pcat-field">
                            <label for="grn-pay-modal-cheque-ref">Cheque number</label>
                            <input
                                id="grn-pay-modal-cheque-ref"
                                type="text"
                                name="payment_reference"
                                value="{{ old('payment_reference') }}"
                                maxlength="120"
                                placeholder="e.g. 001245"
                                data-grn-pay-modal-cheque-ref
                                @if($showChequeRef) required @endif
                            >
                            @error('payment_reference')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                        </div>
                        <div class="pcat-field">
                            <label for="grn-pay-modal-cheque-due">Cheque due date</label>
                            <input
                                id="grn-pay-modal-cheque-due"
                                type="date"
                                name="cheque_due_date"
                                value="{{ old('cheque_due_date') }}"
                                data-grn-pay-modal-cheque-due
                                @if($showChequeRef) required @endif
                            >
                            @error('cheque_due_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grn-pay-settlement">
                        <div class="grn-pay-settlement__head">
                            <div>
                                <h4 class="grn-pay-settlement__title">Record payment</h4>
                                <p class="grn-pay-settlement__lead">Pay the outstanding balance in full or post a partial amount.</p>
                            </div>
                            <div class="grn-pay-settlement__total">
                                <span class="grn-pay-settlement__total-label">Outstanding</span>
                                <span class="grn-pay-settlement__total-value" data-grn-pay-outstanding-display>0.00</span>
                                <span class="muted grn-pay-settlement__currency" data-grn-pay-currency-display style="display:block;font-size:11px;margin-top:2px;"></span>
                            </div>
                        </div>

                        <fieldset class="grn-pay-choices" aria-label="Payment amount">
                            <label class="grn-pay-choices__card">
                                <input type="radio" name="payment_option" value="full" data-grn-record-pay-option @checked($recordPayOption !== 'partial')>
                                <span>
                                    <span class="grn-pay-choices__title">Pay in full</span>
                                    <span class="grn-pay-choices__hint">Clear the remaining balance in one posting.</span>
                                    <span class="grn-pay-choices__amount" data-grn-pay-full-amount>0.00</span>
                                </span>
                            </label>
                            <label class="grn-pay-choices__card">
                                <input type="radio" name="payment_option" value="partial" data-grn-record-pay-option @checked($showRecordPartial)>
                                <span>
                                    <span class="grn-pay-choices__title">Partial payment</span>
                                    <span class="grn-pay-choices__hint">Pay less now; record another payment later.</span>
                                </span>
                            </label>
                        </fieldset>

                        <div id="grn-pay-modal-amount-wrap" class="grn-pay-partial-box" style="{{ $showRecordPartial ? '' : 'display:none;' }}">
                            <div class="pcat-field" style="margin:0;">
                                <label for="grn-pay-modal-amount">Amount for this payment</label>
                                <input
                                    id="grn-pay-modal-amount"
                                    type="number"
                                    name="pay_amount"
                                    value="{{ old('pay_amount') }}"
                                    step="0.01"
                                    min="0.01"
                                    inputmode="decimal"
                                    placeholder="0.00"
                                    data-grn-record-pay-amount
                                    data-grn-pay-amount-input
                                    @if($showRecordPartial) required @endif
                                >
                                <p class="grn-pay-partial-cap" data-grn-pay-max-hint>Maximum for this receipt.</p>
                                @error('pay_amount')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="pcat-field grn-pay-account-field">
                            <label for="grn-pay-modal-account">Pay from account</label>
                            <select id="grn-pay-modal-account" name="deduct_account_id" required data-grn-pay-account-select>
                                <option value="">Select account…</option>
                                @foreach($accounts as $accountRow)
                                    <option
                                        value="{{ $accountRow->id }}"
                                        data-bank-slug="{{ $accountRow->bankType?->slug ?? '' }}"
                                        @selected((string) old('deduct_account_id') === (string) $accountRow->id)
                                    >{{ $accountRow->deductOptionLabel() }}</option>
                                @endforeach
                            </select>
                            @error('deduct_account_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <button type="submit" class="linkbtn grn-pay-submit" style="padding:10px 18px;font-size:13px;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fa fa-circle-check" aria-hidden="true"></i> Confirm payment
                    </button>
                </form>
            </div>
        </div>
    </div>

    @once
    <script>
    (function () {
        if (window.__grnPayModalInit) return;
        window.__grnPayModalInit = true;

        var modal = document.querySelector('[data-grn-pay-modal]');
        if (!modal) return;

        var form = modal.querySelector('[data-grn-pay-form]');
        var lead = modal.querySelector('[data-grn-pay-lead]');
        var outstandingDisplay = modal.querySelector('[data-grn-pay-outstanding-display]');
        var currencyDisplay = modal.querySelector('[data-grn-pay-currency-display]');
        var fullAmountEl = modal.querySelector('[data-grn-pay-full-amount]');
        var maxHint = modal.querySelector('[data-grn-pay-max-hint]');
        var amountInput = modal.querySelector('[data-grn-pay-amount-input]');
        var partialWrap = modal.querySelector('#grn-pay-modal-amount-wrap');
        var accountSelect = modal.querySelector('[data-grn-pay-account-select]');
        var returnToInput = modal.querySelector('[data-grn-pay-return-to]');
        var returnViewInput = modal.querySelector('[data-grn-pay-return-view]');
        var summaryTotal = modal.querySelector('[data-grn-pay-summary-total]');
        var summaryPaid = modal.querySelector('[data-grn-pay-summary-paid]');
        var summaryOutstanding = modal.querySelector('[data-grn-pay-summary-outstanding]');
        var methodSelect = modal.querySelector('[data-grn-pay-modal-method]');
        var chequeWrap = modal.querySelector('#grn-pay-modal-cheque-wrap');
        var chequeRef = modal.querySelector('[data-grn-pay-modal-cheque-ref]');
        var chequeDue = modal.querySelector('[data-grn-pay-modal-cheque-due]');
        var canPayByCheque = modal.getAttribute('data-can-pay-by-cheque') === '1';
        var activeBtn = null;
        var currentOutstanding = 0;

        function lock(on) {
            document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on));
        }

        function formatMoney(n) {
            return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function filterAccounts(paymentMethod) {
            if (!accountSelect) return;
            var isCheque = paymentMethod === @json(Purchase::PAYMENT_CHEQUE);
            Array.prototype.forEach.call(accountSelect.options, function (opt, idx) {
                if (idx === 0) {
                    opt.hidden = false;
                    return;
                }
                var slug = opt.getAttribute('data-bank-slug') || '';
                var hide = isCheque && slug !== 'current-account';
                opt.hidden = hide;
                if (hide && opt.selected) accountSelect.value = '';
            });
        }

        function syncPartialFields() {
            var isPartial = form && form.querySelector('[data-grn-record-pay-option][value="partial"]')?.checked;
            if (partialWrap) partialWrap.style.display = isPartial ? '' : 'none';
            if (amountInput) {
                amountInput.required = !!isPartial;
                amountInput.max = currentOutstanding > 0 ? String(currentOutstanding) : '';
            }
        }

        function syncPaymentMethod() {
            var method = methodSelect?.value || @json(Purchase::PAYMENT_CASH);
            if (!canPayByCheque && method === @json(Purchase::PAYMENT_CHEQUE)) {
                method = @json(Purchase::PAYMENT_CASH);
                if (methodSelect) methodSelect.value = method;
            }
            var isCheque = method === @json(Purchase::PAYMENT_CHEQUE);
            if (chequeWrap) chequeWrap.style.display = isCheque ? '' : 'none';
            if (chequeRef) {
                chequeRef.required = isCheque;
                if (!isCheque) chequeRef.value = '';
            }
            if (chequeDue) {
                chequeDue.required = isCheque;
                if (!isCheque) chequeDue.value = '';
            }
            filterAccounts(method);
        }

        function openFromButton(btn) {
            if (!btn || !form) return;
            activeBtn = btn;
            currentOutstanding = parseFloat(btn.getAttribute('data-grn-outstanding') || '0') || 0;
            var currency = btn.getAttribute('data-grn-currency') || '';
            var grnMethod = btn.getAttribute('data-grn-payment-method') || @json(Purchase::PAYMENT_CASH);
            if (grnMethod === @json(Purchase::PAYMENT_CREDIT) || grnMethod === '') {
                grnMethod = @json(Purchase::PAYMENT_CASH);
            }
            if (grnMethod === @json(Purchase::PAYMENT_CHEQUE) && !canPayByCheque) {
                grnMethod = @json(Purchase::PAYMENT_CASH);
            }
            if (methodSelect) methodSelect.value = grnMethod;
            var grnChequeDue = btn.getAttribute('data-grn-cheque-due-date') || '';
            if (chequeDue && grnChequeDue) chequeDue.value = grnChequeDue;

            form.action = btn.getAttribute('data-grn-pay-url') || '';
            if (returnToInput) returnToInput.value = btn.getAttribute('data-grn-return-to') || 'index';
            if (returnViewInput) returnViewInput.value = btn.getAttribute('data-grn-return-view') || 'grouped';

            var grnNumber = btn.getAttribute('data-grn-number') || '';
            var po = btn.getAttribute('data-grn-po') || '';
            var supplier = btn.getAttribute('data-grn-supplier') || '';
            var parts = [grnNumber];
            if (po) parts.push('PO ' + po);
            if (supplier) parts.push(supplier);
            if (lead) lead.textContent = parts.join(' · ');

            var total = btn.getAttribute('data-grn-total') || '0';
            var paid = btn.getAttribute('data-grn-paid') || '0';
            var outstanding = btn.getAttribute('data-grn-outstanding') || '0';

            if (summaryTotal) summaryTotal.textContent = formatMoney(total) + (currency ? ' ' + currency : '');
            if (summaryPaid) summaryPaid.textContent = formatMoney(paid);
            if (summaryOutstanding) summaryOutstanding.textContent = formatMoney(outstanding) + (currency ? ' ' + currency : '');
            if (outstandingDisplay) outstandingDisplay.textContent = formatMoney(outstanding);
            if (currencyDisplay) {
                currencyDisplay.textContent = currency;
                currencyDisplay.style.display = currency ? '' : 'none';
            }
            if (fullAmountEl) fullAmountEl.textContent = formatMoney(outstanding) + (currency ? ' ' + currency : '');
            if (maxHint) maxHint.textContent = 'Maximum ' + formatMoney(outstanding) + (currency ? ' ' + currency : '') + ' for this receipt.';

            syncPaymentMethod();
            syncPartialFields();

            modal.classList.add('pcat-modal--open');
            modal.setAttribute('aria-hidden', 'false');
            lock(true);
            methodSelect?.focus();
        }

        function closeModal() {
            modal.classList.remove('pcat-modal--open');
            modal.setAttribute('aria-hidden', 'true');
            lock(false);
            activeBtn?.focus();
            activeBtn = null;
        }

        document.addEventListener('click', function (e) {
            var openBtn = e.target.closest('[data-grn-pay-open]');
            if (openBtn) {
                e.preventDefault();
                openFromButton(openBtn);
                return;
            }
            if (e.target.closest('[data-grn-pay-close]')) {
                e.preventDefault();
                closeModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('pcat-modal--open')) closeModal();
        });

        methodSelect?.addEventListener('change', syncPaymentMethod);
        form?.querySelectorAll('[data-grn-record-pay-option]').forEach(function (r) {
            r.addEventListener('change', syncPartialFields);
        });
        syncPaymentMethod();
        syncPartialFields();

        var openId = @json($openPayGrnId > 0 ? $openPayGrnId : null);
        if (openId) {
            var autoBtn = document.querySelector('[data-grn-pay-open][data-grn-id="' + openId + '"]');
            if (autoBtn) openFromButton(autoBtn);
        }
    })();
    </script>
    @endonce
@endif
