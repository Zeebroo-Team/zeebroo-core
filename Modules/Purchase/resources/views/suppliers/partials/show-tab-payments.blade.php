@php
    $paymentSubTab = $paymentSubTab ?? 'cash';
    $cashPayments = $cashPayments ?? collect();
    $cheques = $cheques ?? collect();
    $creditGrns = $creditGrns ?? collect();
    $paymentMethods = \Modules\Purchase\Models\Purchase::paymentMethods();
@endphp
<nav class="supplier-pay-subtabs" aria-label="Payment type">
    <a href="{{ $supplierPayTabUrl('cash') }}" class="supplier-pay-subtabs__tab @if($paymentSubTab === 'cash') is-active @endif">
        <i class="fa fa-money-bill-wave" aria-hidden="true"></i> Cash
        <span class="supplier-show-tabs__count">{{ $cashPayments->count() }}</span>
    </a>
    <a href="{{ $supplierPayTabUrl('cheque') }}" class="supplier-pay-subtabs__tab @if($paymentSubTab === 'cheque') is-active @endif">
        <i class="fa fa-money-check" aria-hidden="true"></i> Cheques
        <span class="supplier-show-tabs__count">{{ $cheques->count() }}</span>
    </a>
    <a href="{{ $supplierPayTabUrl('credit') }}" class="supplier-pay-subtabs__tab @if($paymentSubTab === 'credit') is-active @endif">
        <i class="fa fa-clock" aria-hidden="true"></i> Credit
        <span class="supplier-show-tabs__count">{{ $creditGrns->count() }}</span>
    </a>
</nav>

@if($paymentSubTab === 'cash')
    @if($cashPayments->isEmpty())
        <p class="muted" style="margin:0;font-size:13px;">No cash payments recorded for this supplier yet.</p>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>GRN</th>
                        <th>PO #</th>
                        <th>Account</th>
                        <th>Amount @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cashPayments as $ledger)
                        @php
                            $grn = $ledger->transactionable;
                        @endphp
                        <tr>
                            <td class="muted">{{ $ledger->occurrence_date?->format('M j, Y') ?? '—' }}</td>
                            <td>
                                @if($grn instanceof \Modules\Purchase\Models\GoodsReceiveNote)
                                    <a href="{{ route('purchase.grn.show', $grn) }}" class="pcat-link" style="font-weight:700;">{{ $grn->grn_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="muted">{{ $grn?->purchase?->po_number ?? '—' }}</td>
                            <td class="muted">{{ $ledger->deductAccount?->name ?? '—' }}</td>
                            <td><strong style="color:var(--text);">{{ number_format((float) $ledger->amount, 2) }}</strong></td>
                            <td class="muted">{{ $ledger->meta['payment_reference'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@elseif($paymentSubTab === 'cheque')
    @if($cheques->isEmpty())
        <p class="muted" style="margin:0;font-size:13px;">No cheque payments for this supplier yet.</p>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Cheque #</th>
                        <th>Due</th>
                        <th>GRN</th>
                        <th>Amount @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Status</th>
                        <th style="text-align:right;">View</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cheques as $cheque)
                        @php
                            $grn = $cheque->goodsReceiveNote;
                            $displayStatus = $cheque->displayStatus();
                        @endphp
                        <tr>
                            <td><strong style="color:var(--text);">{{ $cheque->cheque_number }}</strong></td>
                            <td class="muted @if($displayStatus === 'overdue') cheque-due--overdue @endif">{{ $cheque->due_date?->format('M j, Y') ?? '—' }}</td>
                            <td>
                                @if($grn)
                                    <a href="{{ route('purchase.grn.show', $grn) }}" class="pcat-link">{{ $grn->grn_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td><strong style="color:var(--text);">{{ number_format((float) $cheque->amount, 2) }}</strong></td>
                            <td><span class="cheque-status cheque-status--{{ $displayStatus }}">{{ $cheque->displayStatusLabel() }}</span></td>
                            <td style="text-align:right;">
                                <a href="{{ route('purchase.cheques.show', $cheque) }}" class="pcat-link"><i class="fa fa-eye"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@else
    @if($creditGrns->isEmpty())
        <p class="muted" style="margin:0;font-size:13px;">No credit terms or outstanding balances for this supplier.</p>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>GRN #</th>
                        <th>Received</th>
                        <th>PO #</th>
                        <th>Terms</th>
                        <th>Total @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Outstanding</th>
                        <th>Payment</th>
                        <th style="text-align:right;">View</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($creditGrns as $grn)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $grn->grn_number }}</strong></td>
                            <td class="muted">{{ $grn->received_date->format('M j, Y') }}</td>
                            <td>
                                @if($grn->purchase)
                                    <a href="{{ route('purchase.show', $grn->purchase) }}" class="pcat-link">{{ $grn->purchase->po_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="muted">{{ $paymentMethods[$grn->payment_method] ?? ($grn->payment_method ? ucfirst($grn->payment_method) : '—') }}</td>
                            <td><strong style="color:var(--text);">{{ number_format((float) $grn->total, 2) }}</strong></td>
                            <td><strong style="color:var(--text);">{{ number_format($grn->amountOutstanding(), 2) }}</strong></td>
                            <td class="grn-pay-status-cell">
                                @include('purchase::goods-receive.partials.payment-status', [
                                    'grn' => $grn,
                                    'currency' => $currency,
                                    'compact' => true,
                                    'dense' => true,
                                ])
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('purchase.grn.show', $grn) }}" class="pcat-link"><i class="fa fa-eye"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
