@extends('theme::layouts.app', ['title' => 'Return items', 'heading' => 'Return items'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
/* ── Stat cards ─────────────────────────────────────────────────── */
.ret-stats{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px;}
.ret-stat{flex:1;min-width:150px;border:1px solid var(--border);border-radius:12px;padding:14px 16px 14px 20px;background:var(--card);position:relative;overflow:hidden;}
.ret-stat::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:12px 0 0 12px;}
.ret-stat--amber::before{background:#f59e0b;}
.ret-stat--rose::before{background:#f43f5e;}
.ret-stat--blue::before{background:#3b82f6;}
.ret-stat__icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;margin-bottom:10px;}
.ret-stat--amber .ret-stat__icon{background:color-mix(in srgb,#f59e0b 14%,transparent);color:#d97706;}
.ret-stat--rose  .ret-stat__icon{background:color-mix(in srgb,#f43f5e 12%,transparent);color:#e11d48;}
.ret-stat--blue  .ret-stat__icon{background:color-mix(in srgb,#3b82f6 11%,transparent);color:#2563eb;}
.ret-stat__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:0 0 3px;}
.ret-stat__value{font-size:22px;font-weight:800;color:var(--text);line-height:1.2;margin:0;}

/* ── Return number badge ─────────────────────────────────────────── */
.ret-badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;border:1px solid color-mix(in srgb,#f59e0b 42%,var(--border));background:color-mix(in srgb,#f59e0b 11%,transparent);color:color-mix(in srgb,#b45309 80%,var(--text));white-space:nowrap;}

/* ── Refund method badges ────────────────────────────────────────── */
.ret-method{display:inline-block;font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px;}
.ret-method--cash{border:1px solid color-mix(in srgb,#10b981 40%,var(--border));background:color-mix(in srgb,#10b981 10%,transparent);color:color-mix(in srgb,#059669 80%,var(--text));}
.ret-method--credit{border:1px solid color-mix(in srgb,#3b82f6 40%,var(--border));background:color-mix(in srgb,#3b82f6 10%,transparent);color:color-mix(in srgb,#2563eb 80%,var(--text));}
.ret-method--none{border:1px solid var(--border);color:var(--muted);background:transparent;}
.ret-reason{display:inline-block;font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px;border:1px solid var(--border);color:var(--muted);background:transparent;}

/* ── Sub-rows (expanded items + notes) ──────────────────────────── */
.ret-sub-row td{background:color-mix(in srgb,var(--border) 12%,transparent) !important;padding:8px 10px !important;}
.ret-sub-row--last td{border-bottom:3px solid var(--border) !important;}
.ret-notes-row td{background:color-mix(in srgb,var(--border) 6%,transparent) !important;padding:4px 10px 9px !important;}
.ret-notes-row--last td{border-bottom:3px solid var(--border) !important;}

/* ── Main row hover ──────────────────────────────────────────────── */
.pcat-table tbody tr.ret-main-row:hover td{background:color-mix(in srgb,var(--primary) 5%,transparent);}

/* ── Empty state ─────────────────────────────────────────────────── */
.ret-empty{text-align:center;padding:36px 20px 44px;display:flex;flex-direction:column;align-items:center;gap:10px;}
.ret-empty__icon{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:22px;background:color-mix(in srgb,#f59e0b 13%,transparent);color:#d97706;margin-bottom:4px;}
.ret-empty__title{font-size:16px;font-weight:800;color:var(--text);margin:0;}
.ret-empty__desc{font-size:13px;color:var(--muted);max-width:360px;line-height:1.55;margin:0;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    {{-- ── Page header ──────────────────────────────────────────────── --}}
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px;">
        <p class="muted" style="margin:0;font-size:13px;line-height:1.45;">
            Processed returns for <strong style="color:var(--text);">{{ $business->name }}</strong>.
            Each return restores stock and records the refund method.
        </p>
        <a href="{{ route('pos.returns.create') }}"
           class="linkbtn" style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:7px;flex-shrink:0;">
            <i class="fa fa-rotate-left"></i> Create return note
        </a>
    </div>

    @if($hasReturns)
        {{-- ── Stat cards ───────────────────────────────────────────── --}}
        @php
            $totalReturnValue = $returns->sum(fn($r) => (float) $r->total);
            $totalReturnItems = $returns->sum(fn($r) => $r->items->count());
        @endphp
        <div class="ret-stats">
            <div class="ret-stat ret-stat--amber">
                <div class="ret-stat__icon"><i class="fa fa-rotate-left"></i></div>
                <p class="ret-stat__label">Returns (this page)</p>
                <p class="ret-stat__value">{{ $returns->count() }}</p>
            </div>
            <div class="ret-stat ret-stat--rose">
                <div class="ret-stat__icon"><i class="fa fa-money-bill-wave"></i></div>
                <p class="ret-stat__label">Total value @if(filled($currency))({{ $currency }})@endif</p>
                <p class="ret-stat__value">{{ number_format($totalReturnValue, 2) }}</p>
            </div>
            <div class="ret-stat ret-stat--blue">
                <div class="ret-stat__icon"><i class="fa fa-boxes-stacked"></i></div>
                <p class="ret-stat__label">Line items</p>
                <p class="ret-stat__value">{{ $totalReturnItems }}</p>
            </div>
        </div>

        {{-- ── Search / filter ─────────────────────────────────────── --}}
        <div class="pcat-toolbar" style="margin-bottom:14px;">
            <form method="get" action="{{ route('pos.returns.index') }}"
                  style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search return # or sale #…"
                       style="min-width:210px;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);">
                <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:13px;">Search</button>
                @if(filled($search))
                    <a href="{{ route('pos.returns.index') }}" class="pcat-link" style="font-size:13px;">Clear</a>
                @endif
            </form>
        </div>

        {{-- ── Returns table ───────────────────────────────────────── --}}
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Return #</th>
                        <th>Sale</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Reason</th>
                        <th>Refund</th>
                        <th>Processed by</th>
                        <th style="text-align:right;">Total @if(filled($currency))({{ $currency }})@endif</th>
                        <th style="text-align:right;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $ret)
                        @php $lastItem = $ret->items->last(); $hasNotes = filled($ret->notes); @endphp
                        <tr class="ret-main-row">
                            <td>
                                <span class="ret-badge">
                                    <i class="fa fa-rotate-left" style="font-size:10px;opacity:.7;"></i>
                                    {{ $ret->return_number }}
                                </span>
                            </td>
                            <td>
                                @if($ret->sale)
                                    <a href="{{ route('pos.sales.show', $ret->sale) }}" class="pcat-link" style="font-weight:700;">
                                        {{ $ret->sale->sale_number }}
                                    </a>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td class="muted" style="white-space:nowrap;font-size:12px;">
                                {{ $ret->returned_at?->format('M j, Y g:i A') ?? '—' }}
                            </td>
                            <td class="muted">{{ $ret->items->count() }}</td>
                            <td>
                                @if(filled($ret->refund_reason))
                                    <span class="ret-reason">{{ $ret->reasonLabel() }}</span>
                                @else
                                    <span class="muted" style="font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                @php $method = $ret->refund_method ?? 'none'; @endphp
                                <span class="ret-method ret-method--{{ $method }}">{{ $ret->refundMethodLabel() }}</span>
                            </td>
                            <td class="muted" style="font-size:12px;">{{ $ret->user?->name ?? '—' }}</td>
                            <td style="text-align:right;">
                                <strong style="color:var(--text);">{{ number_format((float) $ret->total, 2) }}</strong>
                            </td>
                            <td style="text-align:right;">
                                @if($ret->sale)
                                    <a href="{{ route('pos.sales.show', $ret->sale) }}#returns"
                                       class="pcat-link" style="font-size:12px;font-weight:700;white-space:nowrap;">
                                        View sale <i class="fa fa-arrow-up-right-from-square" style="font-size:10px;"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>

                        {{-- Expanded item rows --}}
                        @foreach($ret->items as $item)
                            @php $isLast = $loop->last && !$hasNotes; @endphp
                            <tr class="ret-sub-row {{ $isLast ? 'ret-sub-row--last' : '' }}">
                                <td style="padding-left:22px !important;" colspan="2">
                                    <i class="fa fa-corner-down-right" style="font-size:10px;opacity:.4;margin-right:5px;"></i>
                                    <strong style="color:var(--text);font-weight:600;">{{ $item->product_name }}</strong>
                                    @if(filled($item->sku))
                                        <span class="muted"> · {{ $item->sku }}</span>
                                    @endif
                                </td>
                                <td></td>
                                <td class="muted" style="font-size:12px;">
                                    {{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}
                                </td>
                                <td colspan="2" class="muted" style="font-size:12px;">
                                    @ {{ number_format((float) $item->unit_sell_price, 2) }}
                                </td>
                                <td></td>
                                <td style="text-align:right;font-weight:700;color:var(--text);">
                                    {{ number_format((float) $item->line_total, 2) }}
                                </td>
                                <td></td>
                            </tr>
                        @endforeach

                        @if($hasNotes)
                            <tr class="ret-notes-row ret-notes-row--last">
                                <td colspan="9" style="padding-left:22px !important;">
                                    <i class="fa fa-note-sticky" style="font-size:10px;opacity:.45;margin-right:6px;"></i>
                                    <span style="color:var(--muted);">{{ $ret->notes }}</span>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="9" style="padding:16px;">
                                <p class="muted" style="margin:0;font-size:13px;">No returns match your search.</p>
                            </td>
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

    @else
        {{-- ── Empty state ─────────────────────────────────────────── --}}
        <section class="pcat-inline">
            <div class="ret-empty">
                <div class="ret-empty__icon"><i class="fa fa-rotate-left"></i></div>
                <p class="ret-empty__title">No returns yet</p>
                <p class="ret-empty__desc">
                    Process a return to restore stock and record the refund method.
                    You can return against an existing sale or enter products directly.
                </p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:6px;">
                    <a href="{{ route('pos.returns.create') }}" class="linkbtn"
                       style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;">
                        <i class="fa fa-rotate-left"></i> Create return note
                    </a>
                    <a href="{{ route('pos.sales.index') }}" class="linkbtn"
                       style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                        <i class="fa fa-receipt"></i> Sales history
                    </a>
                </div>
            </div>
        </section>
    @endif
</div>
@endsection
