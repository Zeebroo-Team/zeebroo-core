@extends('theme::layouts.app', ['title' => 'Sales history', 'heading' => 'Sales history'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
/* ── stat cards ─────────────────────────────────────────────────── */
.sh-stats{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:18px;}
@media(min-width:640px){.sh-stats{grid-template-columns:repeat(4,1fr);}}
.sh-stat{border:1px solid var(--border);border-radius:11px;padding:12px 14px;background:var(--card);}
.sh-stat__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 6px;}
.sh-stat__value{font-size:22px;font-weight:800;color:var(--text);margin:0;line-height:1.15;}
.sh-stat__sub{font-size:11px;color:var(--muted);margin:3px 0 0;}
.sh-stat--green .sh-stat__value{color:#16a34a;}
.sh-stat--red   .sh-stat__value{color:#ef4444;}

/* ── filter bar ─────────────────────────────────────────────────── */
.sh-filter-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-bottom:14px;}
.sh-filter-group{display:flex;flex-direction:column;gap:4px;}
.sh-filter-group label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.sh-input{padding:7px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);min-width:0;}
.sh-input:focus{outline:none;border-color:var(--primary);}
.sh-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;padding-right:28px;}

/* ── status tabs ────────────────────────────────────────────────── */
.sh-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:14px;}
.sh-tab{padding:6px 14px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;text-decoration:none;white-space:nowrap;}
.sh-tab:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);color:var(--text);}
.sh-tab--active{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);}

