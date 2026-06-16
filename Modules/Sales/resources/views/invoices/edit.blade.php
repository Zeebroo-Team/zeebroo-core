@extends('theme::layouts.app', ['title' => 'Edit invoice', 'heading' => 'Edit invoice'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:900px;margin:0 auto;padding:14px;">
    @include('sales::partials.sales-hub-nav')

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
        <div>
            <h2 style="margin:0;font-size:17px;font-weight:800;color:var(--text);">{{ $invoice->invoice_number }}</h2>
            @if($invoice->customer)
                <p class="muted" style="margin:3px 0 0;font-size:12px;">{{ $invoice->customer->name }}</p>
            @endif
        </div>
        <a href="{{ route('sales.invoices.show', $invoice) }}"
           class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
            ← Back
        </a>
    </div>

    @include('sales::invoices.partials.create-form', ['invoice' => $invoice])
</div>
@endsection
