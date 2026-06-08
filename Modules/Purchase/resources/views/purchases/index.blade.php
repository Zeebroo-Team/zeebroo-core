@extends('theme::layouts.app', ['title' => 'Purchase orders', 'heading' => 'Purchase orders'])

@php
    $hasPurchases = $hasPurchases ?? false;
    $hasActiveFilters = filled($search ?? '') || ($statusFilter ?? 'all') !== 'all' || filled($supplierFilter ?? null);
    $purchaseModalOpen = $hasPurchases && $errors->any();
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.purchase-lines-table select,.purchase-lines-table input{width:100%;box-sizing:border-box;padding:7px 8px;font-size:12px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.purchase-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);}
.purchase-status--draft{opacity:.85;}
.purchase-status--ordered{border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);}
.purchase-status--partially_received{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);}
.purchase-status--received{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.purchase-status--cancelled{border-color:color-mix(in srgb,#94a3b8 45%,var(--border));opacity:.75;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('purchase::partials.purchase-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('purchase'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('purchase') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Create and track purchase orders for <strong style="color:var(--text);">{{ $business->name }}</strong>. Mark as <strong>received</strong> when goods arrive to update inventory.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if(!$hasPurchases)
                Create your <strong style="color:var(--text);">first purchase order</strong> below.
            @endif
        </span>
        @if($hasPurchases)
            <button type="button" id="purchase-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-plus"></i> New purchase order</button>
        @endif
    </div>

    @if($products->isEmpty())
        <div class="pcat-banner pcat-banner--err" style="margin-bottom:12px;" role="alert">
            Add at least one <a href="{{ route('product.index') }}" class="pcat-link">product</a> before creating purchase orders.
        </div>
    @endif

    @if(!$hasPurchases)
        <section class="pcat-inline">
            <h2>New purchase order</h2>
            <p class="pcat-muted">Draft to plan or mark as ordered when sent to the supplier. Record goods and payment on a goods receive note.</p>
            @include('purchase::purchases.partials.create-form', [
                'showPurchaseCreateErrorBanner' => true,
                'currency' => $currency,
                'products' => $products,
                'suppliers' => $suppliers,
                'branchStockSeparate' => $branchStockSeparate,
                'branchOptions' => $branchOptions,
            ])
        </section>
    @else
        @include('purchase::purchases.partials.index-filters', [
            'search' => $search,
            'statusFilter' => $statusFilter,
            'supplierFilter' => $supplierFilter,
            'statusTabs' => $statusTabs,
            'suppliers' => $suppliers,
            'purchases' => $purchases,
        ])

        @if($purchases->isEmpty())
            <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
                @if($hasActiveFilters)
                    No purchase orders match your search or filters. <a href="{{ route('purchase.index') }}" class="pcat-link">Clear filters</a>
                @else
                    No purchase orders found.
                @endif
            </p>
        @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>PO #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Lines</th>
                        <th>Total @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchases as $row)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $row->po_number ?? '—' }}</strong></td>
                            <td>{{ $row->purchase_date->format('M j, Y') }}</td>
                            <td>{{ $row->supplier?->name ?? '—' }}</td>
                            <td class="muted">{{ (int) $row->items_count }}</td>
                            <td><strong style="color:var(--text);">{{ number_format((float) $row->total, 2) }}</strong></td>
                            <td>
                                <span class="purchase-status purchase-status--{{ $row->status }}">{{ $row->statusLabel() }}</span>
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('purchase.show', $row) }}" class="pcat-link"><i class="fa fa-eye"></i> View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div id="purchase-modal" class="pcat-modal {{ $purchaseModalOpen ? 'pcat-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="purchase-modal-title" aria-hidden="{{ $purchaseModalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-purchase-modal-close tabindex="-1"></div>
            <div class="pcat-modal__panel" style="max-width:min(94vw,900px);">
                <div class="pcat-modal__head">
                    <h2 id="purchase-modal-title">New purchase order</h2>
                    <button type="button" class="pcat-modal__close" data-purchase-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @include('purchase::purchases.partials.create-form', [
                        'showPurchaseCreateErrorBanner' => true,
                        'currency' => $currency,
                        'products' => $products,
                        'suppliers' => $suppliers,
                        'branchStockSeparate' => $branchStockSeparate,
                        'branchOptions' => $branchOptions,
                    ])
                </div>
            </div>
        </div>
    @endif

    @include('purchase::suppliers.partials.quick-add-modal')
</div>

<div style="margin-top:14px;">
    <a href="{{ route('dashboard') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>

@if($hasPurchases)
<script>
(function () {
    var modal = document.getElementById('purchase-modal');
    var openBtn = document.getElementById('purchase-modal-open');
    function lock(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openM() {
        if (!modal) return;
        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lock(true);
    }
    function closeM() {
        if (!modal) return;
        modal.classList.remove('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lock(false);
        openBtn?.focus();
    }
    openBtn?.addEventListener('click', openM);
    modal?.querySelectorAll('[data-purchase-modal-close]').forEach(function (el) { el.addEventListener('click', closeM); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal?.classList.contains('pcat-modal--open')) closeM();
    });
    if (modal?.classList.contains('pcat-modal--open')) lock(true);
})();
</script>
@endif
@endsection
