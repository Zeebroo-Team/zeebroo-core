@extends('theme::layouts.app', ['title' => 'Edit Client', 'heading' => 'Advertising Agency'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:700px;padding:14px;">
    @include('advertising-agency::partials.hub-nav')

    <h2 style="margin:0 0 16px;font-size:16px;">Edit: {{ $client->name }}</h2>

    @include('advertising-agency::clients.partials.form', ['client' => $client])
</div>
@endsection
