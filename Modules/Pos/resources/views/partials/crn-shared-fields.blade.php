{{--
    Shared: refund method, credit account, notes, total bar, footer
    Variables: $formId (string), $cancelUrl (string), $accounts (Collection)
--}}
<div class="crn-total-bar">
    <span class="crn-total-bar__label">Return total</span>
    <span>
        <span class="crn-total-bar__value" id="crnRunningTotal">0.00</span>
        @if(filled($currency ?? ''))<span class="crn-total-bar__currency">{{ $currency }}</span>@endif
    </span>
</div>

<div class="crn-fields">
    <div class="crn-field">
        <label for="crnRefundMethod-{{ $formId }}">Refund method</label>
        <select name="refund_method" id="crnRefundMethod-{{ $formId }}" class="crn-refund-sel" data-credit-target="crnCreditField-{{ $formId }}">
            <option value="cash"   @selected(old('refund_method', 'cash') === 'cash')>Cash refund</option>
            <option value="credit" @selected(old('refund_method') === 'credit')>Credit account</option>
            <option value="none"   @selected(old('refund_method') === 'none')>No refund (exchange / store credit)</option>
        </select>
    </div>

    <div class="crn-field">
        <label for="crnRefundReason-{{ $formId }}">Return reason</label>
        <select name="refund_reason" id="crnRefundReason-{{ $formId }}">
            <option value="">— Select reason —</option>
            @foreach(\Modules\Pos\Models\SaleReturn::REASONS as $key => $label)
                <option value="{{ $key }}" @selected(old('refund_reason') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="crn-field crn-credit-field" id="crnCreditField-{{ $formId }}">
        <label for="crnCreditAccount-{{ $formId }}">Credit account</label>
        <select name="credit_account_id" id="crnCreditAccount-{{ $formId }}">
            <option value="">— Select account —</option>
            @foreach($accounts as $account)
                <option value="{{ $account->id }}" @selected(old('credit_account_id') == $account->id)>
                    {{ $account->deductOptionLabel() }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="crn-field crn-field--full">
        <label for="crnNotes-{{ $formId }}">Notes (optional)</label>
        <textarea name="notes" id="crnNotes-{{ $formId }}" placeholder="Reason for return, customer reference, condition of returned items…">{{ old('notes') }}</textarea>
    </div>
</div>

<div class="crn-footer">
    <a href="{{ $cancelUrl }}" class="crn-btn"><i class="fa fa-arrow-left"></i> Cancel</a>
    <button type="submit" class="crn-btn crn-btn--primary" id="crnSubmitBtn-{{ $formId }}">
        <i class="fa fa-rotate-left"></i> Process return
    </button>
</div>

<script>
(function () {
    var sel = document.getElementById('crnRefundMethod-{{ $formId }}');
    var creditField = document.getElementById('crnCreditField-{{ $formId }}');
    if (sel && creditField) {
        function syncCredit() {
            creditField.style.display = sel.value === 'credit' ? 'block' : 'none';
        }
        sel.addEventListener('change', syncCredit);
        syncCredit();
    }

    var form = document.getElementById('{{ $formId }}');
    var submitBtn = document.getElementById('crnSubmitBtn-{{ $formId }}');
    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing…';
        });
    }
})();
</script>
