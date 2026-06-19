@extends('theme::layouts.app', ['title' => 'Edit Bundle', 'heading' => 'Bulk Bundle'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:760px;margin:0 auto;padding:14px;">
    @include('service::partials.service-hub-nav')

    <div style="margin-bottom:16px;">
        <h3 style="margin:0 0 4px;font-size:15px;font-weight:700;">Edit Bundle</h3>
        <p style="margin:0;color:var(--muted);font-size:13px;">{{ $bundle->name }}</p>
    </div>

    @include('service::bundles.partials.bundle-form')
</div>
@endsection
