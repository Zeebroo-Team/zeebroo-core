@extends('theme::layouts.app', ['title' => 'Edit bill · '.($bill->name ?? 'Bill'), 'heading' => 'Edit bill'])

@section('content')
<div class="rental-form-page">
    @include('account::rentals.partials.form-shared-styles')

    @if(session('status'))
        <div class="rental-alert rental-alert--ok" role="status"><i class="fa fa-circle-check"></i><span>{{ session('status') }}</span></div>
    @endif

    <header class="rental-edit-hero">
        <h1>{{ $bill->name }}</h1>
        <div style="display:flex;flex-wrap:wrap;gap:9px;">
            <a class="rental-btn--ghost" href="{{ route('account.bills.show', $bill) }}"><i class="fa fa-arrow-left" aria-hidden="true"></i>Back to details</a>
            <a class="rental-btn--ghost" href="{{ route('account.bills.index') }}">All bills</a>
        </div>
    </header>

    <div class="rental-form-card">
        @include('account::bills.partials.create-form')
    </div>
</div>
@endsection
