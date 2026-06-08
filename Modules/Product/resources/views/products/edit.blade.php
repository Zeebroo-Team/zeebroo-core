@extends('theme::layouts.app', ['title' => 'Edit product', 'heading' => 'Edit product'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.product-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.product-field input,.product-field textarea,.product-field select{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.product-field textarea{min-height:80px;line-height:1.45;resize:vertical;font-family:inherit;}
.product-field select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px;}
.product-form-edit{display:grid;gap:10px;}@media (min-width:720px){.product-form-edit{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}}
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
</style>

<div class="card" style="max-width:100%;padding:16px;">
    @include('product::partials.product-hub-nav')

    <p class="muted" style="margin:0 0 14px;font-size:13px;">Updating <strong style="color:var(--text);">{{ $product->name }}</strong> under {{ $business->name }}</p>

    @if($errors->any())
        <div style="margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#f87171 40%,var(--border));font-size:13px;color:var(--text);">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('product.update', $product) }}" class="product-form-edit">
        @csrf
        @method('PUT')
        @include('product::products.partials.product-fields-body', [
            'product' => $product,
            'fieldIdPrefix' => 'edit',
            'currency' => $currency,
            'categories' => $categories,
            'brands' => $brands,
            'units' => $units,
            'bundlePickerCatalog' => $bundlePickerCatalog,
            'branchProductSeparate' => $branchProductSeparate,
            'branchOptions' => $branchOptions,
        ])
        <div style="grid-column:1/-1;display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;margin-top:4px;">
            <a href="{{ route('product.show', $product) }}" class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Cancel</a>
            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save changes</button>
        </div>
    </form>
</div>
@endsection
