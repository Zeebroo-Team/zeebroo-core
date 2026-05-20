@php
    /** @var \Modules\Purchase\Models\Purchase $purchase */
    $purchase = $purchase;
    $notes = $notes ?? collect();
    $hasNotes = $notes->isNotEmpty();
    $defaultOpen = filter_var($defaultOpen ?? false, FILTER_VALIDATE_BOOLEAN);
@endphp
<details class="grn-po-group" data-grn-po-group @if($defaultOpen) open @endif>
    <summary class="grn-po-group__head">
        <span class="grn-po-group__chev" aria-hidden="true"><i class="fa fa-chevron-right"></i></span>
        <span class="grn-po-group__summary-main">
            <span class="grn-po-group__title-row">
                <a href="{{ route('purchase.show', $purchase) }}" class="grn-po-group__po-link" onclick="event.stopPropagation();">{{ $purchase->po_number ?? 'PO' }}</a>
                <span class="purchase-status purchase-status--{{ $purchase->status }}">{{ $purchase->statusLabel() }}</span>
                @if($hasNotes)
                    <span class="grn-po-group__count">{{ $notes->count() }} {{ $notes->count() === 1 ? 'GRN' : 'GRNs' }}</span>
                @endif
            </span>
            <span class="grn-po-group__meta">
                {{ $purchase->purchase_date->format('M j, Y') }}
                @if($purchase->supplier) · {{ $purchase->supplier->name }} @endif
            </span>
        </span>
        <span class="grn-po-group__actions" onclick="event.stopPropagation();">
            @if($purchase->canReceiveGoods())
                <a href="{{ route('purchase.grn.create', $purchase) }}" class="grn-po-group__btn grn-po-group__btn--primary" title="{{ $hasNotes ? 'New GRN' : 'Receive goods' }}">
                    <i class="fa fa-plus"></i>
                </a>
            @endif
            <a href="{{ route('purchase.show', $purchase) }}" class="grn-po-group__btn" title="View purchase order"><i class="fa fa-file-lines"></i></a>
        </span>
    </summary>
    <div class="grn-po-group__body">
        @if($hasNotes)
            <div class="pcat-table-wrap grn-po-group__table-wrap">
                <table class="pcat-table grn-po-group__table">
                    <thead>
                        <tr>
                            <th>GRN</th>
                            <th>Received</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Pay</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notes as $row)
                            <tr>
                                <td><a href="{{ route('purchase.grn.show', $row) }}" class="grn-po-group__grn-link">{{ $row->grn_number }}</a></td>
                                <td class="muted">{{ $row->received_date->format('M j, Y') }}</td>
                                <td class="grn-po-group__num">{{ number_format((float) $row->total, 2) }}</td>
                                <td class="grn-pay-status-cell">
                                    @include('purchase::goods-receive.partials.payment-status', [
                                        'grn' => $row,
                                        'currency' => $currency,
                                        'compact' => true,
                                        'dense' => true,
                                    ])
                                </td>
                                <td>
                                    @include('purchase::goods-receive.partials.pay-action', [
                                        'grn' => $row,
                                        'currency' => $currency,
                                        'hasPaymentAccounts' => $hasPaymentAccounts ?? false,
                                        'activeTab' => $activeTab ?? 'grouped',
                                    ])
                                </td>
                                <td class="grn-po-group__act"><a href="{{ route('purchase.grn.show', $row) }}" class="pcat-link"><i class="fa fa-eye"></i></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="grn-po-group__empty">
                No receipts yet.
                @if($purchase->canReceiveGoods())
                    <a href="{{ route('purchase.grn.create', $purchase) }}" class="pcat-link">Record first GRN</a>
                @endif
            </p>
        @endif
    </div>
</details>
