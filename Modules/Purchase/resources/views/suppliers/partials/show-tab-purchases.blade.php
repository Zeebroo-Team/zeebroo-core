@php
    $purchases = $purchases ?? collect();
@endphp
@if($purchases->isEmpty())
    <p class="muted" style="margin:0;font-size:13px;">No purchase orders for this supplier yet.</p>
@else
    <div class="pcat-table-wrap">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>PO #</th>
                    <th>Date</th>
                    <th>Lines</th>
                    <th>GRNs</th>
                    <th>Total @if(filled($currency))({{ $currency }})@endif</th>
                    <th>Status</th>
                    <th style="text-align:right;">View</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchases as $row)
                    <tr>
                        <td><strong style="color:var(--text);">{{ $row->po_number ?? '—' }}</strong></td>
                        <td class="muted">{{ $row->purchase_date->format('M j, Y') }}</td>
                        <td class="muted">{{ (int) $row->items_count }}</td>
                        <td class="muted">{{ (int) $row->goods_receive_notes_count }}</td>
                        <td><strong style="color:var(--text);">{{ number_format((float) $row->total, 2) }}</strong></td>
                        <td>
                            <span class="purchase-status purchase-status--{{ $row->status }}">{{ $row->statusLabel() }}</span>
                        </td>
                        <td style="text-align:right;">
                            <a href="{{ route('purchase.show', $row) }}" class="pcat-link"><i class="fa fa-eye"></i> View</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
