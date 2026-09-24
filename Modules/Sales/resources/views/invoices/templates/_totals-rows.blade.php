@php
    $money = static fn ($n) => number_format((float) $n, 2).(filled($currency ?? null) ? ' '.$currency : '');
@endphp
<div class="tr"><span>Subtotal</span><span>{{ $money($doc['subtotal']) }}</span></div>
@if($doc['discountAmount'] > 0)
    <div class="tr"><span>Discount</span><span style="color:#ef4444">−{{ $money($doc['discountAmount']) }}</span></div>
@endif
@if($doc['taxAmount'] > 0)
    <div class="tr"><span>Tax</span><span style="color:#10b981">+{{ $money($doc['taxAmount']) }}</span></div>
@endif
