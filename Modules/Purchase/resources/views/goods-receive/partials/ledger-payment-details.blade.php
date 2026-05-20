@php
    $method = $ledger->meta['payment_method'] ?? null;
    if ($method === null && $cheque) {
        $method = \Modules\Purchase\Models\Purchase::PAYMENT_CHEQUE;
    }
    $methodLabels = \Modules\Purchase\Models\Purchase::paymentMethods();
    $methodLabel = $method ? ($methodLabels[$method] ?? ucfirst((string) $method)) : '—';
    $isCheque = $method === \Modules\Purchase\Models\Purchase::PAYMENT_CHEQUE;
    $chequeNumber = $cheque?->cheque_number ?? ($ledger->meta['payment_reference'] ?? null);
    $chequeDue = $cheque?->due_date ?? (filled($ledger->meta['cheque_due_date'] ?? null)
        ? \Illuminate\Support\Carbon::parse($ledger->meta['cheque_due_date'])
        : null);
@endphp
<span style="font-weight:600;color:var(--text);">{{ $methodLabel }}</span>
@if($isCheque && ($chequeNumber || $chequeDue || $cheque))
    <div class="muted grn-ledger-cheque-detail" style="margin-top:4px;font-size:11px;line-height:1.45;">
        @if($chequeNumber)
            <span>Cheque #
                @if($cheque && Route::has('purchase.cheques.show'))
                    <a href="{{ route('purchase.cheques.show', $cheque) }}" class="pcat-link" style="font-weight:700;color:var(--text);">{{ $chequeNumber }}</a>
                @else
                    <strong style="color:var(--text);font-weight:700;">{{ $chequeNumber }}</strong>
                @endif
            </span>
        @endif
        @if($chequeDue)
            @if($chequeNumber)<span> · </span>@endif
            <span>Due {{ $chequeDue->format('M j, Y') }}</span>
        @endif
        @if($cheque)
            @if($chequeNumber || $chequeDue)<span> · </span>@endif
            @if(Route::has('purchase.cheques.show'))
                <a href="{{ route('purchase.cheques.show', $cheque) }}" class="pcat-link">{{ $cheque->displayStatusLabel() }}</a>
            @else
                <span>{{ $cheque->displayStatusLabel() }}</span>
            @endif
        @endif
    </div>
@endif
