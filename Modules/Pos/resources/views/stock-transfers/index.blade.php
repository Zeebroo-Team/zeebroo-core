@extends('theme::layouts.app', ['title' => 'Stock Transfers', 'heading' => 'Stock Transfers'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.st-badge{display:inline-block;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;}
.st-badge--in_transit{border:1px solid color-mix(in srgb,#3b82f6 42%,var(--border));background:color-mix(in srgb,#3b82f6 11%,transparent);color:color-mix(in srgb,#2563eb 80%,var(--text));}
.st-badge--completed{border:1px solid color-mix(in srgb,#10b981 42%,var(--border));background:color-mix(in srgb,#10b981 11%,transparent);color:color-mix(in srgb,#059669 80%,var(--text));}
.st-badge--cancelled{border:1px solid color-mix(in srgb,#ef4444 42%,var(--border));background:color-mix(in srgb,#ef4444 11%,transparent);color:color-mix(in srgb,#dc2626 80%,var(--text));}
.st-num{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 38%,var(--border));background:color-mix(in srgb,var(--primary) 9%,transparent);color:var(--text);white-space:nowrap;}
.st-empty{text-align:center;padding:36px 20px 44px;display:flex;flex-direction:column;align-items:center;gap:10px;}
.st-empty__icon{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:22px;background:color-mix(in srgb,#3b82f6 13%,transparent);color:#2563eb;margin-bottom:4px;}
.st-empty__title{font-size:16px;font-weight:800;color:var(--text);margin:0;}
.st-empty__desc{font-size:13px;color:var(--muted);max-width:380px;line-height:1.55;margin:0;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Move stock between branches. Sending a transfer immediately deducts from the source branch;
        receiving it adds the stock to the destination branch.
    </p>

    <div class="pcat-toolbar" style="margin-bottom:18px;">
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search by transfer #…"
                   style="padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);min-width:200px;">
            <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);">
                <i class="fa fa-magnifying-glass"></i>
            </button>
        </form>
        <a href="{{ route('pos.stock-transfers.create') }}"
           class="linkbtn" style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
            <i class="fa fa-plus"></i> New transfer
        </a>
    </div>

    @if($transfers->total() > 0)
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Transfer #</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th>Transferred</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfers as $transfer)
                        <tr>
                            <td><span class="st-num"><i class="fa fa-truck-arrow-right" style="font-size:10px;opacity:.7;"></i> {{ $transfer->transfer_number }}</span></td>
                            <td style="font-size:13px;">{{ $transfer->fromBranch?->name ?? '—' }}</td>
                            <td style="font-size:13px;">{{ $transfer->toBranch?->name ?? '—' }}</td>
                            <td class="muted">{{ $transfer->lines_count ?? $transfer->lines->count() }}</td>
                            <td><span class="st-badge st-badge--{{ $transfer->status }}">{{ $transfer->statusLabel() }}</span></td>
                            <td class="muted" style="font-size:12px;white-space:nowrap;">{{ $transfer->transferred_at?->format('M j, Y') ?? '—' }}</td>
                            <td style="text-align:right;white-space:nowrap;">
                                <a href="{{ route('pos.stock-transfers.show', $transfer) }}"
                                   class="linkbtn" style="padding:6px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                                    <i class="fa fa-eye" style="font-size:11px;opacity:.7;"></i> View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($transfers->hasPages())
            <div style="margin-top:14px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;font-size:13px;">
                {!! $transfers->withQueryString()->links() !!}
            </div>
        @endif
    @else
        <section class="pcat-inline">
            <div class="st-empty">
                <div class="st-empty__icon"><i class="fa fa-truck-arrow-right"></i></div>
                <p class="st-empty__title">No stock transfers yet</p>
                <p class="st-empty__desc">
                    Move products between branches when one location runs low and another has surplus stock.
                </p>
                <a href="{{ route('pos.stock-transfers.create') }}" class="linkbtn"
                   style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;margin-top:6px;">
                    <i class="fa fa-plus"></i> Start first transfer
                </a>
            </div>
        </section>
    @endif
</div>
@endsection
