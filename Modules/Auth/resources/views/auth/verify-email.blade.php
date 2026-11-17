@extends('theme::layouts.auth', ['title' => __('Verify your email')])

@section('content')
    <header class="auth-brand">
        <div class="auth-brand__mark" aria-hidden="true"><i class="fa fa-envelope-circle-check"></i></div>
        <div class="auth-brand__text">
            <h1>{{ __('Verify your email') }}</h1>
            <p>{{ __('Check your inbox') }}</p>
        </div>
    </header>
    <div class="auth-body">
        @if(session('status'))
            <p class="sub pwd-status"><i class="fa fa-circle-check" aria-hidden="true"></i> {{ session('status') }}</p>
        @endif
        <p class="sub">{{ __('We sent a 6-digit code to') }} <strong class="pwd-email">{{ $email }}</strong>. {{ __('Enter it below to confirm your email address. The code expires in :minutes minutes.', ['minutes' => $minutes]) }}</p>
        <form method="post" action="{{ route('verification.verify') }}" autocomplete="off" data-submit-once>
            @csrf
            <div class="field">
                <label for="otp">{{ __('Verification code') }}</label>
                <input id="otp" name="otp" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6"
                    value="{{ old('otp') }}" required autocomplete="one-time-code"
                    placeholder="000000" class="otp-input" autofocus>
                <div class="error">@error('otp'){{ $message }}@enderror</div>
            </div>
            <button type="submit" class="auth-btn" data-label="{{ __('Verify email') }}" data-loading-text="{{ __('Verifying…') }}">{{ __('Verify email') }}</button>
        </form>
        <div class="auth-alt-links" role="navigation" aria-label="{{ __('Other options') }}">
            <form method="post" action="{{ route('verification.resend') }}" style="margin:0;">
                @csrf
                <button type="submit" class="auth-alt-pill" title="{{ __('Email me a new code') }}">
                    <i class="fa fa-rotate-right" aria-hidden="true"></i><span>{{ __('Resend code') }}</span>
                </button>
            </form>
            <a href="{{ route('dashboard') }}" class="auth-alt-pill">
                <i class="fa fa-arrow-left" aria-hidden="true"></i><span>{{ __('Skip for now') }}</span>
            </a>
        </div>
    </div>
@endsection

@push('auth-styles')
<style>
    .otp-input{text-align:center;letter-spacing:.35em;font-size:24px;font-weight:700;font-family:ui-monospace,monospace;}
    .pwd-email{color:var(--text);word-break:break-all;}
    .pwd-status{color:#16a34a;}
    .auth-alt-links form{flex:1 1 0;min-width:0;display:flex;}
    button.auth-alt-pill{width:100%;cursor:pointer;font-family:inherit;}
</style>
@endpush
