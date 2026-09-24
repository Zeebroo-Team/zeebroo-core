@php
    $italic = $italic ?? false;
    $money = static fn ($n) => number_format((float) $n, 2).(filled($currency ?? null) ? ' '.$currency : '');
    $fmtQty = static fn ($n) => fmod((float) $n, 1.0) === 0.0 ? (string) (int) $n : rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
    $fmtPct = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
@foreach($doc['items'] as $item)
    @php
        $lineGross = $item['qty'] * $item['unitPrice'];
        $discAmt = $item['discountType'] === 'flat'
            ? min($item['discountValue'], $lineGross)
            : ($lineGross * $item['discountValue'] / 100);
    @endphp
    <tr>
        <td class="n">{!! $italic ? '<i>'.$item['n'].'</i>' : $item['n'] !!}</td>
        <td>
            <b>{{ $item['description'] }}</b>
            @if($item['isService'])
                <span style="font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;background:#ede9fe;color:#7c3aed;margin-left:5px;">Service</span>
            @endif
            @if($item['sublabel'])
                <span class="ds">{{ $item['sublabel'] }}</span>
            @endif
        </td>
        <td class="r">{{ $fmtQty($item['qty']) }}</td>
        <td class="r">{{ $money($item['unitPrice']) }}</td>
        <td class="r" style="{{ $item['discountValue'] > 0 ? 'color:#ef4444;font-size:10px' : 'color:#94a3b8' }}">
            @if($item['discountValue'] > 0)
                {{ $item['discountType'] === 'flat' ? '−'.$money($discAmt) : $fmtPct($item['discountValue']).'%' }}
            @else
                —
            @endif
        </td>
        <td class="r" style="{{ $item['taxValue'] > 0 ? 'color:#10b981;font-size:10px' : 'color:#94a3b8' }}">
            @if($item['taxValue'] > 0)
                {{ $item['taxType'] === 'flat' ? $money($item['taxValue']) : $fmtPct($item['taxValue']).'%' }}
            @else
                —
            @endif
        </td>
        <td class="r b">{{ $money($item['lineTotal']) }}</td>
    </tr>
    @foreach($item['boundProducts'] as $bp)
        <tr style="background:#f5f3ff;">
            <td></td>
            <td style="padding-left:24px;color:#7c3aed;font-size:10px;">
                &#8627; {{ $bp['name'] }}@if($bp['sku']) ({{ $bp['sku'] }})@endif
            </td>
            <td class="r" style="color:#7c3aed;font-size:10px;">{{ $fmtQty($bp['qty']) }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    @endforeach
@endforeach
