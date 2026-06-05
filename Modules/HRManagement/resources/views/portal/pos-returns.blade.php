@extends('theme::layouts.app', [
    'title' => __('Return items'),
    'heading' => __('Return items'),
    'employeePortal' => true,
    'portalEmployerBusiness' => $portalEmployerBusiness,
    'portalEmployee' => $portalEmployee,
    'portalEmployeeChoices' => $portalEmployeeChoices,
])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.ret-badge{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid color-mix(in srgb,#f59e0b 42%,var(--border));background:color-mix(in srgb,#f59e0b 11%,transparent);color:color-mix(in srgb,#b45309 80%,var(--text));}
.ret-method{display:inline-block;font-size:11px;font-weight:600;padding:3px 8px;border-radius:999px;border:1px solid var(--border);color:var(--muted);}
.ret-summary-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;}
.ret-summary-card{border:1px solid var(--border);border-radius:10px;padding:10px 14px;background:color-mix(in srgb,var(--card) 96%,transparent);min-width:130px;}
.ret-summary-card__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 3px;}
.ret-summary-card__value{font-size:18px;font-weight:800;color:var(--text);margin:0;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">

    <p class="muted" style="margin:0 0 10px;font-size:12px;">
        <a href="{{ route('hr.portal.pos-online') }}" class="pcat-link"><i class="fa fa-arrow-left"></i> POS Online</a>
    </p>

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        All processed returns for <strong style="color:var(--text);">{{ $business->name }}</strong>.
    </p>

    @if($hasReturns)
        @php
            $totalReturnValue = $returns->sum(fn($r) => (float) $r->total);
            $totalReturnItems = $returns->sum(fn($r) => $r->items->count());
        @endphp
        <div class="ret-summary-bar">
            <div class="ret-summary-card">
                <p class="ret-summary-card__label">Returns (this page)</p>
                <p class="ret-summary-card__value">{{ $returns->count() }}</p>
            </div>
            <div class="ret-summary-card">
                <p class="ret-summary-card__label">Total value @if(filled($currency))({{ $currency }})@endif</p>
                <p class="ret-summary-card__value">{{ number_format($totalReturnValue, 2) }}</p>
            </div>
            <div class="ret-summary-card">
                <p class="ret-summary-card__label">Line items</p>
                <p class="ret-summary-card__value">{{ $totalReturnItems }}</p>
            </div>
        </div>
    @endif

    <div class="pcat-toolbar">
        @if($hasReturns)
            <form method="get" action="{{ route('hr.portal.pos-returns.index') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search return # or sale #…" style="min-width:200px;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);">
                <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:13px;">Search</button>
                @if(filled($search))
                    <a href="{{ route('hr.portal.pos-returns.index') }}" class="pcat-link" style="font-size:13px;">Clear</a>
                @endif
            </form>
        @else
            <span class="muted" style="font-size:13px;">No returns have been processed yet.</span>
        @endif
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-left:auto;">
            <a href="{{ route('hr.portal.pos-returns.create') }}" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-rotate-left"></i> With sale reference
            </a>
            <a href="{{ route('hr.portal.pos-returns.create', ['mode' => 'open']) }}" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-box-open"></i> Without sale reference
            </a>
        </div>
    </div>

    @if(!$hasReturns)
        <section class="pcat-inline" style="margin-top:8px;">
            <h2>No returns yet</h2>
            <p class="pcat-muted">Use the buttons above to process a return. Stock is restored automatically and the refund method is recorded.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                <a href="{{ route('hr.portal.pos-returns.create') }}" class="linkbtn" style="display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa fa-rotate-left"></i> With sale reference
                </a>
                <a href="{{ route('hr.portal.pos-returns.create', ['mode' => 'open']) }}" class="linkbtn" style="display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa fa-box-open"></i> Without sale reference
                </a>
            </div>
        </section>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Return #</th>
                        <th>Sale #</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Reason</th>
                        <th>Refund method</th>
                        <th>Processed by</th>
                        <th>Total @if(filled($currency))({{ $currency }})@endif</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $ret)
                        <tr>
                            <td>
                                <span class="ret-badge"><i class="fa fa-rotate-left" style="margin-right:4px;"></i>{{ $ret->return_number }}</span>
                            </td>
                            <td>
                                @if($ret->sale)
                                    <span style="font-weight:700;color:var(--text);">{{ $ret->sale->sale_number }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td class="muted" style="white-space:nowrap;">{{ $ret->returned_at?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="muted">{{ $ret->items->count() }}</td>
                            <td>
                                @if(filled($ret->refund_reason))
                                    <span class="ret-method">{{ $ret->reasonLabel() }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="ret-method">{{ $ret->refundMethodLabel() }}</span>
                            </td>
                            <td class="muted">{{ $ret->user?->name ?? '—' }}</td>
                            <td><strong style="color:var(--text);">{{ number_format((float) $ret->total, 2) }}</strong></td>
                        </tr>
                        @foreach($ret->items as $item)
                            <tr style="background:color-mix(in srgb,var(--border) 14%,transparent);">
                                <td></td>
                                <td colspan="2" style="padding-left:20px;font-size:12px;color:var(--muted);">
                                    <i class="fa fa-corner-down-right" style="margin-right:6px;font-size:10px;opacity:.6;"></i>
                                    {{ $item->product_name }}
                                    @if(filled($item->sku))<span style="opacity:.7;"> · {{ $item->sku }}</span>@endif
                                </td>
                                <td class="muted" style="font-size:12px;">
                                    {{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}
                                </td>
                                <td class="muted" style="font-size:12px;">@ {{ number_format((float) $item->unit_sell_price, 2) }}</td>
                                <td></td>
                                <td style="font-size:12px;font-weight:700;color:var(--text);">{{ number_format((float) $item->line_total, 2) }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                        @if(filled($ret->notes))
                            <tr style="background:color-mix(in srgb,var(--border) 8%,transparent);">
                                <td></td>
                                <td colspan="7" style="padding-left:20px;font-size:12px;color:var(--muted);padding-top:4px;padding-bottom:8px;">
                                    <i class="fa fa-note-sticky" style="margin-right:5px;font-size:10px;opacity:.6;"></i>
                                    {{ $ret->notes }}
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="muted" style="padding:16px;">No returns match your search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($returns->hasPages())
            <div style="margin-top:14px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;font-size:13px;">
                {!! $returns->withQueryString()->links() !!}
            </div>
        @endif
    @endif
</div>
@endsection
