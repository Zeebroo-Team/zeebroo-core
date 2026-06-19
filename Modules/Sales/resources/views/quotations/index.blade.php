@extends('theme::layouts.app', ['title' => 'Quotations', 'heading' => 'Quotations'])

@php
    $hasQuotations    = $hasQuotations ?? false;
    $hasActiveFilters = filled($search ?? '') || ($statusFilter ?? 'all') !== 'all' || filled($customerFilter ?? null);
    $modalOpen        = $hasQuotations && $errors->any();
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.qt-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);}
.qt-status--draft    {opacity:.8;}
.qt-status--sent     {border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);}
.qt-status--accepted {border-color:color-mix(in srgb,#10b981 45%,var(--border));background:color-mix(in srgb,#10b981 12%,transparent);}
.qt-status--rejected {border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 12%,transparent);opacity:.8;}
.qt-status--expired  {border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('sales::partials.sales-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('quotation'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('quotation') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Create and send sales quotations for <strong style="color:var(--text);">{{ $business->name }}</strong>.
        Track each quote from draft through to accepted or rejected.
    </p>

    {{-- Status tabs + search filter --}}
    @if($hasQuotations)
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;align-items:center;">
        @foreach($statusTabs as $key => $label)
            <a href="{{ route('sales.quotations.index', array_merge(request()->query(), ['status' => $key, 'page' => null])) }}"
               style="padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid var(--border);
                      {{ ($statusFilter ?? 'all') === $key ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'background:transparent;color:var(--muted);' }}">
                {{ $label }}
            </a>
        @endforeach

        <form method="GET" action="{{ route('sales.quotations.index') }}"
              style="margin-left:auto;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="status" value="{{ $statusFilter ?? 'all' }}">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search…"
                   style="padding:5px 10px;border-radius:8px;border:1px solid var(--border);font-size:12px;background:var(--card);color:var(--text);width:160px;">
            <select name="customer_id"
                    style="padding:5px 10px;border-radius:8px;border:1px solid var(--border);font-size:12px;background:var(--card);color:var(--text);cursor:pointer;">
                <option value="">All customers</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}" @selected($customerFilter == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="linkbtn" style="padding:5px 12px;font-size:12px;">Filter</button>
            @if($hasActiveFilters)
                <a href="{{ route('sales.quotations.index') }}" class="linkbtn"
                   style="padding:5px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--muted);">Clear</a>
            @endif
        </form>
    </div>
    @endif

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if(!$hasQuotations) Create your <strong style="color:var(--text);">first quotation</strong> below. @endif
        </span>
        @if($hasQuotations)
            <button type="button" id="qt-modal-open" class="linkbtn"
                    style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> New quotation
            </button>
        @endif
    </div>



    {{-- Inline create when no quotations yet --}}
    @if(!$hasQuotations)
        <section class="pcat-inline">
            <h2>New quotation</h2>
            <p class="pcat-muted">Draft a quote, send it to the customer, then mark it accepted or rejected.</p>
            @include('sales::quotations.partials.create-form')
        </section>

    @else
        {{-- Quotations table --}}
        @if($quotations->isEmpty())
            <p class="muted" style="margin:24px 0;font-size:13px;">
                @if($hasActiveFilters)
                    No quotations match your filters. <a href="{{ route('sales.quotations.index') }}" class="pcat-link">Clear filters</a>
                @else
                    No quotations found.
                @endif
            </p>
        @else
            <div class="pcat-table-wrap">
                <table class="pcat-table">
                    <thead>
                        <tr>
                            <th>Quote #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Expiry</th>
                            <th style="text-align:right;">Total{{ $currency ? ' ('.$currency.')' : '' }}</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quotations as $q)
                            <tr>
                                <td>
                                    <strong style="color:var(--text);">{{ $q->quote_number ?? '—' }}</strong>
                                    @if($q->reference)
                                        <div class="muted" style="font-size:11px;">Ref: {{ $q->reference }}</div>
                                    @endif
                                </td>
                                <td>{{ $q->customer?->name ?? '—' }}</td>
                                <td>{{ $q->quote_date->format('M j, Y') }}</td>
                                <td>
                                    @if($q->expiry_date)
                                        <span style="{{ $q->isExpired() ? 'color:#f59e0b;font-weight:700;' : '' }}">
                                            {{ $q->expiry_date->format('M j, Y') }}
                                        </span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td style="text-align:right;font-weight:700;">
                                    {{ number_format((float) $q->total, 2) }}
                                </td>
                                <td>
                                    <span class="qt-status qt-status--{{ $q->status }}">{{ $q->statusLabel() }}</span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="{{ route('sales.quotations.show', $q) }}" class="pcat-link">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- New quotation modal --}}
        <div id="qt-modal"
             class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}"
             role="dialog" aria-modal="true" aria-labelledby="qt-modal-title"
             aria-hidden="{{ $modalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-qt-modal-close tabindex="-1"></div>
            <div class="pcat-modal__panel" style="max-width:min(94vw,900px);">
                <div class="pcat-modal__head">
                    <h2 id="qt-modal-title">New quotation</h2>
                    <button type="button" class="pcat-modal__close" data-qt-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @include('sales::quotations.partials.create-form')
                </div>
            </div>
        </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('dashboard') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>

@if($hasQuotations)
<script>
(function () {
    var modal  = document.getElementById('qt-modal');
    var openBtn = document.getElementById('qt-modal-open');
    function lock(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openM() { modal.classList.add('pcat-modal--open'); modal.setAttribute('aria-hidden','false'); lock(true); }
    function closeM() { modal.classList.remove('pcat-modal--open'); modal.setAttribute('aria-hidden','true'); lock(false); openBtn?.focus(); }
    openBtn?.addEventListener('click', openM);
    modal?.querySelectorAll('[data-qt-modal-close]').forEach(el => el.addEventListener('click', closeM));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal?.classList.contains('pcat-modal--open')) closeM(); });
    if (modal?.classList.contains('pcat-modal--open')) lock(true);
})();
</script>
@endif
@endsection
