@extends('theme::layouts.app', ['title' => 'Payments & Commission', 'heading' => 'Payments & Commission'])

@section('content')
@include('auth::affiliate.partials.styles')

<div class="aff-wrap">
    <div class="aff-header">
        <div>
            <h1 class="aff-title"><i class="fa fa-sack-dollar" aria-hidden="true"></i>Payments &amp; Commission</h1>
            <p class="aff-sub">Plan payments made by the accounts you referred, and the commission each payment earns you.</p>
        </div>
        <div class="aff-header-actions">
            <a href="{{ route('affiliate.dashboard') }}" class="aff-btn aff-btn--tall"><i class="fa fa-ticket" aria-hidden="true"></i> Referrals &amp; codes</a>
            <a href="{{ route('affiliate.withdrawals') }}" class="aff-add-btn"><i class="fa fa-money-bill-transfer" aria-hidden="true"></i> Withdraw</a>
        </div>
    </div>

    @include('auth::affiliate.partials.commission', ['filterAction' => route('affiliate.payments')])
</div>
@endsection
