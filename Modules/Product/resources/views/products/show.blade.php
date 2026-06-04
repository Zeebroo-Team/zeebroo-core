@extends('theme::layouts.app', ['title' => $product->name, 'heading' => $product->name])

@section('content')
@php
    $activeTab = $activeTab ?? 'overview';
    $productTabUrl = fn (string $tab) => route('product.show', array_filter([
        'product' => $product,
        'tab' => $tab,
        'sales_period' => $tab === 'overview' ? ($salesPeriod ?? request('sales_period', 'weekly')) : null,
    ], fn ($v) => $v !== null && $v !== ''));
    $productOverviewUrl = fn (string $period) => route('product.show', [
        'product' => $product,
        'tab' => 'overview',
        'sales_period' => $period,
    ]);
    $galleryCount = $product->productImages->count() + ($product->imageFile && $product->productImages->isEmpty() ? 1 : 0);
@endphp
@include('product::partials.catalog-hub-styles')
<style>
/* ── Page shell ──────────────────────────────────────────────────── */
.ps{max-width:100%;}

/* ── Header ──────────────────────────────────────────────────────── */
.ps-head{display:flex;align-items:flex-start;gap:12px;margin:0 0 14px;}
.ps-head__img{flex-shrink:0;width:64px;height:64px;object-fit:cover;border-radius:10px;border:1px solid var(--border);}
.ps-head__ph{flex-shrink:0;width:64px;height:64px;border-radius:10px;border:1px dashed var(--border);display:grid;place-items:center;color:var(--muted);font-size:20px;}
.ps-head__body{flex:1;min-width:0;}
.ps-head__back{font-size:11px;color:var(--muted);text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:4px;}
.ps-head__back:hover{color:var(--text);}
.ps-head__name{margin:0;font-size:17px;font-weight:800;color:var(--text);letter-spacing:-.02em;line-height:1.2;}
.ps-head__meta{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:6px;}
.ps-head__sku{font-size:11px;color:var(--muted);font-family:monospace;}
.ps-badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;border:1px solid var(--border);display:inline-block;}
.ps-badge--on{border-color:color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:color-mix(in srgb,#4ade80 80%,var(--text));}
.ps-badge--off{color:var(--muted);}
.ps-badge--bundle{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.ps-head__actions{display:flex;gap:6px;margin-left:auto;flex-shrink:0;}
.ps-head-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:11.5px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);text-decoration:none;cursor:pointer;transition:all .15s;}
.ps-head-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.ps-head-btn--primary{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);}
.ps-head-btn--primary:hover{background:color-mix(in srgb,var(--primary) 20%,transparent);}

/* ── Stats strip ─────────────────────────────────────────────────── */
.ps-stats{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px;}
.ps-stat{display:flex;flex-direction:column;gap:2px;padding:8px 14px;border:1px solid var(--border);border-radius:9px;background:color-mix(in srgb,var(--card) 96%,var(--border) 4%);min-width:100px;}
.ps-stat__lbl{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.ps-stat__val{font-size:15px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums;line-height:1.1;}

/* ── Tabs ────────────────────────────────────────────────────────── */
.ps-tabs{display:flex;flex-wrap:wrap;gap:4px;margin:0 0 14px;padding:4px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,var(--border) 8%);width:fit-content;}
.ps-tab{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:11.5px;font-weight:700;color:var(--muted);text-decoration:none;border-radius:8px;border:1px solid transparent;background:transparent;transition:all .15s;white-space:nowrap;}
.ps-tab:hover{color:var(--text);background:color-mix(in srgb,var(--card) 80%,transparent);}
.ps-tab.is-active{color:var(--text);background:var(--card);border-color:var(--border);box-shadow:0 1px 4px rgba(0,0,0,.1);}
.ps-tab__count{font-size:9px;font-weight:700;padding:1px 5px;border-radius:999px;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);border:1px solid color-mix(in srgb,var(--primary) 25%,transparent);}

/* ── Panel ───────────────────────────────────────────────────────── */
.ps-panel[hidden]{display:none!important;}

/* ── Section label ───────────────────────────────────────────────── */
.ps-label{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 8px;display:flex;align-items:center;gap:6px;}
.ps-label::after{content:'';flex:1;height:1px;background:var(--border);}

