@php
    /** @var \Modules\Purchase\Models\GoodsReceiveNote $grn */
    $grn = $grn ?? null;
    if (!$grn) {
        return;
    }

    $total = round((float) $grn->total, 2);
    $outstanding = $grn->amountOutstanding();
    $paid = $grn->ledgerPaidTotal();
    $canPay = ($hasPaymentAccounts ?? false) && $total > 0.005 && $outstanding > 0.005;
    $returnTo = $returnTo ?? 'index';
    $returnView = $returnView ?? ($activeTab ?? 'grouped');
@endphp
@if($canPay)
    <button
        type="button"
        class="grn-pay-open-btn linkbtn"
        data-grn-pay-open
        data-grn-id="{{ $grn->id }}"
        data-grn-pay-url="{{ route('purchase.grn.pay', $grn) }}"
        data-grn-number="{{ $grn->grn_number }}"
        data-grn-po="{{ $grn->purchase?->po_number ?? '' }}"
        data-grn-supplier="{{ $grn->purchase?->supplier?->name ?? '' }}"
        data-grn-total="{{ number_format($total, 2, '.', '') }}"
        data-grn-paid="{{ number_format($paid, 2, '.', '') }}"
        data-grn-outstanding="{{ number_format($outstanding, 2, '.', '') }}"
        data-grn-payment-method="{{ $grn->payment_method ?? '' }}"
        data-grn-cheque-due-date="{{ $grn->chequePayments->sortByDesc('id')->first()?->due_date?->format('Y-m-d') ?? $grn->cheque_due_date?->format('Y-m-d') ?? '' }}"
        data-grn-currency="{{ $currency ?? '' }}"
        data-grn-return-to="{{ $returnTo }}"
        data-grn-return-view="{{ $returnView }}"
        title="Record payment for {{ $grn->grn_number }}"
    >
        <i class="fa fa-wallet" aria-hidden="true"></i>
        <span class="grn-pay-open-btn__label">Make payment</span>
    </button>
@endif
