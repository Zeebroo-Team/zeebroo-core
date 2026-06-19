@extends('theme::layouts.app', ['title' => 'Edit Request', 'heading' => 'Edit Request'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:760px;margin:0 auto;padding:14px;">
    @include('service::partials.service-hub-nav')

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;font-size:17px;font-weight:800;">{{ $req->request_number }}</h2>
        <a href="{{ route('service.requests.show', $req) }}" class="linkbtn"
           style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">← Back</a>
    </div>

    @include('service::requests.partials.request-form', [
        'req'          => $req,
        'serviceItems' => $serviceItems,
        'customers'    => $customers,
        'currency'     => $currency,
    ])
</div>
@endsection
