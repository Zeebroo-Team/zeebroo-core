@extends('theme::layouts.app', ['title' => 'End of Day', 'heading' => 'End of Day Settlement'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.eod-page{max-width:100%;}
.eod-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin:0 0 20px;}
.eod-stat{padding:12px 16px;border:1px solid var(--border);border-radius:11px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.eod-stat__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 4px;}
.eod-stat__value{font-size:20px;font-weight:800;color:var(--text);}
.eod-stat--highlight{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.eod-section-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 10px;display:flex;align-items:center;gap:6px;}
.eod-section-label::after{content:'';flex:1;height:1px;background:var(--border);}
.eod-table-wrap{border:1px solid var(--border);border-radius:11px;overflow:hidden;margin:0 0 20px;}
.eod-table{width:100%;border-collapse:collapse;font-size:13px;}
.eod-table th{padding:8px 12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);background:color-mix(in srgb,var(--card) 92%,var(--border) 8%);border-bottom:1px solid var(--border);text-align:left;}
.eod-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);vertical-align:middle;}
.eod-table tr:last-child td{border-bottom:none;}
.eod-table tr:hover td{background:color-mix(in srgb,var(--card) 95%,var(--border) 5%);}
.eod-badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;border:1px solid var(--border);}
.eod-badge--cash{border-color:color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);}
.eod-badge--card{border-color:color-mix(in srgb,#3b82f6 40%,var(--border));background:color-mix(in srgb,#3b82f6 10%,transparent);}
.eod-settle-bar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:16px 18px;border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));border-radius:12px;background:color-mix(in srgb,var(--primary) 6%,transparent);margin:0 0 20px;}
.eod-settle-bar__info{font-size:13px;color:var(--text);}
.eod-settle-bar__info strong{font-size:18px;font-weight:800;display:block;}
.eod-settle-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;font-size:13px;font-weight:800;border-radius:10px;border:1px solid color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 18%,transparent);color:var(--text);cursor:pointer;transition:all .15s;}
.eod-settle-btn:hover{background:color-mix(in srgb,var(--primary) 28%,transparent);box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 22%,transparent);}
.eod-empty{padding:32px;text-align:center;color:var(--muted);font-size:13px;}
.eod-empty i{font-size:28px;display:block;margin-bottom:8px;}
.eod-history__row{display:grid;grid-template-columns:1fr auto auto;gap:12px;align-items:center;padding:9px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
.eod-history__row:last-child{border-bottom:none;}
</style>

<div class="pcat-page-card card eod-page" style="padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    {{-- Summary stats --}}
    <div class="eod-summary">
        <div class="eod-stat eod-stat--highlight">
            <p class="eod-stat__label">Unsettled sales</p>
            <p class="eod-stat__value">{{ $unsettled->count() }}</p>
        </div>
        <div class="eod-stat eod-stat--highlight">
            <p class="eod-stat__label">Unsettled total @if(filled($currency))({{ $currency }})@endif</p>
            <p class="eod-stat__value">{{ number_format($totalUnsettled, 2) }}</p>
        </div>
        <div class="eod-stat">
            <p class="eod-stat__label">Date</p>
            <p class="eod-stat__value" style="font-size:15px;">{{ now()->format('M j, Y') }}</p>
        </div>
    </div>

    @if($unsettled->isNotEmpty())
        {{-- Settle action bar --}}
        <div class="eod-settle-bar">
            <div class="eod-settle-bar__info">
                <strong>{{ number_format($totalUnsettled, 2) }}{{ filled($currency) ? ' '.$currency : '' }}</strong>
                {{ $unsettled->count() }} unsettled sale{{ $unsettled->count() === 1 ? '' : 's' }} ready to deposit
            </div>
            <form method="POST" action="{{ route('pos.end-of-day.settle') }}" onsubmit="return confirm('Settle all {{ $unsettled->count() }} sale(s) to bank now?');">
                @csrf
                <button type="submit" class="eod-settle-btn">
                    <i class="fa fa-building-columns" aria-hidden="true"></i>
                    Settle all to bank
                </button>
            </form>
        </div>

        {{-- Unsettled sales table --}}
        <p class="eod-section-label"><i class="fa fa-clock"></i> Pending settlement</p>
        <div class="eod-table-wrap">
            <table class="eod-table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Time</th>
                        <th>Items</th>
                        <th>Payment</th>
                        <th>Account</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($unsettled as $sale)
                        <tr>
                            <td>
                                <a href="{{ route('pos.sales.show', $sale) }}" class="pcat-link" style="font-weight:700;">{{ $sale->sale_number }}</a>
                            </td>
                            <td style="color:var(--muted);font-size:12px;">{{ $sale->sold_at?->format('g:i A') ?? '—' }}</td>
                            <td style="color:var(--muted);">{{ $sale->items_count }}</td>
                            <td>
                                <span class="eod-badge eod-badge--{{ $sale->payment_method }}">{{ $sale->paymentMethodLabel() }}</span>
                            </td>
                            <td style="font-size:12px;color:var(--muted);">{{ $sale->creditAccount?->deductOptionLabel() ?? '—' }}</td>
                            <td style="text-align:right;font-weight:800;">{{ number_format((float)$sale->total, 2) }}{{ filled($currency) ? ' '.$currency : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align:right;font-size:12px;font-weight:700;color:var(--muted);padding:10px 12px;">Total</td>
                        <td style="text-align:right;font-size:15px;font-weight:800;padding:10px 12px;">{{ number_format($totalUnsettled, 2) }}{{ filled($currency) ? ' '.$currency : '' }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="eod-settle-bar" style="border-color:color-mix(in srgb,#22c55e 35%,var(--border));background:color-mix(in srgb,#22c55e 6%,transparent);">
            <div class="eod-settle-bar__info" style="display:flex;align-items:center;gap:10px;">
                <i class="fa fa-circle-check" style="font-size:22px;color:#22c55e;"></i>
                <span>All sales are settled. Nothing pending.</span>
            </div>
        </div>
    @endif

    {{-- Settlement history --}}
    @if($history->isNotEmpty())
        <p class="eod-section-label"><i class="fa fa-history"></i> Recent settlements (last 14 days)</p>
        <div class="eod-table-wrap">
            <div>
                @foreach($history as $row)
                    <div class="eod-history__row">
                        <span style="font-size:13px;font-weight:600;color:var(--text);">{{ \Carbon\Carbon::parse($row->settle_date)->format('M j, Y') }}</span>
                        <span style="font-size:12px;color:var(--muted);">{{ $row->sale_count }} sale{{ $row->sale_count != 1 ? 's' : '' }}</span>
                        <span style="font-size:14px;font-weight:800;">{{ number_format((float)$row->total_amount, 2) }}{{ filled($currency) ? ' '.$currency : '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