/* ── Overview grid ───────────────────────────────────────────────── */
.ps-overview-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin:0 0 12px;background:var(--border);}
@media(max-width:600px){.ps-overview-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
.ps-overview-cell{padding:9px 12px;background:color-mix(in srgb,var(--card) 97%,var(--border) 3%);}
.ps-overview-cell dt{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 3px;}
.ps-overview-cell dd{font-size:12.5px;font-weight:700;color:var(--text);margin:0;}
.ps-tag{display:inline-block;padding:1px 7px;border-radius:999px;font-size:10px;font-weight:600;border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.ps-desc{margin:0 0 12px;padding:10px 12px;border:1px solid var(--border);border-radius:9px;font-size:12px;line-height:1.55;color:var(--text);white-space:pre-wrap;background:color-mix(in srgb,var(--card) 97%,var(--border) 3%);}

/* ── Sales chart ─────────────────────────────────────────────────── */
.ps-chart-wrap{margin:0 0 14px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 97%,transparent);}
.ps-chart-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;}
.ps-chart-title{margin:0;font-size:13px;font-weight:800;color:var(--text);}
.ps-chart-sub{margin:2px 0 0;font-size:11px;color:var(--muted);}
.ps-chart-periods{display:flex;gap:4px;}
.ps-chart-period{padding:4px 10px;font-size:10px;font-weight:700;border-radius:999px;border:1px solid var(--border);background:transparent;color:var(--muted);text-decoration:none;transition:all .15s;}
.ps-chart-period:hover{color:var(--text);}
.ps-chart-period.is-active{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--text);}

/* ── Generic compact table ───────────────────────────────────────── */
.ps-table-wrap{border:1px solid var(--border);border-radius:10px;overflow:hidden;margin:0 0 12px;}
.ps-table{width:100%;border-collapse:collapse;font-size:12px;}
.ps-table th{padding:7px 11px;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);background:color-mix(in srgb,var(--card) 92%,var(--border) 8%);border-bottom:1px solid var(--border);text-align:left;}
.ps-table td{padding:8px 11px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);vertical-align:middle;}
.ps-table tr:last-child td{border-bottom:none;}
.ps-table tr:hover td{background:color-mix(in srgb,var(--card) 95%,var(--border) 5%);}

/* ── Inline add form card ────────────────────────────────────────── */
.ps-form-card{padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 97%,var(--border) 3%);margin:0 0 12px;}
.ps-form-card__title{font-size:11px;font-weight:700;color:var(--text);margin:0 0 10px;}
.ps-field label{display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:4px;}
.ps-field input{width:100%;box-sizing:border-box;padding:7px 10px;font-size:12px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);outline:none;transition:border-color .15s;}
.ps-field input:focus{border-color:var(--primary);}
.ps-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;font-size:11.5px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);cursor:pointer;transition:all .15s;}
.ps-btn:hover{background:color-mix(in srgb,var(--primary) 20%,transparent);}
.ps-btn--ghost{border-color:var(--border);background:transparent;color:var(--muted);}
.ps-btn--ghost:hover{color:var(--text);}
.ps-btn--danger{border-color:color-mix(in srgb,#ef4444 35%,var(--border));background:transparent;color:#f87171;padding:5px 8px;}
.ps-btn--danger:hover{background:color-mix(in srgb,#ef4444 8%,transparent);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .ps-btn--danger{color:#dc2626;}

/* ── Gallery ─────────────────────────────────────────────────────── */
.ps-gallery{display:flex;flex-wrap:wrap;gap:8px;}
.ps-gallery img{width:96px;height:96px;object-fit:cover;border-radius:9px;border:1px solid var(--border);}

/* ── Help text ───────────────────────────────────────────────────── */
.ps-help{margin:0 0 10px;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 96%,var(--border) 4%);font-size:11.5px;color:var(--muted);line-height:1.5;}

/* ── Stock badges ────────────────────────────────────────────────── */
.ps-po-status{font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:999px;border:1px solid var(--border);white-space:nowrap;}
.ps-po-status--ordered{border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 10%,transparent);}
.ps-po-status--partially_received{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 10%,transparent);}
.ps-po-status--received{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);}

