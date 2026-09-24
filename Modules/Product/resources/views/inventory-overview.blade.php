@extends('theme::layouts.app', ['title' => 'Inventory', 'heading' => 'Inventory'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.inv-ov-stats{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:22px;}
.inv-ov-stat{flex:1;min-width:150px;border:1px solid var(--border);border-radius:12px;padding:14px 16px 14px 20px;background:var(--card);position:relative;overflow:hidden;}
.inv-ov-stat::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:12px 0 0 12px;}
.inv-ov-stat--blue::before{background:#3b82f6;}
.inv-ov-stat--amber::before{background:#f59e0b;}
.inv-ov-stat--red::before{background:#ef4444;}
.inv-ov-stat--green::before{background:#10b981;}
.inv-ov-stat--purple::before{background:#8b5cf6;}
.inv-ov-stat__icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;margin-bottom:10px;}
.inv-ov-stat--blue   .inv-ov-stat__icon{background:color-mix(in srgb,#3b82f6 13%,transparent);color:#2563eb;}
.inv-ov-stat--amber  .inv-ov-stat__icon{background:color-mix(in srgb,#f59e0b 13%,transparent);color:#d97706;}
.inv-ov-stat--red    .inv-ov-stat__icon{background:color-mix(in srgb,#ef4444 13%,transparent);color:#dc2626;}
.inv-ov-stat--green  .inv-ov-stat__icon{background:color-mix(in srgb,#10b981 13%,transparent);color:#059669;}
.inv-ov-stat--purple .inv-ov-stat__icon{background:color-mix(in srgb,#8b5cf6 13%,transparent);color:#7c3aed;}
.inv-ov-stat__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:0 0 3px;}
.inv-ov-stat__value{font-size:22px;font-weight:800;color:var(--text);line-height:1.2;margin:0;}
.inv-ov-group-title{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 10px;}
.inv-ov-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin-bottom:24px;}
.inv-ov-card{display:flex;flex-direction:column;gap:8px;border:1px solid var(--border);border-radius:12px;padding:14px 16px;background:var(--card);text-decoration:none;transition:border-color .15s,transform .15s;}
.inv-ov-card:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-1px);}
.inv-ov-card i{font-size:17px;color:var(--primary);}
.inv-ov-card strong{font-size:13.5px;color:var(--text);}
.inv-ov-card span{font-size:11.5px;color:var(--muted);line-height:1.4;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <p class="muted" style="margin:0 0 18px;font-size:13px;line-height:1.45;">
        Manage your product catalog, purchasing, and stock operations in one place.
    </p>

    <div class="inv-ov-stats">
        <div class="inv-ov-stat inv-ov-stat--blue">
            <div class="inv-ov-stat__icon"><i class="fa fa-boxes-stacked"></i></div>
            <p class="inv-ov-stat__label">Total products</p>
            <p class="inv-ov-stat__value">{{ $totalProducts }}</p>
        </div>
        <div class="inv-ov-stat inv-ov-stat--red">
            <div class="inv-ov-stat__icon"><i class="fa fa-triangle-exclamation"></i></div>
            <p class="inv-ov-stat__label">Out of stock</p>
            <p class="inv-ov-stat__value">{{ $outOfStockProducts }}</p>
        </div>
        <div class="inv-ov-stat inv-ov-stat--amber">
            <div class="inv-ov-stat__icon"><i class="fa fa-file-invoice"></i></div>
            <p class="inv-ov-stat__label">Open purchase orders</p>
            <p class="inv-ov-stat__value">{{ $openPurchaseOrders }}</p>
        </div>
        <div class="inv-ov-stat inv-ov-stat--green">
            <div class="inv-ov-stat__icon"><i class="fa fa-clipboard-check"></i></div>
            <p class="inv-ov-stat__label">Open stock audits</p>
            <p class="inv-ov-stat__value">{{ $openStockAudits }}</p>
        </div>
        <div class="inv-ov-stat inv-ov-stat--purple">
            <div class="inv-ov-stat__icon"><i class="fa fa-truck-arrow-right"></i></div>
            <p class="inv-ov-stat__label">In-transit transfers</p>
            <p class="inv-ov-stat__value">{{ $inTransitTransfers }}</p>
        </div>
    </div>

    <p class="inv-ov-group-title">Catalog</p>
    <div class="inv-ov-cards">
        <a href="{{ route('product.index') }}" class="inv-ov-card"><i class="fa fa-box"></i><strong>Products</strong><span>Manage your product list, pricing and stock</span></a>
        <a href="{{ route('product.categories.index') }}" class="inv-ov-card"><i class="fa fa-folder-tree"></i><strong>Categories</strong><span>Organize products into categories</span></a>
        <a href="{{ route('product.brands.index') }}" class="inv-ov-card"><i class="fa fa-tag"></i><strong>Brands</strong><span>Manage product brands</span></a>
        <a href="{{ route('product.units.index') }}" class="inv-ov-card"><i class="fa fa-ruler"></i><strong>Units</strong><span>Units of measure for products</span></a>
        <a href="{{ route('product.discounts.index') }}" class="inv-ov-card"><i class="fa fa-percent"></i><strong>Discounts</strong><span>Time-boxed or ongoing product discounts</span></a>
        <a href="{{ route('product.campaigns.index') }}" class="inv-ov-card"><i class="fa fa-bullhorn"></i><strong>Sale campaigns</strong><span>Promotional campaigns across products</span></a>
        <a href="{{ route('product.barcodes.index') }}" class="inv-ov-card"><i class="fa fa-barcode"></i><strong>Barcodes</strong><span>Generate and print barcode sheets</span></a>
    </div>

    <p class="inv-ov-group-title">Purchasing &amp; stock</p>
    <div class="inv-ov-cards">
        <a href="{{ route('purchase.index') }}" class="inv-ov-card"><i class="fa fa-file-invoice"></i><strong>Purchase orders</strong><span>Order stock from suppliers</span></a>
        <a href="{{ route('purchase.grn.index') }}" class="inv-ov-card"><i class="fa fa-truck-ramp-box"></i><strong>Goods receive</strong><span>Receive and record incoming stock</span></a>
        <a href="{{ route('purchase.suppliers.index') }}" class="inv-ov-card"><i class="fa fa-truck-field"></i><strong>Suppliers</strong><span>Manage supplier accounts</span></a>
        <a href="{{ route('purchase.cheques.index') }}" class="inv-ov-card"><i class="fa fa-money-check"></i><strong>Cheques</strong><span>Track supplier cheque payments</span></a>
        <a href="{{ route('pos.stock-audits.index') }}" class="inv-ov-card"><i class="fa fa-clipboard-check"></i><strong>Stock audit</strong><span>Count physical stock and reconcile</span></a>
        <a href="{{ route('pos.stock-transfers.index') }}" class="inv-ov-card"><i class="fa fa-truck-arrow-right"></i><strong>Stock transfer</strong><span>Move stock between branches</span></a>
    </div>
</div>
@endsection
