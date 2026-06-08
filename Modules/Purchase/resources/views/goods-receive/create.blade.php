@extends('theme::layouts.app', ['title' => 'Goods receive note', 'heading' => 'Goods receive note'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.grn-lines-table input{width:100%;box-sizing:border-box;padding:7px 8px;font-size:12px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);}
</style>

<div class="pcat-page-card card" style="max-width:min(94vw,960px);margin:0 auto;padding:14px;">
    @include('purchase::partials.purchase-hub-nav')

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Receiving against <strong style="color:var(--text);">{{ $purchase->po_number }}</strong>
        @if($purchase->supplier) from <strong style="color:var(--text);">{{ $purchase->supplier->name }}</strong>@endif
    </p>

    <section class="pcat-inline">
        <h2 style="margin:0 0 10px;font-size:16px;font-weight:800;">New goods receive note</h2>
        @include('purchase::goods-receive.partials.receive-form', [
            'purchase' => $purchase,
            'currency' => $currency,
            'canPayByCheque' => $canPayByCheque,
            'accounts' => $accounts,
            'hasPaymentAccounts' => $hasPaymentAccounts,
            'stockSellingMarkupPercent' => $stockSellingMarkupPercent ?? 25,
            'branchStockSeparate' => $branchStockSeparate,
            'branchOptions' => $branchOptions,
            'purchaseBranchId' => $purchaseBranchId,
        ])
    </section>
</div>
@endsection
