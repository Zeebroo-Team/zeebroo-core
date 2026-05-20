@extends('theme::layouts.app', ['title' => $sale->sale_number, 'heading' => 'Sale '.$sale->sale_number])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pos-receipt-status{display:inline-block;font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;border:1px solid var(--border);}
.pos-receipt-status--completed{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.pos-receipt-status--void{border-color:color-mix(in srgb,#94a3b8 45%,var(--border));opacity:.85;}
.pos-receipt-meta{display:grid;gap:10px;margin-bottom:14px;}
@media (min-width:640px){.pos-receipt-meta{grid-template-columns:repeat(2,minmax(0,1fr));}}
.pos-receipt-meta__card{border:1px solid var(--border);border-radius:10px;padding:10px 12px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.pos-receipt-meta__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:0 0 4px;}
.pos-receipt-meta__value{margin:0;font-size:14px;font-weight:700;color:var(--text);}
.pos-receipt-actions{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;}
.pos-btn-void{padding:8px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,#ef4444 42%,var(--border));background:transparent;color:#f87171;cursor:pointer;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('sale'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('sale') }}</div>
    @endif

    <div class="pos-receipt-actions">
        <a href="{{ route('pos.sales.index') }}" class="pcat-link" style="font-weight:700;"><i class="fa fa-arrow-left"></i> Sales history</a>
        <a href="{{ route('pos.online') }}" class="pcat-link" style="font-weight:700;"><i class="fa fa-store"></i> Online POS</a>
        @if($sale->isCompleted())
            <form method="post" action="{{ route('pos.sales.void', $sale) }}" onsubmit="return confirm('Void this sale and restore stock?');" style="margin:0;">
                @csrf
                <button type="submit" class="pos-btn-void">Void sale</button>
            </form>
        @endif
    </div>

    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:14px;">
        <h2 style="margin:0;font-size:18px;font-weight:800;">{{ $sale->sale_number }}</h2>
        @if($sale->isVoid())
            <span class="pos-receipt-status pos-receipt-status--void">Void</span>
        @else
            <span class="pos-receipt-status pos-receipt-status--completed">Completed</span>
        @endif
    </div>

    <div class="pos-receipt-meta">
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Sold at</p>
            <p class="pos-receipt-meta__value">{{ $sale->sold_at?->format('M j, Y g:i A') ?? '—' }}</p>
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Payment</p>
            <p class="pos-receipt-meta__value">{{ $sale->paymentMethodLabel() }}</p>
            @if($sale->creditAccount)
                <p class="muted" style="margin:4px 0 0;font-size:12px;">{{ $sale->creditAccount->deductOptionLabel() }}</p>
            @endif
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Total @if(filled($currency))({{ $currency }})@endif</p>
            <p class="pos-receipt-meta__value">{{ number_format((float) $sale->total, 2) }}</p>
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Channel</p>
            <p class="pos-receipt-meta__value">{{ $sale->channelLabel() }}</p>
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Amount paid</p>
            <p class="pos-receipt-meta__value">{{ number_format((float) $sale->amount_paid, 2) }}@if(filled($currency)) {{ $currency }}@endif</p>
        </div>
        @if($sale->payment_method === \Modules\Pos\Models\Sale::PAYMENT_CASH && $sale->amount_tendered !== null)
            <div class="pos-receipt-meta__card">
                <p class="pos-receipt-meta__label">Cash received</p>
                <p class="pos-receipt-meta__value">{{ number_format((float) $sale->amount_tendered, 2) }}@if(filled($currency)) {{ $currency }}@endif</p>
            </div>
            <div class="pos-receipt-meta__card">
                <p class="pos-receipt-meta__label">Change given</p>
                <p class="pos-receipt-meta__value">{{ number_format((float) ($sale->change_amount ?? 0), 2) }}@if(filled($currency)) {{ $currency }}@endif</p>
            </div>
        @endif
    </div>

    @if(filled($sale->notes))
        <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;"><strong style="color:var(--text);">Notes:</strong> {{ $sale->notes }}</p>
    @endif

    <div class="pcat-table-wrap">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Unit price @if(filled($currency))({{ $currency }})@endif</th>
                    <th>Unit cost</th>
                    <th style="text-align:right;">Line total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td>
                            <strong style="color:var(--text);">{{ $item->product_name }}</strong>
                            @if(filled($item->sku))
                                <div class="muted" style="font-size:11px;margin-top:2px;">{{ $item->sku }}</div>
                            @endif
                            @if($item->product_stock_layer_id)
                                <div class="muted" style="font-size:11px;margin-top:2px;">Batch #{{ $item->product_stock_layer_id }}</div>
                            @endif
                        </td>
                        <td class="muted">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</td>
                        <td class="muted">{{ number_format((float) $item->unit_sell_price, 2) }}</td>
                        <td class="muted">{{ $item->unit_cost !== null ? number_format((float) $item->unit_cost, 2) : '—' }}</td>
                        <td style="text-align:right;"><strong style="color:var(--text);">{{ number_format((float) $item->line_total, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align:right;font-weight:700;">Total</td>
                    <td style="text-align:right;font-weight:800;font-size:15px;color:var(--text);">{{ number_format((float) $sale->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
