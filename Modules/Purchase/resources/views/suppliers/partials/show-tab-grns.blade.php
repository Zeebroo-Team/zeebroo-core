@php
    $grns = $grns ?? collect();
@endphp
@if($grns->isEmpty())
    <p class="muted" style="margin:0;font-size:13px;">No goods receive notes for this supplier yet.</p>
@else
    <div class="pcat-table-wrap">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>GRN #</th>
                    <th>Received</th>
                    <th>PO #</th>
                    <th>Lines</th>
                    <th>Total @if(filled($currency))({{ $currency }})@endif</th>
                    <th>Payment</th>
                    <th style="text-align:right;">View</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grns as $row)
                    <tr>
                        <td><strong style="color:var(--text);">{{ $row->grn_number }}</strong></td>
                        <td class="muted">{{ $row->received_date->format('M j, Y') }}</td>
                        <td>
                            @if($row->purchase)
                                <a href="{{ route('purchase.show', $row->purchase) }}" class="pcat-link">{{ $row->purchase->po_number }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="muted">{{ (int) $row->items_count }}</td>
                        <td><strong style="color:var(--text);">{{ number_format((float) $row->total, 2) }}</strong></td>
                        <td class="grn-pay-status-cell">
                            @include('purchase::goods-receive.partials.payment-status', [
                                'grn' => $row,
                                'currency' => $currency,
                                'compact' => true,
                                'dense' => true,
                            ])
                        </td>
                        <td style="text-align:right;">
                            <a href="{{ route('purchase.grn.show', $row) }}" class="pcat-link"><i class="fa fa-eye"></i> View</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
