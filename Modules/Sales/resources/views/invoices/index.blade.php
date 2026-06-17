@extends('theme::layouts.app', ['title' => 'Invoices', 'heading' => 'Sales Invoices'])

@php
    $hasInvoices      = $hasInvoices ?? false;
    $hasActiveFilters = filled($search ?? '') || ($statusFilter ?? 'all') !== 'all' || filled($customerFilter ?? null);
    $modalOpen        = $hasInvoices && $errors->any();
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.inv-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);}
.inv-status--draft     {opacity:.8;}
.inv-status--sent      {border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);}
.inv-status--paid      {border-color:color-mix(in srgb,#10b981 45%,var(--border));background:color-mix(in srgb,#10b981 12%,transparent);}
.inv-status--overdue   {border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 12%,transparent);}
.inv-status--cancelled {border-color:color-mix(in srgb,#94a3b8 45%,var(--border));background:color-mix(in srgb,#94a3b8 12%,transparent);opacity:.8;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('sales::partials.sales-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('invoice'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('invoice') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Create and send sales invoices for <strong style="color:var(--text);">{{ $business->name }}</strong>.
        Track each invoice from draft through to paid.
    </p>

    {{-- Status tabs + search filter --}}
    @if($hasInvoices)
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;align-items:center;">
        @foreach($statusTabs as $key => $label)
            <a href="{{ route('sales.invoices.index', array_merge(request()->query(), ['status' => $key, 'page' => null])) }}"
               style="padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid var(--border);
                      {{ ($statusFilter ?? 'all') === $key ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'background:transparent;color:var(--muted);' }}">
                {{ $label }}
            </a>
        @endforeach

        <form method="GET" action="{{ route('sales.invoices.index') }}"
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
                <a href="{{ route('sales.invoices.index') }}" class="linkbtn"
                   style="padding:5px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--muted);">Clear</a>
            @endif
        </form>
    </div>
    @endif

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if(!$hasInvoices) Create your <strong style="color:var(--text);">first invoice</strong> below. @endif
        </span>
        @if($hasInvoices)
            <button type="button" id="inv-modal-open" class="linkbtn"
                    style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> New invoice
            </button>
        @endif
    </div>


    {{-- Inline create when no invoices yet --}}
    @if(!$hasInvoices)
        <section class="pcat-inline">
            <h2>New invoice</h2>
            <p class="pcat-muted">Draft an invoice, send it to the customer, then mark it paid when payment is received.</p>
            @include('sales::invoices.partials.create-form')
        </section>

    @else
        {{-- Invoices table --}}
        @if($invoices->isEmpty())
            <p class="muted" style="margin:24px 0;font-size:13px;">
                @if($hasActiveFilters)
                    No invoices match your filters. <a href="{{ route('sales.invoices.index') }}" class="pcat-link">Clear filters</a>
                @else
                    No invoices found.
                @endif
            </p>
        @else
            <div class="pcat-table-wrap">
                <table class="pcat-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Issue date</th>
                            <th>Due date</th>
                            <th style="text-align:right;">Total{{ $currency ? ' ('.$currency.')' : '' }}</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $inv)
                            <tr>
                                <td>
                                    <strong style="color:var(--text);">{{ $inv->invoice_number ?? '—' }}</strong>
                                    @if($inv->reference)
                                        <div class="muted" style="font-size:11px;">Ref: {{ $inv->reference }}</div>
                                    @endif
                                </td>
                                <td>{{ $inv->customer?->name ?? '—' }}</td>
                                <td>{{ $inv->issue_date->format('M j, Y') }}</td>
                                <td>
                                    @if($inv->due_date)
                                        <span style="{{ $inv->isPaymentDue() ? 'color:#ef4444;font-weight:700;' : '' }}">
                                            {{ $inv->due_date->format('M j, Y') }}
                                        </span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td style="text-align:right;font-weight:700;">
                                    {{ number_format((float) $inv->total, 2) }}
                                </td>
                                <td>
                                    <span class="inv-status inv-status--{{ $inv->status }}">{{ $inv->statusLabel() }}</span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="{{ route('sales.invoices.show', $inv) }}" class="pcat-link">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- New invoice modal --}}
        <div id="inv-modal"
             class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}"
             role="dialog" aria-modal="true" aria-labelledby="inv-modal-title"
             aria-hidden="{{ $modalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-inv-modal-close tabindex="-1"></div>
            <div class="pcat-modal__panel" style="max-width:min(94vw,900px);">
                <div class="pcat-modal__head">
                    <h2 id="inv-modal-title">New invoice</h2>
                    <button type="button" class="pcat-modal__close" data-inv-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @include('sales::invoices.partials.create-form')
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

@if($hasInvoices)
<script>
(function () {
    var modal   = document.getElementById('inv-modal');
    var openBtn = document.getElementById('inv-modal-open');
    function lock(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openM()  { modal.classList.add('pcat-modal--open'); modal.setAttribute('aria-hidden','false'); lock(true); }
    function closeM() { modal.classList.remove('pcat-modal--open'); modal.setAttribute('aria-hidden','true'); lock(false); openBtn?.focus(); }
    openBtn?.addEventListener('click', openM);
    modal?.querySelectorAll('[data-inv-modal-close]').forEach(el => el.addEventListener('click', closeM));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal?.classList.contains('pcat-modal--open')) closeM(); });
    if (modal?.classList.contains('pcat-modal--open')) lock(true);
})();
</script>
@endif
@endsection
