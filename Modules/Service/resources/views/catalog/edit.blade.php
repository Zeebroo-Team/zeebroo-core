@extends('theme::layouts.app', ['title' => 'Edit Service', 'heading' => 'Edit Service'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:760px;margin:0 auto;padding:14px;">
    @include('service::partials.service-hub-nav')

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;font-size:17px;font-weight:800;">{{ $item->name }}</h2>
        <a href="{{ route('service.catalog.show', $item) }}" class="linkbtn"
           style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">← Back</a>
    </div>

    @include('service::catalog.partials.item-form', ['item' => $item, 'currency' => $currency])
</div>
@endsection
