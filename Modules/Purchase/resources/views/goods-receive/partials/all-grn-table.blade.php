@php
    $notes = $notes ?? collect();
    $hasGrns = $hasGrns ?? false;
    $hasActiveFilters = $hasActiveFilters ?? false;
@endphp
@if($notes->isEmpty())
    <p class="muted" style="margin:0;font-size:12px;line-height:1.45;">
        @if($hasActiveFilters)
            No goods receive notes match your search or filters.
            <a href="{{ route('purchase.grn.index', ['view' => 'all']) }}" class="pcat-link">Clear filters</a>
        @elseif(!$hasGrns)
            No goods receive notes yet.
            @if($openPurchaseOrders->isNotEmpty())
                <a href="{{ route('purchase.grn.index', ['view' => 'grouped']) }}" class="pcat-link">Record a receipt</a> from a purchase order.
            @else
                <a href="{{ route('purchase.index') }}" class="pcat-link">Create a purchase order</a> first.
            @endif
        @else
            No goods receive notes found.
        @endif
    </p>
@else
    <div class="pcat-table-wrap grn-all-table-wrap">
        <table class="pcat-table grn-all-table">
            <thead>
                <tr>
                    <th>GRN #</th>
                    <th>Received</th>
                    <th>PO #</th>
                    <th>Supplier</th>
                    <th>Total @if(filled($currency))({{ $currency }})@endif</th>
                    <th>Payment</th>
                    <th>Pay</th>
                    <th style="text-align:right;">View</th>
                </tr>
            </thead>
            <tbody>
                @foreach($notes as $row)
                    <tr>
                        <td><strong style="color:var(--text);font-size:12px;">{{ $row->grn_number }}</strong></td>
                        <td class="muted" style="font-size:12px;">{{ $row->received_date->format('M j, Y') }}</td>
                        <td style="font-size:12px;">
                            @if($row->purchase)
                                <a href="{{ route('purchase.show', $row->purchase) }}" class="pcat-link">{{ $row->purchase->po_number }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="muted" style="font-size:12px;">{{ $row->purchase?->supplier?->name ?? '—' }}</td>
                        <td style="font-size:12px;"><strong style="color:var(--text);">{{ number_format((float) $row->total, 2) }}</strong></td>
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
                                'activeTab' => 'all',
                            ])
                        </td>
                        <td style="text-align:right;">
                            <a href="{{ route('purchase.grn.show', $row) }}" class="pcat-link" style="font-size:12px;"><i class="fa fa-eye"></i></a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
