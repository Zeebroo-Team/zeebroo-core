@extends('theme::layouts.app', ['title' => 'Edit rental · '.($rental->property_type ?? 'Rental'), 'heading' => 'Edit rental'])

@section('content')
<div class="rental-form-page">
    @include('account::rentals.partials.form-shared-styles')

    @if(session('status'))
        <div class="rental-alert rental-alert--ok" role="status"><i class="fa fa-circle-check"></i><span>{{ session('status') }}</span></div>
    @endif

    <header class="rental-edit-hero">
        <h1>{{ $rental->property_type }}</h1>
        <div style="display:flex;flex-wrap:wrap;gap:9px;">
            <a class="rental-btn--ghost" href="{{ route('account.rentals.show', $rental) }}"><i class="fa fa-arrow-left" aria-hidden="true"></i>Back to details</a>
            <a class="rental-btn--ghost" href="{{ route('account.rentals.index') }}">All rentals</a>
        </div>
    </header>

    <div class="rental-form-card">
        @include('account::rentals.partials.create-form')
    </div>
</div>
@endsection
