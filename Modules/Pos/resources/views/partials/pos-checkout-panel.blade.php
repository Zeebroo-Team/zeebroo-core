@php
    $defaultDepositAccountId = $defaultDepositAccountId ?? null;
    $currencyLabel           = filled($currency ?? null) ? ' '.$currency : '';
    $discountFieldEnabled    = (bool) ($discountFieldEnabled ?? false);
    $checkoutModalEnabled    = (bool) ($checkoutModalEnabled ?? false);
@endphp

<form method="post" action="{{ $checkoutFormAction ?? route('pos.checkout') }}" id="pos-checkout-form" class="pos-checkout-form">
    @csrf
    <input type="hidden" name="channel" value="{{ $channel ?? 'online' }}">
    <div class="pos-checkout-form__scroll">
        <div class="pos-online__checkout">

            @if($discountFieldEnabled && $checkoutModalEnabled)
            <div class="pco-discount-section">
                <p class="pco-discount-section__head">
                    <i class="fa fa-tag" aria-hidden="true"></i> Discount
                </p>
                <div class="pco-discount-section__body">
                    <div class="pco-discount-section__input-row">
                        <span class="pco-discount-section__pct">%</span>
                        <input type="text" name="discount_percent" id="pos-discount-percent"
                               class="pco-discount-section__input"
                               value="{{ old('discount_percent', '0') }}"
                               inputmode="none" data-pos-numpad="percent" readonly
                               placeholder="0">
                        <span class="pco-discount-section__hint">Tap numpad to set</span>
                    </div>
                    <div class="pco-discount-section__summary" id="pco-discount-summary">
                        <div class="pco-discount-section__sum-row">
                            <span>Subtotal</span>
                            <strong id="pco-subtotal-display">—</strong>
                        </div>
                        <div class="pco-discount-section__sum-row pco-discount-section__sum-row--disc" id="pco-discount-amount-row" hidden>
                            <span>Discount</span>
                            <strong id="pco-discount-amount">—</strong>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @include('pos::partials.pos-payment-field', ['defaultDepositAccountId' => $defaultDepositAccountId])
            <div class="pos-online__field">
                <label for="pos-notes">Notes</label>
                <textarea name="notes" id="pos-notes" maxlength="2000" placeholder="Optional">{{ old('notes') }}</textarea>
            </div>
        </div>
    </div>
    <div class="pos-checkout-form__footer">
        @include('pos::partials.pos-numpad')
        <button type="submit" class="pos-online__pay-btn" id="pos-complete-sale" disabled>Complete sale</button>
    </div>
</form>
