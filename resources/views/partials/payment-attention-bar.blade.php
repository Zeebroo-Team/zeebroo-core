{{-- Horizontal subscription-payment alert, styled to match the layout's .sub-due-bar. --}}
@once
    <style>
        .pay-attn-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 14px;padding:12px 16px;border-radius:14px;background:color-mix(in srgb,#ef4444 10%,var(--card));border:1px solid color-mix(in srgb,#ef4444 45%,var(--border));color:var(--text);font-size:13.5px;text-align:left;}
        .pay-attn-bar__icon{width:30px;height:30px;border-radius:999px;background:#ef4444;color:#fff;display:grid;place-items:center;font-weight:700;flex-shrink:0;}
        .pay-attn-bar__text{flex:1;min-width:220px;line-height:1.45;}
        .pay-attn-bar__text b{color:#b91c1c;}
        html[data-theme="night"] .pay-attn-bar__text b,html[data-theme="night_blue"] .pay-attn-bar__text b,html[data-theme="ocean"] .pay-attn-bar__text b{color:#f87171;}
        .pay-attn-bar__actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
        .pay-attn-bar__actions form{margin:0;}
        .pay-attn-bar__pay{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:9px;border:0;background:#ef4444;color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;}
        .pay-attn-bar__pay:hover{background:#dc2626;}
        .pay-attn-bar__history{display:inline-flex;align-items:center;padding:7px 14px;border-radius:9px;border:1.5px solid color-mix(in srgb,#ef4444 45%,var(--border));color:#b91c1c;font-weight:700;font-size:13px;text-decoration:none;}
        .pay-attn-bar__history:hover{background:color-mix(in srgb,#ef4444 8%,transparent);}
    </style>
@endonce
<div class="pay-attn-bar" role="alert">
    <span class="pay-attn-bar__icon" aria-hidden="true">!</span>
    <div class="pay-attn-bar__text">
        <b>{{ $title }}</b>@if(! empty($message)) — {{ $message }}@endif
    </div>
    @if($payment)
        <div class="pay-attn-bar__actions">
            <form method="post" action="{{ route('payment.checkout.resume', $payment) }}">
                @csrf
                <button type="submit" class="pay-attn-bar__pay">
                    <i class="fa fa-credit-card" aria-hidden="true"></i>
                    Complete payment ({{ $payment->currencySymbol() }}{{ number_format((float) $payment->amount, 2) }}/mo)
                </button>
            </form>
            <a href="{{ route('payment.billing.index') }}" class="pay-attn-bar__history">View payment history</a>
        </div>
    @endif
</div>
