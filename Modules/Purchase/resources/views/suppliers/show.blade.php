@extends('theme::layouts.app', ['title' => $supplier->name, 'heading' => $supplier->name])

@section('content')
@php
    $activeTab = $activeTab ?? 'overview';
    $paymentSubTab = $paymentSubTab ?? 'cash';
    $summary = $summary ?? [];
    $supplierTabUrl = fn (string $tab, array $extra = []) => route('purchase.suppliers.show', array_merge(
        ['supplier' => $supplier, 'tab' => $tab],
        $extra,
    ));
    $supplierPayTabUrl = fn (string $pay) => $supplierTabUrl('payments', ['pay' => $pay]);
@endphp
@include('product::partials.catalog-hub-styles')
@include('purchase::goods-receive.partials.grn-payment-styles')
<style>
.supplier-show-summary{
    display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:0 0 14px;
}
@media(max-width:900px){.supplier-show-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:480px){.supplier-show-summary{grid-template-columns:1fr;}}
.supplier-show-summary__card{
    padding:10px 12px;border:1px solid color-mix(in srgb,var(--border) 85%,transparent);
    border-radius:10px;background:color-mix(in srgb,var(--card) 94%,var(--primary) 6%);
}
.supplier-show-summary__label{margin:0;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);}
.supplier-show-summary__value{margin:4px 0 0;font-size:16px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums;}
.supplier-show-tabs{
    display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px;padding:0;
    border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);
}
.supplier-show-tabs__tab{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 14px 10px;margin:0 0 -1px;
    font-size:12px;font-weight:700;color:var(--muted);text-decoration:none;
    border:1px solid transparent;border-bottom:none;border-radius:8px 8px 0 0;
    background:transparent;
}
.supplier-show-tabs__tab:hover{color:var(--text);border-color:color-mix(in srgb,var(--border) 70%,transparent);background:color-mix(in srgb,var(--card) 90%,transparent);}
.supplier-show-tabs__tab.is-active{
    color:var(--text);
    border-color:color-mix(in srgb,var(--primary) 30%,var(--border));
    background:color-mix(in srgb,var(--primary) 8%,var(--card));
}
.supplier-show-tabs__count{
    font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;
    background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--muted);
}
.supplier-show-panel[hidden]{display:none !important;}
.supplier-show-overview-grid{
    display:grid;gap:12px 20px;grid-template-columns:repeat(2,minmax(0,1fr));
    margin:0 0 14px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;
}
@media(max-width:560px){.supplier-show-overview-grid{grid-template-columns:1fr;}}
.supplier-show-overview-grid dt{margin:0;font-size:11px;color:var(--muted);}
.supplier-show-overview-grid dd{margin:2px 0 0;font-size:14px;font-weight:700;color:var(--text);}
.supplier-pay-subtabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px;}
.supplier-pay-subtabs__tab{
    display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:12px;font-weight:700;
    border-radius:999px;border:1px solid var(--border);color:var(--muted);text-decoration:none;background:var(--card);
}
.supplier-pay-subtabs__tab:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));color:var(--text);}
.supplier-pay-subtabs__tab.is-active{
    border-color:color-mix(in srgb,var(--primary) 40%,var(--border));
    background:color-mix(in srgb,var(--primary) 10%,var(--card));color:var(--text);
}
.purchase-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);}
.purchase-status--draft{opacity:.85;}
.purchase-status--ordered{border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);}
.purchase-status--partially_received{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);}
.purchase-status--received{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.purchase-status--cancelled{border-color:color-mix(in srgb,#94a3b8 45%,var(--border));opacity:.75;}
.cheque-status{font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;border:1px solid var(--border);white-space:nowrap;}
.cheque-status--cleared{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.cheque-status--pending,.cheque-status--due{border-color:color-mix(in srgb,#3b82f6 40%,var(--border));background:color-mix(in srgb,#3b82f6 10%,transparent);}
.cheque-status--overdue{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);}
.cheque-due--overdue{color:color-mix(in srgb,#f59e0b 90%,var(--text));font-weight:700;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('purchase::partials.purchase-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
        <div>
            <p class="muted" style="margin:0 0 4px;font-size:12px;">
                <a href="{{ route('purchase.suppliers.index') }}" class="pcat-link"><i class="fa fa-arrow-left"></i> Suppliers</a>
            </p>
            <h2 style="margin:0;font-size:18px;font-weight:800;color:var(--text);">{{ $supplier->name }}</h2>
            <p class="muted" style="margin:6px 0 0;font-size:12px;">
                @if($supplier->contact_name){{ $supplier->contact_name }}@endif
                @if($supplier->email)@if($supplier->contact_name) · @endif{{ $supplier->email }}@endif
                @if($supplier->phone)@if($supplier->contact_name || $supplier->email) · @endif{{ $supplier->phone }}@endif
            </p>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
            @if($supplier->is_active)
                <span class="pcat-badge pcat-badge--on">Active</span>
            @else
                <span class="pcat-badge pcat-badge--off">Inactive</span>
            @endif
            <a href="{{ route('purchase.suppliers.edit', $supplier) }}" class="linkbtn" style="padding:6px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;"><i class="fa fa-pen"></i> Edit</a>
        </div>
    </div>

    <div class="supplier-show-summary" role="region" aria-label="Supplier summary">
        <div class="supplier-show-summary__card">
            <p class="supplier-show-summary__label">Purchase orders</p>
            <p class="supplier-show-summary__value">{{ (int) ($summary['purchases_count'] ?? 0) }}</p>
        </div>
        <div class="supplier-show-summary__card">
            <p class="supplier-show-summary__label">Goods receipts</p>
            <p class="supplier-show-summary__value">{{ (int) ($summary['grns_count'] ?? 0) }}</p>
        </div>
        <div class="supplier-show-summary__card">
            <p class="supplier-show-summary__label">Outstanding @if(filled($currency))({{ $currency }})@endif</p>
            <p class="supplier-show-summary__value">{{ number_format((float) ($summary['outstanding_total'] ?? 0), 2) }}</p>
        </div>
        <div class="supplier-show-summary__card">
            <p class="supplier-show-summary__label">Open cheques @if(filled($currency))({{ $currency }})@endif</p>
            <p class="supplier-show-summary__value">{{ number_format((float) ($summary['cheques_open_amount'] ?? 0), 2) }}</p>
        </div>
    </div>

    <nav class="supplier-show-tabs" aria-label="Supplier sections">
        <a href="{{ $supplierTabUrl('overview') }}" class="supplier-show-tabs__tab @if($activeTab === 'overview') is-active @endif" @if($activeTab === 'overview') aria-current="page" @endif>
            <i class="fa fa-circle-info" aria-hidden="true"></i> Overview
        </a>
        <a href="{{ $supplierTabUrl('payments') }}" class="supplier-show-tabs__tab @if($activeTab === 'payments') is-active @endif" @if($activeTab === 'payments') aria-current="page" @endif>
            <i class="fa fa-wallet" aria-hidden="true"></i> Payments
        </a>
        <a href="{{ $supplierTabUrl('purchases') }}" class="supplier-show-tabs__tab @if($activeTab === 'purchases') is-active @endif" @if($activeTab === 'purchases') aria-current="page" @endif>
            <i class="fa fa-file-invoice" aria-hidden="true"></i> Purchase orders
            <span class="supplier-show-tabs__count">{{ (int) ($summary['purchases_count'] ?? 0) }}</span>
        </a>
        <a href="{{ $supplierTabUrl('grns') }}" class="supplier-show-tabs__tab @if($activeTab === 'grns') is-active @endif" @if($activeTab === 'grns') aria-current="page" @endif>
            <i class="fa fa-truck-ramp-box" aria-hidden="true"></i> Goods receive notes
            <span class="supplier-show-tabs__count">{{ (int) ($summary['grns_count'] ?? 0) }}</span>
        </a>
    </nav>

    <section class="supplier-show-panel" id="supplier-show-overview" @if($activeTab !== 'overview') hidden @endif>
        @include('purchase::suppliers.partials.show-tab-overview')
    </section>

    <section class="supplier-show-panel" id="supplier-show-payments" @if($activeTab !== 'payments') hidden @endif>
        @include('purchase::suppliers.partials.show-tab-payments')
    </section>

    <section class="supplier-show-panel" id="supplier-show-purchases" @if($activeTab !== 'purchases') hidden @endif>
        @include('purchase::suppliers.partials.show-tab-purchases')
    </section>

    <section class="supplier-show-panel" id="supplier-show-grns" @if($activeTab !== 'grns') hidden @endif>
        @include('purchase::suppliers.partials.show-tab-grns')
    </section>
</div>
@endsection
