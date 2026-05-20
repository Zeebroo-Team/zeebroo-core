@extends('theme::layouts.app', ['title' => 'Goods receive notes', 'heading' => 'Goods receive notes'])

@section('content')
@php
    $activeTab = $activeTab ?? 'grouped';
    $hasGrns = $hasGrns ?? false;
    $hasActiveFilters = filled($search ?? '') || ($paymentFilter ?? 'all') !== 'all' || filled($supplierFilter ?? null);
    $grnFilterParams = array_filter([
        'q' => filled($search ?? '') ? $search : null,
        'payment' => ($paymentFilter ?? 'all') !== 'all' ? $paymentFilter : null,
        'supplier_id' => filled($supplierFilter ?? null) ? $supplierFilter : null,
    ], fn ($value) => $value !== null && $value !== '');
    $grnIndexGroupedUrl = route('purchase.grn.index', array_merge($grnFilterParams, ['view' => 'grouped']));
    $grnIndexAllUrl = route('purchase.grn.index', array_merge($grnFilterParams, ['view' => 'all']));
@endphp
@include('product::partials.catalog-hub-styles')
@include('purchase::goods-receive.partials.grn-payment-styles')
<style>
.grn-po-groups{display:flex;flex-direction:column;gap:6px;}
.grn-po-group{
    margin:0;border:1px solid color-mix(in srgb,var(--border) 88%,transparent);
    border-radius:8px;overflow:hidden;background:var(--card);
}
.grn-po-group[open]{border-color:color-mix(in srgb,var(--primary) 28%,var(--border));}
.grn-po-group__head{
    display:flex;align-items:center;gap:8px;
    padding:8px 10px;cursor:pointer;list-style:none;
    background:color-mix(in srgb,var(--card) 94%,var(--primary) 6%);
    user-select:none;
}
.grn-po-group__head::-webkit-details-marker{display:none;}
.grn-po-group__chev{
    flex-shrink:0;width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;
    font-size:10px;color:var(--muted);transition:transform .15s ease;
}
.grn-po-group[open] .grn-po-group__chev{transform:rotate(90deg);}
.grn-po-group__summary-main{flex:1;min-width:0;}
.grn-po-group__title-row{display:flex;flex-wrap:wrap;align-items:center;gap:6px;font-size:13px;line-height:1.3;}
.grn-po-group__po-link{font-weight:800;color:var(--text);text-decoration:none;}
.grn-po-group__po-link:hover{text-decoration:underline;}
.grn-po-group__count{font-size:10px;font-weight:700;padding:2px 6px;border-radius:999px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--muted);}
.grn-po-group__meta{display:block;margin-top:2px;font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.grn-po-group__actions{display:flex;gap:4px;flex-shrink:0;}
.grn-po-group__btn{
    display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;
    border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);
    font-size:11px;text-decoration:none;
}
.grn-po-group__btn:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));color:var(--primary);}
.grn-po-group__btn--primary{background:color-mix(in srgb,var(--primary) 14%,var(--card));border-color:color-mix(in srgb,var(--primary) 35%,var(--border));}
.grn-po-group__body{
    border-top:1px solid color-mix(in srgb,var(--border) 75%,transparent);
    padding:6px 10px 8px 32px;
}
.grn-po-group__table-wrap{border:0;border-radius:0;margin-left:0;}
.grn-po-group__table{font-size:11px;}
.grn-po-group__table th,.grn-po-group__table td{padding:6px 10px;}
.grn-po-group__table th:first-child,.grn-po-group__table td:first-child{padding-left:14px;}
.grn-po-group__table thead th{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);background:color-mix(in srgb,var(--card) 96%,transparent);}
.grn-po-group__grn-link{font-weight:700;color:var(--text);text-decoration:none;font-size:12px;}
.grn-po-group__grn-link:hover{color:var(--primary);}
.grn-po-group__num{font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap;}
.grn-po-group__act{text-align:right;width:32px;}
.grn-po-group__empty{margin:0;padding:4px 0 2px;font-size:11px;color:var(--muted);}
.purchase-status{font-size:9px;font-weight:700;padding:1px 5px;border-radius:999px;border:1px solid var(--border);vertical-align:middle;}
.purchase-status--draft{opacity:.85;}
.purchase-status--ordered{border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 10%,transparent);}
.purchase-status--partially_received{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 10%,transparent);}
.purchase-status--received{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);}
.purchase-status--cancelled{opacity:.75;}
.grn-index-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;}
.grn-index-toolbar__actions{display:flex;gap:6px;}
.grn-index-toolbar__btn{padding:5px 10px;font-size:11px;}
.grn-pay-status--dense{flex-direction:row;align-items:center;gap:4px;}
.grn-pay-status--dense .grn-pay-status__badge{font-size:9px;padding:2px 6px;}
.grn-pay-status--dense .grn-pay-status__badge i{display:none;}
.grn-pay-status--dense .grn-pay-status__amounts{gap:4px;}
.grn-pay-status--dense .grn-pay-status__chip{padding:1px 5px;font-size:10px;}
.grn-pay-status--dense .grn-pay-status__chip-label{display:none;}
.grn-pay-status--dense .grn-pay-status__currency{display:none;}
.grn-pay-status--dense .grn-pay-status__amounts--partial-summary .grn-pay-status__chip-label{display:inline;font-size:9px;}
.grn-pay-status--dense .grn-pay-status__amounts--partial-summary .grn-pay-status__currency{display:inline;font-size:9px;}
.grn-index-tabs{
    display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px;padding:0;
    border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);
}
.grn-index-tabs__tab{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 14px 10px;margin:0 0 -1px;
    font-size:12px;font-weight:700;color:var(--muted);text-decoration:none;
    border:1px solid transparent;border-bottom:none;border-radius:8px 8px 0 0;
    background:transparent;
}
.grn-index-tabs__tab:hover{color:var(--text);border-color:color-mix(in srgb,var(--border) 70%,transparent);background:color-mix(in srgb,var(--card) 90%,transparent);}
.grn-index-tabs__tab.is-active{
    color:var(--text);
    border-color:color-mix(in srgb,var(--primary) 30%,var(--border));
    background:color-mix(in srgb,var(--primary) 8%,var(--card));
}
.grn-index-tabs__count{
    font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;
    background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--muted);
}
.grn-index-tabs__tab.is-active .grn-index-tabs__count{color:var(--text);}
.grn-index-panel[hidden]{display:none !important;}
.grn-all-table{font-size:12px;}
.grn-all-table th,.grn-all-table td{padding:8px 12px;}
.grn-all-table-wrap{margin-top:0;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('purchase::partials.purchase-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 10px;font-size:12px;line-height:1.4;">
        <strong style="color:var(--text);">{{ $business->name }}</strong>
    </p>

    <nav class="grn-index-tabs" aria-label="Goods receive view">
        <a href="{{ $grnIndexGroupedUrl }}" class="grn-index-tabs__tab @if($activeTab === 'grouped') is-active @endif">
            <i class="fa fa-layer-group" aria-hidden="true"></i>
            By purchase order
            <span class="grn-index-tabs__count">{{ $purchaseGroups->count() }}</span>
        </a>
        <a href="{{ $grnIndexAllUrl }}" class="grn-index-tabs__tab @if($activeTab === 'all') is-active @endif">
            <i class="fa fa-list" aria-hidden="true"></i>
            All GRNs
            <span class="grn-index-tabs__count">{{ $notes->count() }}</span>
        </a>
    </nav>

    @if($hasGrns)
        @include('purchase::goods-receive.partials.index-filters', [
            'search' => $search,
            'paymentFilter' => $paymentFilter,
            'supplierFilter' => $supplierFilter,
            'paymentTabs' => $paymentTabs,
            'suppliers' => $suppliers,
            'notes' => $notes,
            'activeTab' => $activeTab,
        ])
    @endif

    <div class="grn-index-panel" data-grn-panel="grouped" @if($activeTab !== 'grouped') hidden @endif>
        @include('purchase::goods-receive.partials.index-panel-grouped', [
            'hasGrns' => $hasGrns,
            'hasActiveFilters' => $hasActiveFilters,
            'activeTab' => $activeTab,
        ])
    </div>

    <div class="grn-index-panel" data-grn-panel="all" @if($activeTab !== 'all') hidden @endif>
        @include('purchase::goods-receive.partials.all-grn-table', [
            'hasGrns' => $hasGrns,
            'hasActiveFilters' => $hasActiveFilters,
        ])
    </div>
</div>

<div style="margin-top:12px;">
    <a href="{{ route('dashboard') }}" class="linkbtn" style="padding:6px 10px;font-size:11px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>

@include('purchase::goods-receive.partials.pay-modal', [
    'accounts' => $accounts,
    'hasPaymentAccounts' => $hasPaymentAccounts,
    'canPayByCheque' => $canPayByCheque ?? false,
    'openPayGrnId' => $openPayGrnId ?? 0,
])

@once
<script>
(function () {
    if (window.__grnPoCollapseInit) return;
    window.__grnPoCollapseInit = true;

    document.querySelector('[data-grn-expand-all]')?.addEventListener('click', function () {
        document.querySelectorAll('[data-grn-po-group]').forEach(function (el) {
            el.open = true;
        });
    });
    document.querySelector('[data-grn-collapse-all]')?.addEventListener('click', function () {
        document.querySelectorAll('[data-grn-po-group]').forEach(function (el) {
            el.open = false;
        });
    });
})();
</script>
@endonce
@endsection