/* ── status badges ──────────────────────────────────────────────── */
.sh-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);white-space:nowrap;}
.sh-badge--ok{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:#16a34a;}
.sh-badge--void{border-color:color-mix(in srgb,#94a3b8 45%,var(--border));color:var(--muted);}
.sh-badge--disc{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 10%,transparent);color:#b45309;}

/* ── pagination ─────────────────────────────────────────────────── */
.sh-pagination{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:14px;font-size:13px;}
.sh-pagination a,.sh-pagination span{padding:5px 10px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:600;}
.sh-pagination span.sh-page-active{background:color-mix(in srgb,var(--primary) 15%,transparent);border-color:color-mix(in srgb,var(--primary) 50%,var(--border));color:var(--primary);}
.sh-pagination span.sh-page-dots{border:none;background:transparent;color:var(--muted);}
.sh-pagination a:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    {{-- ── summary stat cards ── --}}
    @if($hasSales && $summary)
    <div class="sh-stats">
        <div class="sh-stat">
            <p class="sh-stat__label">Total sales</p>
            <p class="sh-stat__value">{{ number_format($summary['count']) }}</p>
            <p class="sh-stat__sub">in current filter</p>
        </div>
        <div class="sh-stat sh-stat--green">
            <p class="sh-stat__label">Completed revenue @if(filled($currency))({{ $currency }})@endif</p>
            <p class="sh-stat__value">{{ number_format($summary['completed_total'], 2) }}</p>
            <p class="sh-stat__sub">{{ number_format($summary['completed_count']) }} completed</p>
        </div>
        <div class="sh-stat">
            <p class="sh-stat__label">Avg. sale @if(filled($currency))({{ $currency }})@endif</p>
            <p class="sh-stat__value">{{ $summary['completed_count'] > 0 ? number_format($summary['completed_total'] / $summary['completed_count'], 2) : '—' }}</p>
            <p class="sh-stat__sub">per completed sale</p>
        </div>
        <div class="sh-stat sh-stat--red">
            <p class="sh-stat__label">Voided</p>
            <p class="sh-stat__value">{{ number_format($summary['void_count']) }}</p>
            <p class="sh-stat__sub">sales voided</p>
        </div>
    </div>
    @endif

    @if(!$hasSales)
        <section class="pcat-inline" style="margin-top:8px;">
            <h2>Get started</h2>
            <p class="pcat-muted">Use the point of sale register to sell products. Stock is deducted using FIFO batch pricing from goods receipts.</p>
            <a href="{{ route('pos.online') }}" class="linkbtn" style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;"><i class="fa fa-store"></i> Open online POS</a>
        </section>
    @else

    {{-- ── status tabs ── --}}
    @php
        $baseUrl = route('pos.sales.index');
        $q = $filters['q'];
        $df = $filters['date_from'];
        $dt = $filters['date_to'];
        $ch = $filters['channel'];
        $buildUrl = function(string $status) use ($baseUrl, $q, $df, $dt, $ch) {
            return $baseUrl . '?' . http_build_query(array_filter([
                'status'    => $status !== 'all' ? $status : null,
                'q'         => $q ?: null,
                'date_from' => $df ?: null,
                'date_to'   => $dt ?: null,
                'channel'   => $ch !== 'all' ? $ch : null,
            ]));
        };
    @endphp
    <div class="sh-tabs">
        <a href="{{ $buildUrl('all') }}" class="sh-tab {{ $filters['status'] === 'all' ? 'sh-tab--active' : '' }}">All ({{ $summary['count'] }})</a>
        <a href="{{ $buildUrl('completed') }}" class="sh-tab {{ $filters['status'] === 'completed' ? 'sh-tab--active' : '' }}">Completed ({{ $summary['completed_count'] }})</a>
        <a href="{{ $buildUrl('void') }}" class="sh-tab {{ $filters['status'] === 'void' ? 'sh-tab--active' : '' }}">Void ({{ $summary['void_count'] }})</a>
    </div>

    {{-- ── filter bar ── --}}
    <form method="get" action="{{ route('pos.sales.index') }}" class="sh-filter-bar">
        @if($filters['status'] !== 'all')
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
        @endif

        <div class="sh-filter-group" style="flex:1;min-width:180px;">
            <label>Search</label>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Sale #, notes, customer…" class="sh-input" style="width:100%;box-sizing:border-box;">
        </div>

        <div class="sh-filter-group">
            <label>From</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="sh-input">
        </div>
        <div class="sh-filter-group">
            <label>To</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="sh-input">
        </div>

        <div class="sh-filter-group">
            <label>Channel</label>
            <select name="channel" class="sh-input sh-select">
                <option value="all" {{ $filters['channel'] === 'all' ? 'selected' : '' }}>All channels</option>
                <option value="retail" {{ $filters['channel'] === 'retail' ? 'selected' : '' }}>Retail</option>
                <option value="online" {{ $filters['channel'] === 'online' ? 'selected' : '' }}>Online</option>
            </select>
        </div>

        <div style="display:flex;gap:6px;align-items:flex-end;">
            <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:13px;">Filter</button>
            @if(array_filter([$filters['q'], $filters['date_from'], $filters['date_to'], $filters['status'] !== 'all' ? '1' : '', $filters['channel'] !== 'all' ? '1' : '']))
                <a href="{{ route('pos.sales.index') }}" class="pcat-link" style="font-size:13px;padding:7px 0;">Clear</a>
            @endif
        </div>

        <a href="{{ route('pos.online') }}" class="linkbtn" style="padding:7px 14px;font-size:13px;display:inline-flex;align-items:center;gap:6px;margin-left:auto;"><i class="fa fa-store"></i> Open POS</a>
    </form>

    {{-- ── table ── --}}
    <div class="pcat-table-wrap">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>Sale #</th>
                    <th>Date &amp; Time</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Channel</th>
                    <th>Payment</th>
                    <th>Total @if(filled($currency))({{ $currency }})@endif</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td>
                            <a href="{{ route('pos.sales.show', $sale) }}" style="font-weight:800;color:var(--text);text-decoration:none;">{{ $sale->sale_number }}</a>
                            @if((float)($sale->discount_percent ?? 0) > 0 || (float)($sale->discount_amount ?? 0) > 0)
                                <span class="sh-badge sh-badge--disc" style="margin-left:4px;font-size:10px;">
                                    <i class="fa fa-tag"></i>
                                    @if((float)($sale->discount_percent ?? 0) > 0)
                                        {{ rtrim(rtrim(number_format((float)$sale->discount_percent,2),'0'),'.') }}% off
                                    @endif
                                </span>
                            @endif
                        </td>
                        <td>
                            <span style="color:var(--text);font-weight:600;">{{ $sale->sold_at?->format('M j, Y') ?? '—' }}</span>
                            <span class="muted" style="display:block;font-size:11px;">{{ $sale->sold_at?->format('g:i A') }}</span>
                        </td>
                        <td>
                            @if($sale->customer)
                                <span style="color:var(--text);font-weight:600;">{{ $sale->customer->name }}</span>
                                @if($sale->customer->phone)
                                    <span class="muted" style="display:block;font-size:11px;">{{ $sale->customer->phone }}</span>
                                @endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="muted" style="font-weight:700;">{{ (int) $sale->items_count }}</td>
                        <td class="muted">
                            {{ $sale->channelLabel() }}
                            @if($sale->branch)
                                <span style="display:block;font-size:10px;margin-top:2px;color:var(--muted);"><i class="fa fa-code-branch" style="font-size:9px;"></i> {{ $sale->branch->name }}</span>
                            @endif
                        </td>
                        <td class="muted">{{ $sale->paymentMethodLabel() }}</td>
                        <td><strong style="color:var(--text);font-size:14px;">{{ number_format((float) $sale->total, 2) }}</strong></td>
                        <td>
                            @if($sale->isVoid())
                                <span class="sh-badge sh-badge--void"><i class="fa fa-ban"></i> Void</span>
                            @else
                                <span class="sh-badge sh-badge--ok"><i class="fa fa-check"></i> Completed</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            <a href="{{ route('pos.sales.show', $sale) }}" class="pcat-link" style="font-weight:700;">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="padding:32px;text-align:center;">
                            <p class="muted" style="margin:0 0 8px;">No sales match your filters.</p>
                            <a href="{{ route('pos.sales.index') }}" class="pcat-link" style="font-size:13px;">Clear filters</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── pagination ── --}}
    @if($sales && $sales->hasPages())
    <div class="sh-pagination">
        {{-- Previous --}}
        @if($sales->onFirstPage())
            <span style="opacity:.4;">‹ Prev</span>
        @else
            <a href="{{ $sales->previousPageUrl() }}">‹ Prev</a>
        @endif

        {{-- Page numbers --}}
        @foreach($sales->getUrlRange(max(1, $sales->currentPage() - 2), min($sales->lastPage(), $sales->currentPage() + 2)) as $page => $url)
            @if($page === $sales->currentPage())
                <span class="sh-page-active">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        {{-- Next --}}
        @if($sales->hasMorePages())
            <a href="{{ $sales->nextPageUrl() }}">Next ›</a>
        @else
            <span style="opacity:.4;">Next ›</span>
        @endif

        <span class="muted" style="font-size:12px;margin-left:auto;">
            Showing {{ $sales->firstItem() }}–{{ $sales->lastItem() }} of {{ number_format($sales->total()) }} sales
        </span>
    </div>
    @endif

    @endif {{-- hasSales --}}
</div>
@endsection