/* ── Passthrough styles for stock & chart partials (keep working) ── */
.product-sales-chart{margin:0;}
.product-sales-chart__head{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:10px;}
.product-sales-chart__title{margin:0;font-size:13px;font-weight:800;color:var(--text);}
.product-sales-chart__sub{margin:3px 0 0;font-size:11px;}
.product-sales-chart__periods{display:flex;flex-wrap:wrap;gap:4px;}
.product-sales-chart__period{padding:4px 10px;font-size:10px;font-weight:700;border-radius:999px;border:1px solid var(--border);background:transparent;color:var(--muted);text-decoration:none;transition:all .15s;}
.product-sales-chart__period:hover{color:var(--text);}
.product-sales-chart__period.is-active{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--text);}
.product-sales-chart__total{margin:0 0 8px;font-size:11px;}
.product-sales-chart__empty{margin:0;padding:20px 12px;text-align:center;font-size:12px;border:1px dashed var(--border);border-radius:9px;}
.product-stock-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin:0 0 12px;}
@media(max-width:900px){.product-stock-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:480px){.product-stock-summary{grid-template-columns:1fr;}}
.product-stock-summary__card{padding:8px 11px;border:1px solid var(--border);border-radius:9px;background:color-mix(in srgb,var(--card) 96%,var(--border) 4%);}
.product-stock-summary__label{margin:0;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.product-stock-summary__value{margin:3px 0 0;font-size:14px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums;}
.product-stock-subtabs{display:flex;flex-wrap:wrap;gap:4px;margin:0 0 10px;}
.product-stock-subtabs__tab{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;font-size:11px;font-weight:700;border-radius:999px;border:1px solid var(--border);color:var(--muted);text-decoration:none;background:transparent;transition:all .15s;}
.product-stock-subtabs__tab:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary) 35%,var(--border));}
.product-stock-subtabs__tab.is-active{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--text);}
.product-show-tabs__count{font-size:9px;font-weight:700;padding:1px 5px;border-radius:999px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);border:1px solid color-mix(in srgb,var(--primary) 22%,transparent);}
.product-po-status{font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:999px;border:1px solid var(--border);white-space:nowrap;}
.product-po-status--draft{opacity:.85;}
.product-po-status--ordered{border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 10%,transparent);}
.product-po-status--partially_received{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 10%,transparent);}
.product-po-status--received{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);}
.product-po-status--cancelled{opacity:.75;}
.product-stock-applied{font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:999px;border:1px solid var(--border);}
.product-stock-applied--yes{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);}
.product-stock-applied--no{color:var(--muted);}
</style>

