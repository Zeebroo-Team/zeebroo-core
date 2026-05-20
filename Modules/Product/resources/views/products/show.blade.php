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
.product-show-header{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-start;margin:0 0 14px;}
.product-show-header__media{flex-shrink:0;}
.product-show-header__media img{width:96px;height:96px;object-fit:cover;border-radius:12px;border:1px solid var(--border);}
.product-show-header__placeholder{
    display:grid;place-items:center;width:96px;height:96px;border-radius:12px;
    border:1px dashed var(--border);color:var(--muted);font-size:28px;
}
.product-show-header__main{flex:1;min-width:min(100%,200px);}
.product-show-summary{
    display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:0 0 14px;
}
@media(max-width:900px){.product-show-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:480px){.product-show-summary{grid-template-columns:1fr;}}
.product-show-summary__card{
    padding:10px 12px;border:1px solid color-mix(in srgb,var(--border) 85%,transparent);
    border-radius:10px;background:color-mix(in srgb,var(--card) 94%,var(--primary) 6%);
}
.product-show-summary__label{margin:0;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);}
.product-show-summary__value{margin:4px 0 0;font-size:16px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums;}
.product-show-tabs{
    display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px;padding:0;
    border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);
}
.product-show-tabs__tab{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 14px 10px;margin:0 0 -1px;
    font-size:12px;font-weight:700;color:var(--muted);text-decoration:none;
    border:1px solid transparent;border-bottom:none;border-radius:8px 8px 0 0;
    background:transparent;
}
.product-show-tabs__tab:hover{color:var(--text);border-color:color-mix(in srgb,var(--border) 70%,transparent);background:color-mix(in srgb,var(--card) 90%,transparent);}
.product-show-tabs__tab.is-active{
    color:var(--text);
    border-color:color-mix(in srgb,var(--primary) 30%,var(--border));
    background:color-mix(in srgb,var(--primary) 8%,var(--card));
}
.product-show-tabs__count{
    font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;
    background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--muted);
}
.product-show-panel[hidden]{display:none !important;}
.product-show-overview-grid{
    display:grid;gap:12px 20px;grid-template-columns:repeat(2,minmax(0,1fr));
    margin:0 0 14px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;
}
@media(max-width:560px){.product-show-overview-grid{grid-template-columns:1fr;}}
.product-show-overview-grid dt{margin:0;font-size:11px;color:var(--muted);}
.product-show-overview-grid dd{margin:2px 0 0;font-size:14px;font-weight:700;color:var(--text);}
.product-show-tag{
    display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;
    border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));
    background:color-mix(in srgb,var(--primary) 10%,transparent);
}
.product-show-badge{font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);display:inline-block;}
.product-show-badge--on{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.product-show-badge--off{opacity:.8;color:var(--muted);}
.product-show-gallery{display:flex;flex-wrap:wrap;gap:10px;}
.product-show-gallery__item img{width:120px;height:120px;object-fit:cover;border-radius:10px;border:1px solid var(--border);}
.product-show-desc{margin:0;padding:12px 14px;border:1px solid var(--border);border-radius:10px;font-size:13px;line-height:1.55;color:var(--text);white-space:pre-wrap;}
.product-sales-chart{
    margin:0 0 16px;padding:14px 16px;border:1px solid var(--border);border-radius:12px;
    background:color-mix(in srgb,var(--card) 96%,transparent);
}
.product-sales-chart__head{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px;}
.product-sales-chart__title{margin:0;font-size:14px;font-weight:800;display:flex;align-items:center;gap:8px;color:var(--text);}
.product-sales-chart__sub{margin:4px 0 0;font-size:12px;}
.product-sales-chart__periods{display:flex;flex-wrap:wrap;gap:6px;}
.product-sales-chart__period{
    padding:6px 12px;font-size:11px;font-weight:700;border-radius:999px;border:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--muted);text-decoration:none;
}
.product-sales-chart__period:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.product-sales-chart__period.is-active{
    color:var(--text);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));
    background:color-mix(in srgb,var(--primary) 12%,transparent);
}
.product-sales-chart__total{margin:0 0 10px;font-size:12px;}
.product-sales-chart__empty{margin:0;padding:24px 12px;text-align:center;font-size:13px;line-height:1.5;border:1px dashed var(--border);border-radius:10px;}
.product-stock-summary{
    display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin:0 0 14px;
}
@media(max-width:900px){.product-stock-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:480px){.product-stock-summary{grid-template-columns:1fr;}}
.product-stock-summary__card{
    padding:10px 12px;border:1px solid color-mix(in srgb,var(--border) 85%,transparent);
    border-radius:10px;background:color-mix(in srgb,var(--card) 94%,var(--primary) 6%);
}
.product-stock-summary__label{margin:0;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);}
.product-stock-summary__value{margin:4px 0 0;font-size:15px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums;}
.product-stock-subtabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px;}
.product-stock-subtabs__tab{
    display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:12px;font-weight:700;
    border-radius:999px;border:1px solid var(--border);color:var(--muted);text-decoration:none;background:var(--card);
}
.product-stock-subtabs__tab:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));color:var(--text);}
.product-stock-subtabs__tab.is-active{
    border-color:color-mix(in srgb,var(--primary) 40%,var(--border));
    background:color-mix(in srgb,var(--primary) 10%,var(--card));color:var(--text);
}
.product-po-status{font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;border:1px solid var(--border);white-space:nowrap;}
.product-po-status--draft{opacity:.85;}
.product-po-status--ordered{border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);}
.product-po-status--partially_received{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);}
.product-po-status--received{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.product-po-status--cancelled{opacity:.75;}
.product-stock-applied{font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;border:1px solid var(--border);}
.product-stock-applied--yes{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.product-stock-applied--no{color:var(--muted);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('product::partials.product-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div class="product-show-header">
        <div class="product-show-header__media">
            @if($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="">
            @else
                <span class="product-show-header__placeholder" aria-hidden="true"><i class="fa fa-image"></i></span>
            @endif
        </div>
        <div class="product-show-header__main">
            <p class="muted" style="margin:0 0 4px;font-size:12px;">
                <a href="{{ route('product.index') }}" class="pcat-link"><i class="fa fa-arrow-left"></i> Product catalog</a>
            </p>
            <h2 style="margin:0;font-size:18px;font-weight:800;color:var(--text);">{{ $product->name }}</h2>
            <p class="muted" style="margin:6px 0 0;font-size:12px;">
                @if($product->sku)<span>SKU {{ $product->sku }}</span>@endif
                @if($product->is_bundle)
                    @if($product->sku)<span> · </span>@endif
                    <span>Bundle · {{ $product->bundleItems->count() }} items</span>
                @endif
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;align-items:center;">
                @if($product->is_active)
                    <span class="product-show-badge product-show-badge--on">Active</span>
                @else
                    <span class="product-show-badge product-show-badge--off">Inactive</span>
                @endif
                <a href="{{ route('product.edit', $product) }}" class="linkbtn" style="padding:6px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;"><i class="fa fa-pen"></i> Edit</a>
            </div>
        </div>
    </div>

    <div class="product-show-summary" role="region" aria-label="Product summary">
        <div class="product-show-summary__card">
            <p class="product-show-summary__label">Unit price @if(filled($currency))({{ $currency }})@endif</p>
            <p class="product-show-summary__value">
                @if($product->unit_price !== null)
                    {{ number_format((float) $product->unit_price, 2) }}
                @else
                    —
                @endif
            </p>
        </div>
        <div class="product-show-summary__card">
            <p class="product-show-summary__label">Stock</p>
            <p class="product-show-summary__value">{{ number_format((float) $product->stock_quantity, 3) }}</p>
        </div>
        <div class="product-show-summary__card">
            <p class="product-show-summary__label">Unit</p>
            <p class="product-show-summary__value" style="font-size:14px;">
                @if($product->productUnit)
                    {{ $product->productUnit->displayLabel() }}
                @elseif($product->unit)
                    {{ $product->unit }}
                @else
                    —
                @endif
            </p>
        </div>
        <div class="product-show-summary__card">
            <p class="product-show-summary__label">Images</p>
            <p class="product-show-summary__value">{{ $galleryCount }}</p>
        </div>
    </div>

    <nav class="product-show-tabs" aria-label="Product sections">
        <a href="{{ $productTabUrl('overview') }}" class="product-show-tabs__tab @if($activeTab === 'overview') is-active @endif" @if($activeTab === 'overview') aria-current="page" @endif>
            <i class="fa fa-circle-info" aria-hidden="true"></i> Overview
        </a>
        <a href="{{ $productTabUrl('stock') }}" class="product-show-tabs__tab @if($activeTab === 'stock') is-active @endif" @if($activeTab === 'stock') aria-current="page" @endif>
            <i class="fa fa-warehouse" aria-hidden="true"></i> Stock
            @if(($summary['purchase_lines_count'] ?? 0) + ($summary['grn_lines_count'] ?? 0) > 0)
                <span class="product-show-tabs__count">{{ (int) ($summary['purchase_lines_count'] ?? 0) + (int) ($summary['grn_lines_count'] ?? 0) }}</span>
            @endif
        </a>
        @if($product->is_bundle)
            <a href="{{ $productTabUrl('bundle') }}" class="product-show-tabs__tab @if($activeTab === 'bundle') is-active @endif" @if($activeTab === 'bundle') aria-current="page" @endif>
                <i class="fa fa-layer-group" aria-hidden="true"></i> Bundle
                <span class="product-show-tabs__count">{{ $product->bundleItems->count() }}</span>
            </a>
        @endif
        @if($galleryCount > 0)
            <a href="{{ $productTabUrl('gallery') }}" class="product-show-tabs__tab @if($activeTab === 'gallery') is-active @endif" @if($activeTab === 'gallery') aria-current="page" @endif>
                <i class="fa fa-images" aria-hidden="true"></i> Gallery
                <span class="product-show-tabs__count">{{ $galleryCount }}</span>
            </a>
        @endif
    </nav>

    <section class="product-show-panel" @if($activeTab !== 'overview') hidden @endif>
        @include('product::products.partials.product-sales-chart', [
            'salesChart' => $salesChart ?? [],
            'salesPeriod' => $salesPeriod ?? 'weekly',
            'productOverviewUrl' => $productOverviewUrl,
        ])

        <dl class="product-show-overview-grid">
            <div>
                <dt>SKU</dt>
                <dd>{{ $product->sku ?: '—' }}</dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd>{{ $product->is_active ? 'Active' : 'Inactive' }}</dd>
            </div>
            <div>
                <dt>Categories</dt>
                <dd>
                    @if($product->categories->isNotEmpty())
                        <span style="display:flex;flex-wrap:wrap;gap:4px;">
                            @foreach($product->categories as $cat)
                                <span class="product-show-tag">{{ $cat->name }}</span>
                            @endforeach
                        </span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt>Brands</dt>
                <dd>
                    @if($product->brands->isNotEmpty())
                        <span style="display:flex;flex-wrap:wrap;gap:4px;">
                            @foreach($product->brands as $brandRow)
                                <span class="product-show-tag">{{ $brandRow->name }}</span>
                            @endforeach
                        </span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt>Type</dt>
                <dd>{{ $product->is_bundle ? 'Bundle' : 'Single product' }}</dd>
            </div>
            <div>
                <dt>Business</dt>
                <dd>{{ $business->name }}</dd>
            </div>
        </dl>
        @if($product->description)
            <p class="muted" style="margin:0 0 6px;font-size:11px;font-weight:700;">Description</p>
            <div class="product-show-desc">{{ $product->description }}</div>
        @endif
    </section>

    <section class="product-show-panel" @if($activeTab !== 'stock') hidden @endif>
        @include('product::products.partials.show-tab-stock', [
            'stockView' => $stockView ?? 'layers',
            'summary' => $summary ?? [],
            'purchaseItems' => $purchaseItems ?? collect(),
            'grnItems' => $grnItems ?? collect(),
            'stockLayers' => $stockLayers ?? collect(),
            'stockSellingMarkupPercent' => $stockSellingMarkupPercent ?? 25,
        ])
    </section>

    @if($product->is_bundle)
        <section class="product-show-panel" @if($activeTab !== 'bundle') hidden @endif>
            @if($product->bundleItems->isEmpty())
                <p class="muted" style="margin:0;font-size:13px;">No bundle line items configured.</p>
            @else
                <div class="pcat-table-wrap">
                    <table class="pcat-table">
                        <thead>
                            <tr>
                                <th style="width:52px;"></th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th style="text-align:right;">View</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($product->bundleItems as $bundleRow)
                                @php $item = $bundleRow->itemProduct; @endphp
                                <tr>
                                    <td>
                                        @if($item?->imageUrl())
                                            <img src="{{ $item->imageUrl() }}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                                        @else
                                            <span class="muted" style="display:grid;place-items:center;width:40px;height:40px;border-radius:8px;border:1px dashed var(--border);font-size:14px;"><i class="fa fa-image"></i></span>
                                        @endif
                                    </td>
                                    <td><strong style="color:var(--text);">{{ $item?->name ?? '—' }}</strong></td>
                                    <td class="muted">{{ $item?->sku ?? '—' }}</td>
                                    <td>{{ number_format((float) $bundleRow->quantity, 3) }}</td>
                                    <td class="muted">
                                        @if($item?->productUnit)
                                            {{ $item->productUnit->displayLabel() }}
                                        @elseif($item?->unit)
                                            {{ $item->unit }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td style="text-align:right;">
                                        @if($item)
                                            <a href="{{ route('product.show', $item) }}" class="pcat-link"><i class="fa fa-eye"></i></a>
                                        @else
                                            —
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

    @if($galleryCount > 0)
        <section class="product-show-panel" @if($activeTab !== 'gallery') hidden @endif>
            <div class="product-show-gallery">
                @foreach($product->productImages as $imageRow)
                    @if($imageRow->file?->publicUrl())
                        <div class="product-show-gallery__item">
                            <img src="{{ $imageRow->file->publicUrl() }}" alt="">
                        </div>
                    @endif
                @endforeach
                @if($product->productImages->isEmpty() && $product->imageFile?->publicUrl())
                    <div class="product-show-gallery__item">
                        <img src="{{ $product->imageFile->publicUrl() }}" alt="">
                    </div>
                @endif
            </div>
        </section>
    @endif
</div>
@endsection
