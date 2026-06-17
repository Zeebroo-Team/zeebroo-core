@extends('theme::layouts.app', ['title' => 'Edit Service Category', 'heading' => 'Edit Service Category'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('service::partials.service-hub-nav')

    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    <div style="max-width:480px;padding:8px 0;">
        <h3 style="margin:0 0 16px;font-size:15px;font-weight:800;">Edit: {{ $category->name }}</h3>
        @include('service::categories.partials.category-form')
    </div>
</div>
@endsection
