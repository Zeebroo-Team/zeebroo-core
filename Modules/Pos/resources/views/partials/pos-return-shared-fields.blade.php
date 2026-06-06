{{--
    Variables: $formId (string), $currency (string), $accounts (Collection)
--}}
<div style="display:flex;flex-direction:column;gap:12px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border);">

    <div class="pos-ret-total">
        <span class="pos-ret-total__label">Return total</span>
        <span>
            <span class="pos-ret-total__value" id="{{ $formId }}Total">0.00</span>
            @if(filled($currency))<span style="font-size:13px;font-weight:600;color:var(--muted);margin-left:4px;">{{ $currency }}</span>@endif
        </span>
    </div>

    <div class="pos-ret-shared">
        <div class="pos-ret-field">
            <label for="{{ $formId }}RefundSel">Refund method</label>
            <select name="refund_method" id="{{ $formId }}RefundSel">
                <option value="cash">Cash refund</option>
                <option value="credit">Credit account</option>
                <option value="none">No refund (exchange / store credit)</option>
            </select>
        </div>

        <div class="pos-ret-field">
            <label for="{{ $formId }}ReasonSel">Return reason</label>
            <select name="refund_reason" id="{{ $formId }}ReasonSel">
                <option value="">— Select reason —</option>
                @foreach(\Modules\Pos\Models\SaleReturn::REASONS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="pos-ret-field pos-ret-credit-field" id="{{ $formId }}CreditField" style="display:none;">
            <label for="{{ $formId }}CreditSel">Credit account</label>
            <select name="credit_account_id" id="{{ $formId }}CreditSel">
                <option value="">— Select account —</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->deductOptionLabel() }}</option>
                @endforeach
            </select>
        </div>

        <div class="pos-ret-field pos-ret-field--full">
            <label for="{{ $formId }}Notes">Notes (optional)</label>
            <textarea name="notes" id="{{ $formId }}Notes"
                placeholder="Reason for return, customer reference, item condition…"></textarea>
        </div>
    </div>
</div>
