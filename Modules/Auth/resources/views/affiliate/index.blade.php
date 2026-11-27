@extends('theme::layouts.app', ['title' => 'Affiliate Dashboard', 'heading' => 'Affiliate Dashboard'])

@section('content')
@include('auth::affiliate.partials.styles')

<div class="aff-wrap">
    <div class="aff-header">
        <div>
            <h1 class="aff-title"><i class="fa fa-handshake" aria-hidden="true"></i>Affiliate Dashboard</h1>
            <p class="aff-sub">Share your sign-up link or referral code. Every account created with one of your codes is credited to you and listed below.</p>
        </div>
        <div class="aff-header-actions">
            <a href="{{ route('affiliate.payments') }}" class="aff-btn aff-btn--tall">
                <i class="fa fa-sack-dollar" aria-hidden="true"></i> Commission: <strong>{{ $commissionTotal }}</strong>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="aff-msg"><i class="fa fa-circle-check" aria-hidden="true"></i> {{ session('success') }}</div>
    @endif
    @if($errors->any() && ! $errors->has('code') && ! $errors->has('label'))
        <div class="aff-msg aff-msg-err"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i> {{ $errors->first() }}</div>
    @endif

    @php
        $settingsDone = count(array_filter($settingsCompletion));
        $firstTodo = array_search(false, $settingsCompletion, true);
    @endphp
    @if($settingsDone < count($settingsCompletion))
        <div class="aff-progress">
            <i class="fa fa-list-check" aria-hidden="true"></i>
            <div><strong>Finish your settings to get paid.</strong> Add your contact details, payout bank account and ID copy so we can pay your commission.</div>
            <a href="{{ route('affiliate.settings', ['tab' => $firstTodo ?: 'profile']) }}" class="aff-btn aff-btn--tall" style="margin-left:auto">
                <i class="fa fa-gear" aria-hidden="true"></i> Complete settings ({{ $settingsDone }}/{{ count($settingsCompletion) }})
            </a>
        </div>
    @endif

    @include('auth::affiliate.partials.stats', ['stats' => $stats])

    @include('auth::affiliate.partials.codes-section', [
        'codes' => $codes,
        'storeAction' => route('affiliate.codes.store'),
        'toggleRoute' => 'affiliate.codes.toggle',
        'maxCodes' => $maxCodes,
        'sectionTitle' => 'Your referral codes',
        'emptySub' => 'Generate a code, then share its sign-up link. People who create an account with it are credited to you.',
    ])

    @include('auth::affiliate.partials.referrals', [
        'referrals' => $referrals,
        'search' => $search,
        'searchAction' => route('affiliate.dashboard'),
        'sectionTitle' => 'Accounts signed up with your codes',
        'emptySub' => 'Share your sign-up link. When someone creates an account with your code, they show up here.',
    ])
</div>
@endsection
