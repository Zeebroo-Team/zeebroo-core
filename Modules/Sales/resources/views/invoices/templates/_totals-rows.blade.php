@php
    $money = static fn ($n) => number_format((float) $n, 2).(filled($currency ?? null) ? ' '.$currency : '');

    $rawSub = 0.0; $plDiscTot = 0.0; $plTaxTot = 0.0;
    foreach ($doc['items'] as $item) {
        $lineGross = $item['qty'] * $item['unitPrice'];
        $discAmt = $item['discountType'] === 'flat'
            ? min($item['discountValue'], $lineGross)
            : ($lineGross * $item['discountValue'] / 100);
        $netAfterDisc = max(0, $lineGross - $discAmt);
        $taxAmt = $item['taxType'] === 'flat'
            ? $item['taxValue']
            : ($netAfterDisc * $item['taxValue'] / 100);
        $rawSub += $lineGross;
        $plDiscTot += $discAmt;
        $plTaxTot += $taxAmt;
    }
    $hasPlAdj = $plDiscTot > 0.001 || $plTaxTot > 0.001;
@endphp
@if($hasPlAdj)
    <div class="tr"><span>Gross Subtotal</span><span>{{ $money($rawSub) }}</span></div>
    @if($plDiscTot > 0.001)
        <div class="tr"><span>Item Discounts</span><span style="color:#ef4444">−{{ $money($plDiscTot) }}</span></div>
    @endif
    @if($plTaxTot > 0.001)
        <div class="tr"><span>Item Taxes</span><span style="color:#10b981">+{{ $money($plTaxTot) }}</span></div>
    @endif
    @if($doc['discountAmount'] > 0 || $doc['taxAmount'] > 0)
        <div class="tr" style="font-weight:700"><span>Line Subtotal</span><span>{{ $money($doc['subtotal']) }}</span></div>
    @endif
@else
    <div class="tr"><span>Subtotal</span><span>{{ $money($doc['subtotal']) }}</span></div>
@endif
@if($doc['discountAmount'] > 0)
    <div class="tr"><span>Discount</span><span style="color:#ef4444">−{{ $money($doc['discountAmount']) }}</span></div>
@endif
@if($doc['taxAmount'] > 0)
    <div class="tr"><span>Tax</span><span style="color:#10b981">+{{ $money($doc['taxAmount']) }}</span></div>
@endif