<div class="pcat-page-card card ps" style="max-width:100%;padding:14px;">
    @include('product::partials.product-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:12px;">{{ session('status') }}</div>
    @endif

    {{-- ── Header ───────────────────────────────────────────────── --}}
    <div class="ps-head">
        @if($product->imageUrl())
            <img src="{{ $product->imageUrl() }}" alt="" class="ps-head__img">
        @else
            <span class="ps-head__ph" aria-hidden="true"><i class="fa fa-box"></i></span>
        @endif

        <div class="ps-head__body">
            <a href="{{ route('product.index') }}" class="ps-head__back">
                <i class="fa fa-arrow-left"></i> Product catalog
            </a>
            <h2 class="ps-head__name">{{ $product->name }}</h2>
            <div class="ps-head__meta">
                @if($product->sku)
                    <span class="ps-head__sku">{{ $product->sku }}</span>
                    <span class="muted" style="font-size:10px;">·</span>
                @endif
                @if($product->is_active)
                    <span class="ps-badge ps-badge--on">Active</span>
                @else
                    <span class="ps-badge ps-badge--off">Inactive</span>
                @endif
                @if($product->is_bundle)
                    <span class="ps-badge ps-badge--bundle">Bundle · {{ $product->bundleItems->count() }} items</span>
                @endif
                @if($product->productUnit)
                    <span class="ps-badge">{{ $product->productUnit->displayLabel() }}</span>
                @elseif($product->unit)
                    <span class="ps-badge">{{ $product->unit }}</span>
                @endif
            </div>
        </div>

        <div class="ps-head__actions">
            <a href="{{ route('product.edit', $product) }}" class="ps-head-btn ps-head-btn--primary">
                <i class="fa fa-pen-to-square"></i> Edit
            </a>
        </div>
    </div>

    {{-- ── Stats strip ──────────────────────────────────────────── --}}
    <div class="ps-stats" role="region" aria-label="Product summary">
        <div class="ps-stat">
            <span class="ps-stat__lbl">Price @if(filled($currency))({{ $currency }})@endif</span>
            <span class="ps-stat__val">
                @if($product->unit_price !== null){{ number_format((float) $product->unit_price, 2) }}@else —@endif
            </span>
        </div>
        <div class="ps-stat">
            <span class="ps-stat__lbl">Stock</span>
            <span class="ps-stat__val">{{ rtrim(rtrim(number_format((float) $product->stock_quantity, 3), '0'), '.') }}</span>
        </div>
        @if($product->sellingUnits->isNotEmpty())
        <div class="ps-stat">
            <span class="ps-stat__lbl">Selling units</span>
            <span class="ps-stat__val">{{ $product->sellingUnits->count() }}</span>
        </div>
        @endif
        <div class="ps-stat">
            <span class="ps-stat__lbl">Images</span>
            <span class="ps-stat__val">{{ $galleryCount }}</span>
        </div>
        @if(($summary['purchase_lines_count'] ?? 0) > 0)
        <div class="ps-stat">
            <span class="ps-stat__lbl">Purchase lines</span>
            <span class="ps-stat__val">{{ (int) ($summary['purchase_lines_count'] ?? 0) }}</span>
        </div>
        @endif
    </div>

    {{-- ── Tab bar ───────────────────────────────────────────────── --}}
    <nav class="ps-tabs" aria-label="Product sections">
        <a href="{{ $productTabUrl('overview') }}" class="ps-tab @if($activeTab === 'overview') is-active @endif" @if($activeTab === 'overview') aria-current="page" @endif>
            <i class="fa fa-circle-info" aria-hidden="true"></i> Overview
        </a>
        <a href="{{ $productTabUrl('selling-units') }}" class="ps-tab @if($activeTab === 'selling-units') is-active @endif" @if($activeTab === 'selling-units') aria-current="page" @endif>
            <i class="fa fa-cubes" aria-hidden="true"></i> Selling Units
            @if($product->sellingUnits->isNotEmpty())
                <span class="ps-tab__count">{{ $product->sellingUnits->count() }}</span>
            @endif
        </a>
        <a href="{{ $productTabUrl('stock') }}" class="ps-tab @if($activeTab === 'stock') is-active @endif" @if($activeTab === 'stock') aria-current="page" @endif>
            <i class="fa fa-warehouse" aria-hidden="true"></i> Stock
            @if(($summary['purchase_lines_count'] ?? 0) + ($summary['grn_lines_count'] ?? 0) > 0)
                <span class="ps-tab__count">{{ (int)($summary['purchase_lines_count'] ?? 0) + (int)($summary['grn_lines_count'] ?? 0) }}</span>
            @endif
        </a>
        @if($product->is_bundle)
            <a href="{{ $productTabUrl('bundle') }}" class="ps-tab @if($activeTab === 'bundle') is-active @endif" @if($activeTab === 'bundle') aria-current="page" @endif>
                <i class="fa fa-layer-group" aria-hidden="true"></i> Bundle
                <span class="ps-tab__count">{{ $product->bundleItems->count() }}</span>
            </a>
        @endif
        @if($galleryCount > 0)
            <a href="{{ $productTabUrl('gallery') }}" class="ps-tab @if($activeTab === 'gallery') is-active @endif" @if($activeTab === 'gallery') aria-current="page" @endif>
                <i class="fa fa-images" aria-hidden="true"></i> Gallery
                <span class="ps-tab__count">{{ $galleryCount }}</span>
            </a>
        @endif
    </nav>

    {{-- ── Overview panel ───────────────────────────────────────── --}}
    <section class="ps-panel" @if($activeTab !== 'overview') hidden @endif>
        @include('product::products.partials.product-sales-chart', [
            'salesChart' => $salesChart ?? [],
            'salesPeriod' => $salesPeriod ?? 'weekly',
            'productOverviewUrl' => $productOverviewUrl,
        ])

        <p class="ps-label"><i class="fa fa-circle-info"></i> Details</p>
        <dl class="ps-overview-grid">
            <div class="ps-overview-cell">
                <dt>SKU</dt>
                <dd>{{ $product->sku ?: '—' }}</dd>
            </div>
            <div class="ps-overview-cell">
                <dt>Status</dt>
                <dd>{{ $product->is_active ? 'Active' : 'Inactive' }}</dd>
            </div>
            <div class="ps-overview-cell">
                <dt>Type</dt>
                <dd>{{ $product->is_bundle ? 'Bundle' : 'Single' }}</dd>
            </div>
            <div class="ps-overview-cell">
                <dt>Categories</dt>
                <dd>
                    @if($product->categories->isNotEmpty())
                        <span style="display:flex;flex-wrap:wrap;gap:3px;">
                            @foreach($product->categories as $cat)
                                <span class="ps-tag">{{ $cat->name }}</span>
                            @endforeach
                        </span>
                    @else —@endif
                </dd>
            </div>
            <div class="ps-overview-cell">
                <dt>Brands</dt>
                <dd>
                    @if($product->brands->isNotEmpty())
                        <span style="display:flex;flex-wrap:wrap;gap:3px;">
                            @foreach($product->brands as $b)
                                <span class="ps-tag">{{ $b->name }}</span>
                            @endforeach
                        </span>
                    @else —@endif
                </dd>
            </div>
            <div class="ps-overview-cell">
                <dt>Business</dt>
                <dd>{{ $business->name }}</dd>
            </div>
        </dl>

        @if($product->description)
            <p class="ps-label"><i class="fa fa-align-left"></i> Description</p>
            <p class="ps-desc">{{ $product->description }}</p>
        @endif
    </section>

    {{-- ── Selling Units panel ──────────────────────────────────── --}}
    <section class="ps-panel" @if($activeTab !== 'selling-units') hidden @endif>
        <p class="ps-help">
            Define pack sizes customers can buy (e.g. 500g bag, per gram). The <strong>conversion factor</strong> is how many base units equal 1 of this selling unit — e.g. <code>0.5</code> for 500g when base = kg.
        </p>

        @if($product->sellingUnits->isNotEmpty())
            <p class="ps-label"><i class="fa fa-cubes"></i> Defined units</p>
            <div class="ps-table-wrap">
                <table class="ps-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Factor</th>
                            <th>Selling price</th>
                            <th>Sort</th>
                            <th style="text-align:right;width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($product->sellingUnits as $su)
                            <tr>
                                <td><strong style="color:var(--text);">{{ $su->label }}</strong></td>
                                <td style="font-family:monospace;font-size:11px;">{{ rtrim(rtrim(number_format((float)$su->conversion_factor,6),'0'),'.') }}</td>
                                <td>
                                    @if($su->selling_price !== null)
                                        {{ number_format((float)$su->selling_price,2) }}{{ filled($currency??'') ? ' '.$currency : '' }}
                                    @else
                                        <span class="muted" style="font-size:11px;">derived</span>
                                    @endif
                                </td>
                                <td class="muted" style="font-size:11px;">{{ $su->sort_order }}</td>
                                <td style="text-align:right;">
                                    <form method="POST" action="{{ route('product.selling-units.destroy', [$product, $su]) }}" style="display:inline;" onsubmit="return confirm('Remove {{ addslashes($su->label) }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="ps-btn ps-btn--danger" title="Remove">
                                            <i class="fa fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted" style="margin:0 0 12px;font-size:12px;">No selling units defined yet.</p>
        @endif

        <p class="ps-label"><i class="fa fa-plus"></i> Add selling unit</p>
        <div class="ps-form-card">
            <form method="POST" action="{{ route('product.selling-units.store', $product) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin-bottom:10px;">
                    <div class="ps-field">
                        <label for="su-label">Label <span style="color:#f87171;">*</span></label>
                        <input type="text" id="su-label" name="label" value="{{ old('label') }}" placeholder="e.g. 500g bag" maxlength="80" required>
                    </div>
                    <div class="ps-field">
                        <label for="su-factor">Conversion factor <span style="color:#f87171;">*</span></label>
                        <input type="number" id="su-factor" name="conversion_factor" value="{{ old('conversion_factor') }}" placeholder="e.g. 0.5" step="any" min="0.000001" required>
                    </div>
                    <div class="ps-field">
                        <label for="su-price">Selling price</label>
                        <input type="number" id="su-price" name="selling_price" value="{{ old('selling_price') }}" placeholder="Leave blank to derive" step="any" min="0">
                    </div>
                    <div class="ps-field">
                        <label for="su-sort">Sort order</label>
                        <input type="number" id="su-sort" name="sort_order" value="{{ old('sort_order', 0) }}" min="0">
                    </div>
                </div>
                <button type="submit" class="ps-btn">
                    <i class="fa fa-plus"></i> Add selling unit
                </button>
            </form>
        </div>
    </section>

    {{-- ── Stock panel ───────────────────────────────────────────── --}}
    <section class="ps-panel" @if($activeTab !== 'stock') hidden @endif>
        @include('product::products.partials.show-tab-stock', [
            'stockView' => $stockView ?? 'layers',
            'summary' => $summary ?? [],
            'purchaseItems' => $purchaseItems ?? collect(),
            'grnItems' => $grnItems ?? collect(),
            'stockLayers' => $stockLayers ?? collect(),
            'stockSellingMarkupPercent' => $stockSellingMarkupPercent ?? 25,
        ])
    </section>

    {{-- ── Bundle panel ─────────────────────────────────────────── --}}
    @if($product->is_bundle)
        <section class="ps-panel" @if($activeTab !== 'bundle') hidden @endif>
            @if($product->bundleItems->isEmpty())
                <p class="muted" style="font-size:12px;">No bundle line items configured.</p>
            @else
                <div class="ps-table-wrap">
                    <table class="ps-table">
                        <thead>
                            <tr>
                                <th style="width:44px;"></th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th style="text-align:right;width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($product->bundleItems as $bundleRow)
                                @php $item = $bundleRow->itemProduct; @endphp
                                <tr>
                                    <td>
                                        @if($item?->imageUrl())
                                            <img src="{{ $item->imageUrl() }}" alt="" style="width:34px;height:34px;object-fit:cover;border-radius:7px;border:1px solid var(--border);">
                                        @else
                                            <span style="display:grid;place-items:center;width:34px;height:34px;border-radius:7px;border:1px dashed var(--border);font-size:13px;color:var(--muted);"><i class="fa fa-box"></i></span>
                                        @endif
                                    </td>
                                    <td><strong style="color:var(--text);">{{ $item?->name ?? '—' }}</strong></td>
                                    <td style="font-family:monospace;font-size:11px;color:var(--muted);">{{ $item?->sku ?? '—' }}</td>
                                    <td style="font-weight:700;">{{ rtrim(rtrim(number_format((float)$bundleRow->quantity,3),'0'),'.') }}</td>
                                    <td class="muted" style="font-size:11px;">
                                        @if($item?->productUnit) {{ $item->productUnit->displayLabel() }}
                                        @elseif($item?->unit) {{ $item->unit }}
                                        @else —@endif
                                    </td>
                                    <td style="text-align:right;">
                                        @if($item)
                                            <a href="{{ route('product.show', $item) }}" class="ps-btn ps-btn--ghost" style="padding:5px 9px;">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- ── Gallery panel ─────────────────────────────────────────── --}}
    @if($galleryCount > 0)
        <section class="ps-panel" @if($activeTab !== 'gallery') hidden @endif>
            <div class="ps-gallery">
                @foreach($product->productImages as $imageRow)
                    @if($imageRow->file?->publicUrl())
                        <img src="{{ $imageRow->file->publicUrl() }}" alt="">
                    @endif
                @endforeach
                @if($product->productImages->isEmpty() && $product->imageFile?->publicUrl())
                    <img src="{{ $product->imageFile->publicUrl() }}" alt="">
                @endif
            </div>
        </section>
    @endif

</div>
@endsection
