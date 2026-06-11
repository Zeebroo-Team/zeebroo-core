@extends('theme::layouts.app', ['title' => 'Stock Audits', 'heading' => 'Stock Audits'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
/* ── Stat cards ─────────────────────────────────────────────────── */
.sa-stats{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px;}
.sa-stat{flex:1;min-width:150px;border:1px solid var(--border);border-radius:12px;padding:14px 16px 14px 20px;background:var(--card);position:relative;overflow:hidden;}
.sa-stat::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:12px 0 0 12px;}
.sa-stat--blue::before{background:#3b82f6;}
.sa-stat--amber::before{background:#f59e0b;}
.sa-stat--green::before{background:#10b981;}
.sa-stat__icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;margin-bottom:10px;}
.sa-stat--blue  .sa-stat__icon{background:color-mix(in srgb,#3b82f6 13%,transparent);color:#2563eb;}
.sa-stat--amber .sa-stat__icon{background:color-mix(in srgb,#f59e0b 13%,transparent);color:#d97706;}
.sa-stat--green .sa-stat__icon{background:color-mix(in srgb,#10b981 13%,transparent);color:#059669;}
.sa-stat__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:0 0 3px;}
.sa-stat__value{font-size:22px;font-weight:800;color:var(--text);line-height:1.2;margin:0;}

/* ── Status badges ───────────────────────────────────────────────── */
.sa-badge{display:inline-block;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;}
.sa-badge--open     {border:1px solid color-mix(in srgb,#3b82f6 42%,var(--border));background:color-mix(in srgb,#3b82f6 11%,transparent);color:color-mix(in srgb,#2563eb 80%,var(--text));}
.sa-badge--finalized{border:1px solid color-mix(in srgb,#10b981 42%,var(--border));background:color-mix(in srgb,#10b981 11%,transparent);color:color-mix(in srgb,#059669 80%,var(--text));}

/* ── Audit number badge ──────────────────────────────────────────── */
.sa-num{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 38%,var(--border));background:color-mix(in srgb,var(--primary) 9%,transparent);color:var(--text);white-space:nowrap;}

/* ── Progress bar ────────────────────────────────────────────────── */
.sa-progress{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);}
.sa-progress__bar{flex:1;height:6px;border-radius:999px;background:color-mix(in srgb,var(--border) 60%,transparent);min-width:60px;overflow:hidden;}
.sa-progress__fill{height:100%;border-radius:999px;background:var(--primary);transition:width .3s;}

/* ── Empty state ─────────────────────────────────────────────────── */
.sa-empty{text-align:center;padding:36px 20px 44px;display:flex;flex-direction:column;align-items:center;gap:10px;}
.sa-empty__icon{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:22px;background:color-mix(in srgb,#3b82f6 13%,transparent);color:#2563eb;margin-bottom:4px;}
.sa-empty__title{font-size:16px;font-weight:800;color:var(--text);margin:0;}
.sa-empty__desc{font-size:13px;color:var(--muted);max-width:380px;line-height:1.55;margin:0;}

/* ── Main row hover ──────────────────────────────────────────────── */
.pcat-table tbody tr:hover td{background:color-mix(in srgb,var(--primary) 5%,transparent);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    {{-- ── Page header ──────────────────────────────────────────────── --}}
    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Periodically count your physical stock and compare it to the system's expected quantities.
        Finalizing an audit updates product stock to match your physical count.
    </p>
    <div class="pcat-toolbar" style="margin-bottom:18px;">
        <span></span>
        <a href="{{ route('pos.stock-audits.create') }}"
           class="linkbtn" style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
            <i class="fa fa-plus"></i> New audit
        </a>
    </div>

    @if($hasAudits)
        {{-- ── Stat cards ───────────────────────────────────────────── --}}
        @php
            $allItems   = $audits->getCollection();
            $openCount  = $allItems->where('status', 'open')->count();
            $doneCount  = $allItems->where('status', 'finalized')->count();
        @endphp
        <div class="sa-stats">
            <div class="sa-stat sa-stat--blue">
                <div class="sa-stat__icon"><i class="fa fa-clipboard-list"></i></div>
                <p class="sa-stat__label">Total audits (page)</p>
                <p class="sa-stat__value">{{ $audits->total() }}</p>
            </div>
            <div class="sa-stat sa-stat--amber">
                <div class="sa-stat__icon"><i class="fa fa-hourglass-half"></i></div>
                <p class="sa-stat__label">Open</p>
                <p class="sa-stat__value">{{ $openCount }}</p>
            </div>
            <div class="sa-stat sa-stat--green">
                <div class="sa-stat__icon"><i class="fa fa-check-circle"></i></div>
                <p class="sa-stat__label">Finalized</p>
                <p class="sa-stat__value">{{ $doneCount }}</p>
            </div>
        </div>

        {{-- ── Audits table ─────────────────────────────────────────── --}}
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Audit #</th>
                        <th>Date</th>
                        <th>Products</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Created by</th>
                        <th>Finalized</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($audits as $audit)
                        @php
                            $total   = $audit->lines_count ?? 0;
                            $counted = 0; // loaded lazily below via relation count workaround
                        @endphp
                        <tr>
                            <td>
                                <span class="sa-num">
                                    <i class="fa fa-clipboard-check" style="font-size:10px;opacity:.7;"></i>
                                    {{ $audit->audit_number }}
                                </span>
                            </td>
                            <td style="white-space:nowrap;font-size:13px;">{{ $audit->audit_date->format('M j, Y') }}</td>
                            <td class="muted">{{ $total }}</td>
                            <td>
                                @if($total > 0)
                                    <span class="muted" style="font-size:12px;">{{ $total }} product{{ $total !== 1 ? 's' : '' }}</span>
                                @else
                                    <span class="muted" style="font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="sa-badge sa-badge--{{ $audit->status }}">{{ $audit->statusLabel() }}</span>
                            </td>
                            <td class="muted" style="font-size:12px;">{{ $audit->createdBy?->name ?? '—' }}</td>
                            <td class="muted" style="font-size:12px;white-space:nowrap;">
                                {{ $audit->finalized_at?->format('M j, Y') ?? '—' }}
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <a href="{{ route('pos.stock-audits.show', $audit) }}"
                                   class="linkbtn"
                                   style="padding:6px 12px;font-size:12px;display:inline-flex;align-items:center;gap:5px;
                                          {{ $audit->isFinalized()
                                              ? 'background:transparent;border:1px solid var(--border);color:var(--text);'
                                              : 'background:color-mix(in srgb,var(--primary) 13%,transparent);border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));color:var(--text);' }}
                                          text-decoration:none;">
                                    @if($audit->isFinalized())
                                        <i class="fa fa-file-lines" style="font-size:11px;opacity:.7;"></i> View report
                                    @else
                                        <i class="fa fa-clipboard-list" style="font-size:11px;opacity:.7;"></i> Continue counting
                                    @endif
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted" style="padding:16px;font-size:13px;">No audits found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($audits->hasPages())
            <div style="margin-top:14px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;font-size:13px;">
                {!! $audits->withQueryString()->links() !!}
            </div>
        @endif

    @else
        {{-- ── Empty state ─────────────────────────────────────────── --}}
        <section class="pcat-inline">
            <div class="sa-empty">
                <div class="sa-empty__icon"><i class="fa fa-clipboard-list"></i></div>
                <p class="sa-empty__title">No stock audits yet</p>
                <p class="sa-empty__desc">
                    A stock audit lets you count your physical inventory and reconcile it with the
                    system's expected quantities. Start your first audit to spot discrepancies.
                </p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:6px;">
                    <a href="{{ route('pos.stock-audits.create') }}" class="linkbtn"
                       style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;">
                        <i class="fa fa-plus"></i> Start first audit
                    </a>
                </div>
            </div>
        </section>
    @endif
</div>
@endsection
