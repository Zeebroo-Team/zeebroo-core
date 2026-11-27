@extends('theme::layouts.app', ['title' => $affiliate->name.' - Commission', 'heading' => 'Affiliates'])

@section('content')
@include('auth::affiliate.partials.styles')

<div class="aff-wrap">
    <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="aff-back"><i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $affiliate->name }}</a>

    <div class="aff-header">
        <div>
            <h1 class="aff-title"><i class="fa fa-sack-dollar" aria-hidden="true"></i>Payments &amp; Commission</h1>
            <p class="aff-sub">Plan payments made by the accounts {{ $affiliate->name }} referred ({{ $affiliate->email }}), and the commission each one earns.</p>
        </div>
    </div>

    @include('auth::affiliate.partials.commission', ['filterAction' => route('admin.affiliates.payments', $affiliate)])
</div>
@endsection
