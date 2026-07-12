@extends('theme::layouts.app', ['title' => 'Edit lead', 'heading' => 'Edit lead'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $project])

    <h2 style="margin:0 0 8px;font-size:16px;font-weight:800;">Edit {{ $lead->name }}</h2>
    @include('crm::leads.partials.create-form')
</div>
@endsection
