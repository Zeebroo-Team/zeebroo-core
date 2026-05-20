@extends('theme::layouts.app', ['title' => 'Edit purchase order', 'heading' => 'Edit purchase order'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:min(94vw,900px);margin:0 auto;padding:14px;">
    @include('purchase::partials.purchase-hub-nav')

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Editing <strong style="color:var(--text);">{{ $purchase->po_number }}</strong> for {{ $business->name }}.
    </p>

    @if($products->isEmpty())
        <div class="pcat-banner pcat-banner--err" style="margin-bottom:12px;" role="alert">
            Add at least one <a href="{{ route('product.index') }}" class="pcat-link">product</a> before editing line items.
        </div>
    @endif

    <section class="pcat-inline">
        @include('purchase::purchases.partials.create-form', [
            'purchase' => $purchase,
            'formAction' => route('purchase.update', $purchase),
            'formMethod' => 'PUT',
            'showPurchaseCreateErrorBanner' => true,
            'currency' => $currency,
            'products' => $products,
            'suppliers' => $suppliers,
            'submitLabel' => 'Save purchase order',
        ])
    </section>

    <div style="margin-top:14px;">
        <a href="{{ route('purchase.show', $purchase) }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-arrow-left"></i> Back to order
        </a>
    </div>
</div>

@include('purchase::suppliers.partials.quick-add-modal')
@endsection
