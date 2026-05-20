@extends('theme::layouts.app', ['title' => 'Sales history', 'heading' => 'Sales history'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pos-sales-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);}
.pos-sales-status--completed{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.pos-sales-status--void{border-color:color-mix(in srgb,#94a3b8 45%,var(--border));opacity:.8;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Completed and voided sales for <strong style="color:var(--text);">{{ $business->name }}</strong>.
    </p>

    <div class="pcat-toolbar">
        @if($hasSales)
            <form method="get" action="{{ route('pos.sales.index') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search sale # or notes…" style="min-width:200px;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);">
                <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:13px;">Search</button>
                @if(filled($search))
                    <a href="{{ route('pos.sales.index') }}" class="pcat-link" style="font-size:13px;">Clear</a>
                @endif
            </form>
            <a href="{{ route('pos.online') }}" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-store"></i> Online POS</a>
        @else
            <span class="muted" style="margin:0;font-size:13px;">No sales yet — open the register to record your first sale.</span>
            <a href="{{ route('pos.online') }}" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-store"></i> Online POS</a>
        @endif
    </div>

    @if(!$hasSales)
        <section class="pcat-inline" style="margin-top:8px;">
            <h2>Get started</h2>
            <p class="pcat-muted">Use the point of sale register to sell products. Stock is deducted using FIFO batch pricing from goods receipts.</p>
            <a href="{{ route('pos.online') }}" class="linkbtn" style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;"><i class="fa fa-store"></i> Open online POS</a>
        </section>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Payment</th>
                        <th>Channel</th>
                        <th>Total @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $sale->sale_number }}</strong></td>
                            <td class="muted">{{ $sale->sold_at?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="muted">{{ (int) $sale->items_count }}</td>
                            <td class="muted">{{ $sale->paymentMethodLabel() }}</td>
                            <td class="muted">{{ $sale->channelLabel() }}</td>
                            <td><strong style="color:var(--text);">{{ number_format((float) $sale->total, 2) }}</strong></td>
                            <td>
                                @if($sale->isVoid())
                                    <span class="pos-sales-status pos-sales-status--void">Void</span>
                                @else
                                    <span class="pos-sales-status pos-sales-status--completed">Completed</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('pos.sales.show', $sale) }}" class="pcat-link" style="font-weight:700;">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted" style="padding:16px;">No sales match your search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
