@php
    use Modules\Purchase\Services\GrnPaymentSettlementService;

    /** @var \Modules\Purchase\Models\GoodsReceiveNote $grn */
    $grn = $grn ?? null;
    if (!$grn) {
        return;
    }

    $status = $grn->paymentStatus();
    $compact = filter_var($compact ?? false, FILTER_VALIDATE_BOOLEAN);
    $dense = filter_var($dense ?? false, FILTER_VALIDATE_BOOLEAN);
    $showAmounts = filter_var($showAmounts ?? (!$dense), FILTER_VALIDATE_BOOLEAN);
    $currencyLabel = filled($currency ?? null) ? (string) $currency : '';

    $paid = $grn->ledgerPaidTotal();
    $outstanding = $grn->amountOutstanding();
    $total = round((float) $grn->total, 2);
    $isPartialWithBalance = $status === GrnPaymentSettlementService::STATUS_PAID_PARTIAL && $outstanding > 0.005;
    $showFullAmounts = $showAmounts && $total > 0.005;
    $showPartialSummary = $isPartialWithBalance && ! $showFullAmounts;
@endphp

<div class="grn-pay-status {{ $compact ? 'grn-pay-status--compact' : '' }} {{ $dense ? 'grn-pay-status--dense' : '' }}">
    <span class="grn-pay-status__badge grn-pay-status__badge--{{ $status }}" title="{{ $grn->paymentStatusLabel() }}">
        @if($status === GrnPaymentSettlementService::STATUS_PAID_FULL)
            <i class="fa fa-circle-check" aria-hidden="true"></i>
        @elseif($status === GrnPaymentSettlementService::STATUS_PAID_PARTIAL)
            <i class="fa fa-circle-half-stroke" aria-hidden="true"></i>
        @elseif($status === GrnPaymentSettlementService::STATUS_PENDING)
            <i class="fa fa-clock" aria-hidden="true"></i>
        @endif
        {{ $grn->paymentStatusLabel() }}
    </span>

    @if($showFullAmounts)
        <div class="grn-pay-status__amounts">
            <span class="grn-pay-status__chip" title="Receipt total">
                <span class="grn-pay-status__chip-label">Total</span>
                <strong>{{ number_format($total, 2) }}</strong>
            </span>
            <span class="grn-pay-status__chip" title="Amount paid">
                <span class="grn-pay-status__chip-label">Paid</span>
                <strong>{{ number_format($paid, 2) }}</strong>
            </span>
            <span class="grn-pay-status__chip grn-pay-status__chip--{{ $outstanding > 0.005 ? 'due' : 'clear' }}" title="Balance still due">
                <span class="grn-pay-status__chip-label">Balance</span>
                <strong>{{ number_format($outstanding, 2) }}</strong>
            </span>
            @if($currencyLabel)
                <span class="grn-pay-status__currency muted">{{ $currencyLabel }}</span>
            @endif
        </div>
    @elseif($showPartialSummary)
        <div class="grn-pay-status__amounts grn-pay-status__amounts--partial-summary">
            <span class="grn-pay-status__chip grn-pay-status__chip--paid" title="Amount paid on this receipt">
                <span class="grn-pay-status__chip-label">Paid</span>
                <strong>{{ number_format($paid, 2) }}</strong>
            </span>
            <span class="grn-pay-status__chip grn-pay-status__chip--due" title="Balance still due on this receipt">
                <span class="grn-pay-status__chip-label">Balance</span>
                <strong>{{ number_format($outstanding, 2) }}</strong>
            </span>
            @if($currencyLabel)
                <span class="grn-pay-status__currency muted">{{ $currencyLabel }}</span>
            @endif
        </div>
    @endif

    @if(!$compact && $grn->paymentMethodLabel())
        <span class="grn-pay-status__method muted">{{ $grn->paymentMethodLabel() }}</span>
    @endif
</div>
