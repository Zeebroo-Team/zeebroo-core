@php
    $discountFieldEnabled  = (bool) ($discountFieldEnabled ?? false);
    $checkoutModalEnabled  = (bool) ($checkoutModalEnabled ?? false);
    $currency = $currency ?? '';
@endphp

@once
<style>
.pos-catalog-totals-bar{flex-shrink:0;border-top:1px solid var(--border);padding:10px 12px;background:color-mix(in srgb,var(--card) 98%,transparent);display:grid;gap:6px;}
.pos-catalog-totals{display:grid;gap:6px;}
.pos-catalog-totals[hidden]{display:none!important;}
.pos-catalog-totals__row{display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:13px;color:var(--muted);}
.pos-catalog-totals__row strong,.pos-catalog-totals__row span:last-child{color:var(--text);font-weight:700;}
.pos-catalog-totals__row--grand{font-size:17px;font-weight:800;color:var(--text);padding-top:4px;border-top:1px solid var(--border);margin-top:2px;}
.pos-catalog-totals__row--grand span,.pos-catalog-totals__row--grand strong{font-size:17px;font-weight:800;}
.pos-catalog-totals__row input[name="discount_percent"]{width:72px;padding:6px 8px;font-size:12px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);text-align:right;cursor:pointer;box-sizing:border-box;}
body.pos-walking-active .pos-catalog-totals-bar{padding:8px 10px;}
body.pos-walking-active .pos-catalog-totals__row{font-size:12px;}
body.pos-walking-active .pos-catalog-totals__row--grand{font-size:15px;}
</style>
@endonce

<div class="pos-catalog-totals-bar" aria-label="Sale totals">
    <div id="pos-cart-summary" class="pos-catalog-totals" hidden>
        <div class="pos-catalog-totals__row">
            <span>Subtotal</span>
            <strong id="pos-cart-subtotal">0.00</strong>
        </div>
        @if($discountFieldEnabled && !$checkoutModalEnabled)
        <div class="pos-catalog-totals__row" id="pos-discount-row">
            <span>Discount (%)</span>
            <input type="text" name="discount_percent" id="pos-discount-percent" form="pos-checkout-form" value="{{ old('discount_percent', '0') }}" inputmode="none" data-pos-numpad="percent" readonly>
        </div>
        @endif
        <div class="pos-catalog-totals__row" id="pos-discount-amount-row" hidden>
            <span>Discount amount</span>
            <strong id="pos-cart-discount">0.00</strong>
        </div>
    </div>
    <div class="pos-catalog-totals__row pos-catalog-totals__row--grand">
        <span>Total</span>
        <strong id="pos-cart-total">0.00{{ filled($currency) ? ' '.$currency : '' }}</strong>
    </div>
    @if($checkoutModalEnabled)
    <button type="button" id="pos-open-checkout-modal" class="pco-trigger" disabled>
        <i class="fa fa-arrow-right" aria-hidden="true"></i> Proceed to checkout
    </button>
    @endif
</div>
