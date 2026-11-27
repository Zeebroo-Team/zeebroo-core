{{-- "Request withdrawal" fields, shared by the inline first-request form and the modal.
     Expects: $idPrefix, $balance, $currency, $minAmount, $profile --}}
<div class="aff-field">
    <label for="{{ $idPrefix }}-amount">Amount <span class="aff-req">*</span></label>
    <div class="aff-amount">
        <span>{{ $currency }}</span>
        <input type="number" id="{{ $idPrefix }}-amount" name="amount" step="0.01" min="{{ $minAmount }}" max="{{ $balance['available'] }}"
               value="{{ old('amount', number_format($balance['available'], 2, '.', '')) }}" required inputmode="decimal"
               class="@error('amount') is-invalid @enderror" data-aff-amount>
    </div>
    <div class="aff-amount-hint aff-field-hint">
        <span>Available: {{ $currency }} {{ number_format($balance['available'], 2) }} · Minimum {{ $currency }} {{ number_format($minAmount, 2) }}</span>
        <button type="button" class="aff-linkbtn" data-aff-withdraw-all="{{ number_format($balance['available'], 2, '.', '') }}">Withdraw all</button>
    </div>
    @error('amount')<p class="aff-field-err">{{ $message }}</p>@enderror
</div>

<div class="aff-field">
    <label for="{{ $idPrefix }}-note">Note for the admin <span class="aff-optional">(optional)</span></label>
    <textarea id="{{ $idPrefix }}-note" name="note" maxlength="500" rows="2" placeholder="Anything we should know about this payout" class="@error('note') is-invalid @enderror">{{ old('note') }}</textarea>
    @error('note')<p class="aff-field-err">{{ $message }}</p>@enderror
</div>

<div class="aff-pay-to">
    <div class="aff-pay-to-head">
        <span><i class="fa fa-building-columns" aria-hidden="true"></i> Paid to</span>
        <a href="{{ route('affiliate.settings', ['tab' => 'payout']) }}">Change</a>
    </div>
    <div><strong>{{ $profile->account_holder_name }}</strong></div>
    <div>{{ $profile->bank_name }}, {{ $profile->bank_branch }} · <span class="aff-mono">{{ $profile->maskedAccountNumber() }}</span></div>
</div>
