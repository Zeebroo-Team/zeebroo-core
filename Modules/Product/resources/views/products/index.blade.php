@extends('theme::layouts.app', ['title' => 'Products', 'heading' => 'Product catalog'])

@section('content')
<style>
.product-page{max-width:100%;margin:0;}
.product-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.product-field input,.product-field textarea,.product-field select{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.product-field select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px;}
.product-field textarea{min-height:70px;line-height:1.45;resize:vertical;font-family:inherit;}
.product-form-grid{display:grid;gap:10px;}@media (min-width:720px){.product-form-grid--2{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}}
.product-table-wrap{margin-top:12px;border:1px solid var(--border);border-radius:11px;overflow:auto;}
.product-table{width:100%;border-collapse:collapse;font-size:13px;min-width:880px;}
.product-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);}
.product-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);vertical-align:top;}
.product-table tr:last-child td{border-bottom:none;}
.product-badge{font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);display:inline-block;}
.product-badge--on{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:color-mix(in srgb,#bbf7d0 70%,var(--text));}
.product-badge--off{opacity:.8;color:var(--muted);}
.product-actions{display:flex;flex-wrap:nowrap;gap:5px;align-items:center;}
.product-action-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;font-size:11.5px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;text-decoration:none;white-space:nowrap;transition:border-color .15s,background .15s,box-shadow .15s;}
.product-action-btn:hover{box-shadow:0 2px 8px rgba(0,0,0,.1);}
.product-action-btn--view{border-color:color-mix(in srgb,var(--primary) 30%,var(--border));color:var(--primary);}
.product-action-btn--view:hover{background:color-mix(in srgb,var(--primary) 10%,transparent);border-color:color-mix(in srgb,var(--primary) 55%,var(--border));}
.product-action-btn--edit{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);}
.product-action-btn--edit:hover{background:color-mix(in srgb,var(--primary) 22%,transparent);border-color:color-mix(in srgb,var(--primary) 65%,var(--border));}
.product-action-btn--del{border-color:color-mix(in srgb,#ef4444 35%,var(--border));background:transparent;color:#f97373;}
.product-action-btn--del:hover{background:color-mix(in srgb,#ef4444 10%,transparent);border-color:color-mix(in srgb,#ef4444 60%,var(--border));}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .product-action-btn--del{color:#dc2626;}
.product-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;}
.product-modal{position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:center;padding:4vh 6vw;overflow:auto;box-sizing:border-box;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility .22s ease;}
.product-modal.product-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
.product-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .product-modal__backdrop{background:rgba(17,24,39,.38);}
.product-modal__panel{position:relative;z-index:1;box-sizing:border-box;width:min(94vw,820px);max-width:820px;height:auto;max-height:min(88vh,calc(100vh - 8vh));display:flex;flex-direction:column;border-radius:12px;border:1px solid var(--border);background:var(--card);box-shadow:0 16px 40px rgba(0,0,0,.28);margin:auto;}
.product-modal__head{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid var(--border);flex-shrink:0;background:color-mix(in srgb,var(--card) 95%,transparent);}
.product-modal__head-main{display:flex;align-items:center;gap:10px 14px;min-width:0;flex:1 1 auto;flex-wrap:wrap;}
.product-modal__head h2{margin:0;font-size:14px;font-weight:800;letter-spacing:-.02em;flex-shrink:0;}
.product-modal__head-toggles{display:inline-flex;align-items:center;gap:10px 14px;flex-wrap:wrap;}
.product-modal__feature-toggle{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:600;color:var(--muted);cursor:pointer;user-select:none;white-space:nowrap;}
.product-modal__feature-toggle__lbl{color:var(--text);}
.product-modal__feature-toggle .product-switch--sm{width:34px;height:18px;}
.product-modal__feature-toggle .product-switch--sm .product-switch-slider:before{height:14px;width:14px;left:2px;top:2px;}
.product-modal__feature-toggle .product-switch--sm input:checked+.product-switch-slider:before{transform:translateX(16px);}
.product-modal__close{width:28px;height:28px;display:grid;place-items:center;padding:0;border:1px solid var(--border);border-radius:8px;background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--text);cursor:pointer;font-size:15px;line-height:1;}
.product-modal__close:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.product-modal__body{flex:1 1 auto;min-height:0;padding:10px 12px 12px;overflow:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;font-size:12px;}
.product-modal__banner,.product-inline-form__banner{margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);font-size:13px;color:var(--text);}
.product-modal__banner{margin-bottom:8px;padding:8px 10px;font-size:12px;border-radius:8px;}
.product-modal__body [data-product-modal-form]{gap:8px;}
@media (min-width:720px){
    .product-modal__body [data-product-modal-form].product-form-grid--2{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 10px;}
    .product-modal__body .product-field-row--pricing-stock{grid-template-columns:repeat(3,minmax(0,1fr));gap:8px 10px;}
}
.product-modal__body .product-field label{font-size:9px;margin-bottom:3px;}
.product-modal__body .product-field input,.product-modal__body .product-field textarea,.product-modal__body .product-field select{padding:6px 8px;font-size:12px;border-radius:7px;}
.product-modal__body .product-field select{padding-right:26px;background-position:right 8px center;}
.product-modal__body .product-sku-generate-btn{padding:6px 9px;font-size:11px;border-radius:7px;}
.product-modal__body .product-sku-row{gap:5px;}
.product-modal__body .product-image-field__label{margin-bottom:5px;}
.product-modal__body .product-image-field__panel{gap:8px;padding:8px 10px;border-radius:8px;}
.product-modal__body .product-image-field__preview img,.product-modal__body .product-image-field__gallery-item{width:52px;height:52px;}
.product-modal__body .product-image-field__gallery-item{width:52px;height:52px;}
.product-modal__body .product-image-field__placeholder{padding:10px;font-size:12px;gap:8px;border-radius:8px;}
.product-modal__body .product-image-field__placeholder i{font-size:18px;}
.product-modal__body .product-image-field__btn{padding:6px 10px!important;font-size:12px!important;}
.product-modal__body .product-image-field__hint{font-size:10px;}
.product-modal__body .product-cat-tags__label,.product-modal__body .product-brand-tags__label{margin-bottom:3px;font-size:9px;}
.product-modal__body .product-cat-tags__box,.product-modal__body .product-brand-tags__box{min-height:36px;padding:4px 6px;gap:4px;border-radius:7px;}
.product-modal__body .product-cat-tags__chip,.product-modal__body .product-brand-tags__chip{font-size:11px;padding:3px 7px 3px 8px;}
.product-modal__body .product-cat-tags__input,.product-modal__body .product-brand-tags__input{font-size:12px;min-width:72px;}
.product-modal__body .product-cat-tags__hint,.product-modal__body .product-brand-tags__hint{font-size:10px;margin-top:4px;}
.product-modal__body .product-bundle-card{border-radius:10px;}
.product-modal__body .product-bundle-card__head{padding:8px 10px;gap:8px 10px;}
.product-modal__body .product-bundle-card__title{font-size:13px;gap:6px;}
.product-modal__body .product-bundle-card__lead{font-size:11px;margin-top:2px;}
.product-modal__body .product-bundle-toggle{padding:5px 9px;font-size:11px;border-radius:7px;}
.product-modal__body .product-bundle-card__body{padding:8px 10px 10px;}
.product-modal__body .product-bundle-panel__hint{margin-bottom:6px;font-size:11px;}
.product-modal__body .product-bundle-search{padding:6px 8px;font-size:12px;border-radius:7px;}
.product-modal__body .product-bundle-list{padding:6px;gap:4px;min-height:36px;border-radius:7px;}
.product-modal__body .product-bundle-row{padding:6px 8px;gap:6px 8px;border-radius:7px;}
.product-modal__body .product-bundle-row__name{font-size:12px;}
.product-modal__body .product-bundle-row__qty input{width:60px;padding:5px 6px;font-size:12px;}
.product-modal__body .product-bundle-row__remove{width:24px;height:24px;font-size:16px;}
.product-modal__body .product-active-row{padding:8px 10px;border-radius:8px;}
.product-modal__body .product-active-row__lbl{font-size:12px;}
.product-modal__body .product-switch{width:40px;height:22px;}
.product-modal__body .product-switch-slider:before{height:16px;width:16px;}
.product-modal__body .product-switch input:checked+.product-switch-slider:before{transform:translateX(18px);}
.product-modal__body [data-product-modal-form]>div[style*="grid-column"] .linkbtn{padding:6px 14px!important;font-size:12px!important;}
html.product-modal-open-html,html.product-modal-open-html body{overflow:hidden;}
.product-active-row{display:flex;align-items:center;justify-content:space-between;gap:14px;width:100%;padding:11px 14px;box-sizing:border-box;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);}
.product-active-row__lbl{margin:0;font-size:13px;font-weight:600;color:var(--text);cursor:pointer;}
.product-switch{position:relative;display:inline-block;width:46px;height:26px;flex-shrink:0;}
.product-switch input{opacity:0;width:0;height:0;margin:0;position:absolute;}
.product-switch-slider{position:absolute;inset:0;cursor:pointer;background:#475569;border-radius:999px;transition:.2s;}
.product-switch-slider:before{content:"";position:absolute;height:20px;width:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.22);}
.product-switch input:checked + .product-switch-slider{background:#22c55e;}
.product-switch input:checked + .product-switch-slider:before{transform:translateX(20px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .product-switch-slider{background:color-mix(in srgb,#475569 75%,var(--border));}
.product-switch input:focus-visible + .product-switch-slider{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 45%,transparent);}
.product-inline-create{box-sizing:border-box;width:100%;max-width:none;margin-top:8px;padding:14px 16px 16px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 98%,transparent);}
.product-inline-create__head{margin:0 0 14px;padding-bottom:12px;border-bottom:1px solid var(--border);}
.product-inline-create__head h2{margin:0;font-size:16px;font-weight:800;letter-spacing:-.02em;color:var(--text);}
.product-inline-create__lead{margin:6px 0 0;font-size:13px;line-height:1.45;color:var(--muted);max-width:62ch;}
</style>

@include('product::partials.catalog-hub-styles')

<div class="product-page card" style="max-width:100%;padding:14px;">
    @include('product::partials.product-hub-nav')

    @if(session('status'))
        <div style="margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);font-size:13px;font-weight:600;color:var(--text);">{{ session('status') }}</div>
    @endif
    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">Products for <strong style="color:var(--text);">{{ $business->name }}</strong>. Track items you buy, sell, or stock for this business.</p>

    @php $productModalOpen = $products->isNotEmpty() && $errors->any(); @endphp
    <div class="product-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if($products->isEmpty())
                Use the form below to add your <strong style="color:var(--text);">first product</strong>.
            @else
                {{ $products->total() }} product{{ $products->total() === 1 ? '' : 's' }}.
            @endif
        </span>
        @if($products->isNotEmpty())
            <button type="button" id="product-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-plus"></i> Add product</button>
        @endif
    </div>

    @if($products->isEmpty())
        <section class="product-inline-create" aria-labelledby="product-inline-title">
            <header class="product-inline-create__head">
                <h2 id="product-inline-title">Add your first product</h2>
                <p class="product-inline-create__lead">Name items you purchase or keep in inventory. You can edit prices, stock, and SKU later.</p>
            </header>
            @include('product::products.partials.create-form', [
                'productFormErrorBannerClass' => 'product-inline-form__banner',
                'currency' => $currency,
                'categories' => $categories,
                'brands' => $brands,
                'units' => $units,
                'bundlePickerCatalog' => $bundlePickerCatalog,
            ])
        </section>
    @else
        <div class="product-table-wrap">
            <table class="product-table">
                <thead>
                    <tr>
                        <th style="width:56px;"></th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Active</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        <tr>
                            <td>
                                @if($product->imageUrl())
                                    <img src="{{ $product->imageUrl() }}" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                                @else
                                    <span class="muted" style="display:grid;place-items:center;width:44px;height:44px;border-radius:8px;border:1px dashed var(--border);font-size:16px;"><i class="fa fa-image" aria-hidden="true"></i></span>
                                @endif
                            </td>
                            <td>
                                <strong style="color:var(--text);">{{ $product->name }}</strong>
                                @if($product->is_bundle)
                                    <span class="product-badge" style="margin-left:6px;font-size:10px;border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);">Bundle · {{ $product->bundleItems->count() }} items</span>
                                @endif
                                @if($product->is_bundle && $product->bundleItems->isNotEmpty())
                                    <div class="muted" style="font-size:11px;margin-top:4px;line-height:1.35;">
                                        @foreach($product->bundleItems->take(4) as $bundleRow)
                                            {{ $bundleRow->quantity }}× {{ $bundleRow->itemProduct?->name ?? '—' }}@if(!$loop->last), @endif
                                        @endforeach
                                        @if($product->bundleItems->count() > 4)
                                            …
                                        @endif
                                    </div>
                                @endif
                                @if($product->description)
                                    <div class="muted" style="font-size:12px;line-height:1.4;margin-top:4px;">{{ \Illuminate\Support\Str::limit($product->description, 120) }}</div>
                                @endif
                                @if($product->productUnit)
                                    <div class="muted" style="font-size:11px;margin-top:4px;">{{ $product->productUnit->displayLabel() }}</div>
                                @elseif($product->unit)
                                    <div class="muted" style="font-size:11px;margin-top:4px;">{{ $product->unit }}</div>
                                @endif
                            </td>
                            <td>
                                @if($product->categories->isNotEmpty())
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        @foreach($product->categories as $cat)
                                            <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);">{{ $cat->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($product->brands->isNotEmpty())
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        @foreach($product->brands as $brandRow)
                                            <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);">{{ $brandRow->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>@if($product->sku){{ $product->sku }}@else<span class="muted">—</span>@endif</td>
                            <td>
                                @if($product->unit_price !== null)
                                    @if($currency){{ $currency }} @endif{{ number_format((float) $product->unit_price, 2) }}
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>{{ number_format((float) $product->stock_quantity, 3) }}</td>
                            <td>
                                @if($product->is_active)
                                    <span class="product-badge product-badge--on">Active</span>
                                @else
                                    <span class="product-badge product-badge--off">Inactive</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <div class="product-actions" style="justify-content:flex-end;">
                                    <a class="product-action-btn product-action-btn--view" href="{{ route('product.show', $product) }}">
                                        <i class="fa fa-eye" aria-hidden="true"></i> View
                                    </a>
                                    <a class="product-action-btn product-action-btn--edit" href="{{ route('product.edit', $product) }}">
                                        <i class="fa fa-pen-to-square" aria-hidden="true"></i> Edit
                                    </a>
                                    <form method="post" action="{{ route('product.destroy', $product) }}" style="margin:0;" onsubmit="return confirm('Delete {{ addslashes($product->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="product-action-btn product-action-btn--del">
                                            <i class="fa fa-trash-can" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div style="margin-top:14px;">
                {{ $products->withQueryString()->links() }}
            </div>
        @endif

        <div id="product-modal"
            class="product-modal {{ $productModalOpen ? 'product-modal--open' : '' }}"
            @if($productModalOpen) data-preserve-form="1" @endif
            role="dialog"
            aria-modal="true"
            aria-labelledby="product-modal-title"
            aria-hidden="{{ $productModalOpen ? 'false' : 'true' }}">
            <div class="product-modal__backdrop" data-product-modal-close tabindex="-1"></div>
            <div class="product-modal__panel">
                <div class="product-modal__head">
                    <div class="product-modal__head-main">
                        <h2 id="product-modal-title">Add product</h2>
                        <div class="product-modal__head-toggles">
                            <label class="product-modal__feature-toggle" for="product-modal-image-toggle">
                                <span class="product-modal__feature-toggle__lbl">Image</span>
                                <span class="product-switch product-switch--sm">
                                    <input type="checkbox" id="product-modal-image-toggle" data-product-modal-image-toggle role="switch" aria-checked="false">
                                    <span class="product-switch-slider" aria-hidden="true"></span>
                                </span>
                            </label>
                            <label class="product-modal__feature-toggle" for="product-modal-bundle-toggle">
                                <span class="product-modal__feature-toggle__lbl">Bundle</span>
                                <span class="product-switch product-switch--sm">
                                    <input type="checkbox" id="product-modal-bundle-toggle" data-product-modal-bundle-toggle role="switch" aria-checked="false">
                                    <span class="product-switch-slider" aria-hidden="true"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <button type="button" class="product-modal__close" data-product-modal-close aria-label="Close dialog">&times;</button>
                </div>
                <div class="product-modal__body">
                    @include('product::products.partials.create-form', [
                        'productFormErrorBannerClass' => 'product-modal__banner',
                        'fieldIdPrefix' => 'modal',
                        'currency' => $currency,
                        'categories' => $categories,
                        'brands' => $brands,
                        'units' => $units,
                        'bundlePickerCatalog' => $bundlePickerCatalog,
                    ])
                </div>
            </div>
        </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('dashboard') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>

<script>
(function () {
    const modal = document.getElementById('product-modal');
    const openBtn = document.getElementById('product-modal-open');

    function lockScroll(on) {
        document.documentElement.classList.toggle('product-modal-open-html', Boolean(on));
    }

    function getModalImageField() {
        return modal?.querySelector('[data-product-image-modal-field]');
    }

    function modalHasImageFormState() {
        const form = modal?.querySelector('[data-product-modal-form]');
        const field = getModalImageField();
        if (!form || !field) return false;
        if (field.querySelector('div[style*="color:#f87171"]')) return true;
        if (form.querySelector('[data-product-image-id-input]')) return true;
        const fileId = form.querySelector('[name="file_manager_file_id"]');
        if (fileId && String(fileId.value || '').trim() !== '') return true;
        return false;
    }

    function setModalProductImageEnabled(on) {
        const toggle = modal?.querySelector('[data-product-modal-image-toggle]');
        const field = getModalImageField();
        if (!field) return;
        const enabled = Boolean(on);
        if (toggle) {
            toggle.checked = enabled;
            toggle.setAttribute('aria-checked', enabled ? 'true' : 'false');
        }
        field.hidden = !enabled;
        field.querySelectorAll('input, textarea, select, button').forEach(function (el) {
            el.disabled = !enabled;
        });
        if (!enabled && window.resetProductImageFields) {
            window.resetProductImageFields(modal);
        }
    }

    function getModalBundleField() {
        return modal?.querySelector('[data-product-bundle-modal-field]');
    }

    function modalHasBundleFormState() {
        const form = modal?.querySelector('[data-product-modal-form]');
        const field = getModalBundleField();
        if (!form || !field) return false;
        if (field.querySelector('.product-bundle-card__err')) return true;
        if (field.querySelector('[data-bundle-row]')) return true;
        const bundleToggle = field.querySelector('[data-bundle-toggle]');
        if (bundleToggle && bundleToggle.checked) return true;
        return false;
    }

    function setModalProductBundleEnabled(on) {
        const toggle = modal?.querySelector('[data-product-modal-bundle-toggle]');
        const field = getModalBundleField();
        if (!field) return;
        const enabled = Boolean(on);
        if (toggle) {
            toggle.checked = enabled;
            toggle.setAttribute('aria-checked', enabled ? 'true' : 'false');
        }
        field.hidden = !enabled;
        field.querySelectorAll('input, textarea, select, button').forEach(function (el) {
            el.disabled = !enabled;
        });
        const bundleToggle = field.querySelector('[data-bundle-toggle]');
        const panel = field.querySelector('[data-bundle-panel]');
        if (!enabled) {
            if (window.resetProductBundleFields) {
                window.resetProductBundleFields(modal);
            }
        } else if (bundleToggle && panel) {
            bundleToggle.checked = true;
            panel.hidden = false;
        }
    }

    function resetProductModalForm() {
        const form = modal?.querySelector('[data-product-modal-form]');
        if (!form) return;

        if (window.resetProductCategoryTags) {
            window.resetProductCategoryTags(modal);
        }
        if (window.resetProductBrandTags) {
            window.resetProductBrandTags(modal);
        }
        if (window.resetProductImageFields) {
            window.resetProductImageFields(modal);
        }
        if (window.resetProductBundleFields) {
            window.resetProductBundleFields(modal);
        }

        form.querySelectorAll('[data-cat-tags-hidden-inputs], [data-brand-tags-hidden-inputs]').forEach(function (el) {
            el.innerHTML = '';
        });

        form.reset();

        form.querySelectorAll('input, textarea, select').forEach(function (el) {
            if (el.type === 'hidden' || el.type === 'file') {
                if (el.type === 'file') {
                    el.value = '';
                }
                return;
            }
            if (el.type === 'checkbox') {
                if (el.name === 'is_active') {
                    el.checked = true;
                    el.setAttribute('aria-checked', 'true');
                }
                return;
            }
            if (el.tagName === 'SELECT') {
                el.selectedIndex = 0;
                return;
            }
            el.value = '';
            if (typeof el.defaultValue !== 'undefined') {
                el.defaultValue = '';
            }
        });

        const stock = form.querySelector('[name="stock_quantity"]');
        if (stock) {
            stock.value = '0';
            stock.defaultValue = '0';
        }

        const fileId = form.querySelector('[name="file_manager_file_id"]');
        if (fileId) {
            fileId.value = '';
            fileId.defaultValue = '';
        }
        form.querySelectorAll('[name="file_manager_file_ids[]"]').forEach(function (el) {
            el.remove();
        });
        const removeImg = form.querySelector('[name="remove_product_images"]');
        if (removeImg) {
            removeImg.value = '0';
        }

        modal.querySelectorAll('[data-cat-tags-root], [data-brand-tags-root]').forEach(function (root) {
            root.dataset.initialTags = '[]';
        });

        modal.querySelectorAll('.product-modal__banner').forEach(function (el) {
            el.remove();
        });
        form.querySelectorAll('.product-field > div[style*="color:#f87171"]').forEach(function (el) {
            el.remove();
        });

        document.documentElement.classList.remove('product-image-picker-open');
        setModalProductImageEnabled(false);
        setModalProductBundleEnabled(false);
    }

    function openProductModal() {
        if (!modal) return;

        if (modal.dataset.preserveForm === '1') {
            delete modal.dataset.preserveForm;
        } else {
            resetProductModalForm();
        }

        modal.classList.add('product-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lockScroll(true);

        if (window.initProductCategoryTags) {
            window.initProductCategoryTags(modal);
        }
        if (window.initProductBrandTags) {
            window.initProductBrandTags(modal);
        }
        if (window.initProductImageFields) {
            window.initProductImageFields(modal);
        }
        setModalProductImageEnabled(modalHasImageFormState());
        setModalProductBundleEnabled(modalHasBundleFormState());

        const first = document.getElementById('modal-name') || document.getElementById('product-name');
        window.requestAnimationFrame(function () {
            first?.focus();
        });
    }

    function closeProductModal() {
        if (!modal) return;
        modal.classList.remove('product-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lockScroll(false);
        resetProductModalForm();
        openBtn?.focus();
    }

    openBtn?.addEventListener('click', openProductModal);
    modal?.querySelector('[data-product-modal-image-toggle]')?.addEventListener('change', function () {
        setModalProductImageEnabled(this.checked);
    });
    modal?.querySelector('[data-product-modal-bundle-toggle]')?.addEventListener('change', function () {
        setModalProductBundleEnabled(this.checked);
    });
    modal?.querySelectorAll('[data-product-modal-close]').forEach((el) =>
        el.addEventListener('click', () => closeProductModal()),
    );

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (!modal?.classList.contains('product-modal--open')) return;
        closeProductModal();
    });

    if (modal?.classList.contains('product-modal--open')) {
        lockScroll(true);
        if (window.initProductImageFields) {
            window.initProductImageFields(modal);
        }
        setModalProductImageEnabled(modalHasImageFormState());
        setModalProductBundleEnabled(modalHasBundleFormState());
    }
})();
</script>
@endsection
