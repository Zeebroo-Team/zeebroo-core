@extends('theme::layouts.app', ['title' => 'Sales Orders', 'heading' => 'Sales Orders'])

@php
    $hasOrders        = $hasOrders ?? false;
    $hasActiveFilters = filled($search ?? '') || ($statusFilter ?? 'all') !== 'all';
    $modalOpen        = $hasOrders && $errors->any();
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.so-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);}
.so-status--pending    {border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);}
.so-status--confirmed  {border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);}
.so-status--processing {border-color:color-mix(in srgb,#8b5cf6 45%,var(--border));background:color-mix(in srgb,#8b5cf6 12%,transparent);}
.so-status--completed  {border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.so-status--cancelled  {border-color:color-mix(in srgb,#6b7280 45%,var(--border));background:color-mix(in srgb,#6b7280 12%,transparent);opacity:.8;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('sales::partials.sales-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('order'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('order') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Track customer orders for <strong style="color:var(--text);">{{ $business->name }}</strong> from pending through to fulfilment.
        Confirming an order converts it into an invoice and deducts stock.
    </p>

    @if($hasOrders)
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;align-items:center;">
        @foreach($statusTabs as $key => $label)
            <a href="{{ route('sales.orders.index', array_merge(request()->query(), ['status' => $key, 'page' => null])) }}"
               style="padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid var(--border);
                      {{ ($statusFilter ?? 'all') === $key ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'background:transparent;color:var(--muted);' }}">
                {{ $label }}
            </a>
        @endforeach

        <form method="GET" action="{{ route('sales.orders.index') }}"
              style="margin-left:auto;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="status" value="{{ $statusFilter ?? 'all' }}">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search…"
                   style="padding:5px 10px;border-radius:8px;border:1px solid var(--border);font-size:12px;background:var(--card);color:var(--text);width:160px;">
            <button type="submit" class="linkbtn" style="padding:5px 12px;font-size:12px;">Filter</button>
            @if($hasActiveFilters)
                <a href="{{ route('sales.orders.index') }}" class="linkbtn"
                   style="padding:5px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--muted);">Clear</a>
            @endif
        </form>
    </div>
    @endif

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if(!$hasOrders) Create your <strong style="color:var(--text);">first order</strong> below. @endif
        </span>
        @if($hasOrders)
            <button type="button" id="so-modal-open" class="linkbtn"
                    style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> New order
            </button>
        @endif
    </div>

    @if(!$hasOrders)
        <section class="pcat-inline">
            <h2>New order</h2>
            <p class="pcat-muted">Draft an order, confirm it to convert into an invoice and reserve stock.</p>
            @include('sales::orders.partials.create-form')
        </section>

    @else
        @if($orders->isEmpty())
            <p class="muted" style="margin:24px 0;font-size:13px;">
                @if($hasActiveFilters)
                    No orders match your filters. <a href="{{ route('sales.orders.index') }}" class="pcat-link">Clear filters</a>
                @else
                    No orders found.
                @endif
            </p>
        @else
            <div class="pcat-table-wrap">
                <table class="pcat-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Order date</th>
                            <th>Expected delivery</th>
                            <th style="text-align:right;">Total{{ $currency ? ' ('.$currency.')' : '' }}</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $o)
                            <tr>
                                <td>
                                    <strong style="color:var(--text);">{{ $o->order_number ?? '—' }}</strong>
                                    @if($o->reference)
                                        <div class="muted" style="font-size:11px;">Ref: {{ $o->reference }}</div>
                                    @endif
                                </td>
                                <td>{{ $o->customer?->name ?? '—' }}</td>
                                <td>{{ $o->order_date->format('M j, Y') }}</td>
                                <td>
                                    @if($o->expected_delivery_date)
                                        {{ $o->expected_delivery_date->format('M j, Y') }}
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td style="text-align:right;font-weight:700;">
                                    {{ number_format((float) $o->total, 2) }}
                                </td>
                                <td>
                                    <span class="so-status so-status--{{ $o->status }}">{{ $o->statusLabel() }}</span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="{{ route('sales.orders.show', $o) }}" class="pcat-link">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div id="so-modal"
             class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}"
             role="dialog" aria-modal="true" aria-labelledby="so-modal-title"
             aria-hidden="{{ $modalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-so-modal-close tabindex="-1"></div>
            <div class="pcat-modal__panel" style="max-width:min(94vw,900px);">
                <div class="pcat-modal__head">
                    <h2 id="so-modal-title">New order</h2>
                    <button type="button" class="pcat-modal__close" data-so-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @include('sales::orders.partials.create-form')
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

@if($hasOrders)
<script>
(function () {
    var modal   = document.getElementById('so-modal');
    var openBtn = document.getElementById('so-modal-open');
    function lock(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openM()  { modal.classList.add('pcat-modal--open'); modal.setAttribute('aria-hidden','false'); lock(true); }
    function closeM() { modal.classList.remove('pcat-modal--open'); modal.setAttribute('aria-hidden','true'); lock(false); openBtn?.focus(); }
    openBtn?.addEventListener('click', openM);
    modal?.querySelectorAll('[data-so-modal-close]').forEach(el => el.addEventListener('click', closeM));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal?.classList.contains('pcat-modal--open')) closeM(); });
    if (modal?.classList.contains('pcat-modal--open')) lock(true);
})();
</script>
@endif
@endsection
