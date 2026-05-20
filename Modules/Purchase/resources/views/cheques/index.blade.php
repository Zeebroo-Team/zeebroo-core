@extends('theme::layouts.app', ['title' => 'Cheque payments', 'heading' => 'Cheque payments'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.cheque-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:0 0 14px;}
@media(max-width:720px){.cheque-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
.cheque-summary__card{padding:10px 12px;border:1px solid color-mix(in srgb,var(--border) 85%,transparent);border-radius:10px;background:color-mix(in srgb,var(--card) 94%,var(--primary) 6%);}
.cheque-summary__label{margin:0;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);}
.cheque-summary__value{margin:4px 0 0;font-size:16px;font-weight:800;color:var(--text);}
.cheque-filters{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px;}
.cheque-filters__tab{
    display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:12px;font-weight:700;
    border-radius:999px;border:1px solid var(--border);color:var(--muted);text-decoration:none;background:var(--card);
}
.cheque-filters__tab:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));color:var(--text);}
.cheque-filters__tab.is-active{
    border-color:color-mix(in srgb,var(--primary) 40%,var(--border));
    background:color-mix(in srgb,var(--primary) 10%,var(--card));color:var(--text);
}
.cheque-status{font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;border:1px solid var(--border);white-space:nowrap;}
.cheque-status--cleared{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:color-mix(in srgb,#22c55e 80%,var(--text));}
.cheque-status--pending,.cheque-status--due{border-color:color-mix(in srgb,#3b82f6 40%,var(--border));background:color-mix(in srgb,#3b82f6 10%,transparent);color:color-mix(in srgb,#3b82f6 75%,var(--text));}
.cheque-status--overdue{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);color:color-mix(in srgb,#f59e0b 85%,var(--text));}
.cheque-due--overdue{color:color-mix(in srgb,#f59e0b 90%,var(--text));font-weight:700;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('purchase::partials.purchase-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <p class="muted" style="margin:0 0 12px;font-size:13px;line-height:1.45;">
        Cheque payments for <strong style="color:var(--text);">{{ $business->name }}</strong> from goods receipts and supplier settlements.
    </p>

    <div class="cheque-summary">
        <div class="cheque-summary__card">
            <p class="cheque-summary__label">Open amount</p>
            <p class="cheque-summary__value">{{ number_format($summary['pending_amount'], 2) }}@if(filled($currency)) <span style="font-size:11px;font-weight:600;">{{ $currency }}</span>@endif</p>
        </div>
        <div class="cheque-summary__card">
            <p class="cheque-summary__label">Due / pending</p>
            <p class="cheque-summary__value">{{ $summary['pending'] }}</p>
        </div>
        <div class="cheque-summary__card">
            <p class="cheque-summary__label">Overdue</p>
            <p class="cheque-summary__value">{{ $summary['overdue'] }}</p>
        </div>
        <div class="cheque-summary__card">
            <p class="cheque-summary__label">Cleared</p>
            <p class="cheque-summary__value">{{ $summary['cleared'] }}</p>
        </div>
    </div>

    @php
        $filterTabs = [
            'all' => 'All',
            'due' => 'Due',
            'overdue' => 'Overdue',
            'pending' => 'Pending',
            'cleared' => 'Cleared',
        ];
    @endphp
    <nav class="cheque-filters" aria-label="Cheque filters">
        @foreach($filterTabs as $tabKey => $tabLabel)
            <a href="{{ route('purchase.cheques.index', $tabKey === 'all' ? [] : ['filter' => $tabKey]) }}" class="cheque-filters__tab @if($filter === $tabKey) is-active @endif">{{ $tabLabel }}</a>
        @endforeach
    </nav>

    @if($cheques->isEmpty())
        <p class="muted" style="margin:0;font-size:13px;line-height:1.45;">
            @if($filter === 'all')
                No cheque payments yet. Record a goods receipt or payment with <strong style="color:var(--text);">Cheque</strong> as the payment method.
                @if(Route::has('purchase.grn.index'))
                    <a href="{{ route('purchase.grn.index') }}" class="pcat-link">Goods receive notes</a>
                @endif
            @else
                No cheques match this filter.
                <a href="{{ route('purchase.cheques.index') }}" class="pcat-link">Show all</a>
            @endif
        </p>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Cheque #</th>
                        <th>Due date</th>
                        <th>Amount @if(filled($currency))({{ $currency }})@endif</th>
                        <th>GRN</th>
                        <th>PO / Supplier</th>
                        <th>Account</th>
                        <th>Paid</th>
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
                            <td>
                                <span class="cheque-status cheque-status--{{ $displayStatus }}">{{ $cheque->displayStatusLabel() }}</span>
                            </td>
                            <td>
                                <a href="{{ route('purchase.cheques.show', $cheque) }}" class="pcat-link" style="font-weight:700;color:var(--text);">{{ $cheque->cheque_number }}</a>
                            </td>
                            <td @class(['muted', 'cheque-due--overdue' => $displayStatus === 'overdue']) style="font-size:12px;">
                                {{ $cheque->due_date->format('M j, Y') }}
                            </td>
                            <td><strong style="color:var(--text);">{{ number_format((float) $cheque->amount, 2) }}</strong></td>
                            <td style="font-size:12px;">
                                @if($grn)
                                    <a href="{{ route('purchase.grn.show', $grn) }}" class="pcat-link">{{ $grn->grn_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="muted" style="font-size:12px;">
                                @if($grn?->purchase)
                                    <a href="{{ route('purchase.show', $grn->purchase) }}" class="pcat-link">{{ $grn->purchase->po_number }}</a>
                                @else
                                    —
                                @endif
                                @if($grn?->purchase?->supplier)
                                    <span style="display:block;margin-top:2px;">{{ $grn->purchase->supplier->name }}</span>
                                @endif
                            </td>
                            <td class="muted" style="font-size:12px;">{{ $cheque->deductAccount?->deductOptionLabel() ?? '—' }}</td>
                            <td class="muted" style="font-size:12px;">{{ $cheque->paidAt()?->format('M j, Y') ?? '—' }}</td>
                            <td style="text-align:right;">
                                <a href="{{ route('purchase.cheques.show', $cheque) }}" class="pcat-link" style="font-size:12px;">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div style="margin-top:12px;">
    <a href="{{ route('dashboard') }}" class="linkbtn" style="padding:6px 10px;font-size:11px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>
@endsection
