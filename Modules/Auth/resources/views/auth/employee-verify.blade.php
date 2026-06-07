@extends('theme::layouts.auth', ['title' => __('Verify your email')])

@section('content')
    <header class="auth-brand">
        <div class="auth-brand__mark" aria-hidden="true"><i class="fa fa-envelope-circle-check"></i></div>
        <div class="auth-brand__text">
            <h1>{{ __('Verify your email') }}</h1>
            <p>{{ __('Employee email detected') }}</p>
        </div>
    </header>
    <div class="auth-body">
        <div class="emp-notice">
            <i class="fa fa-circle-info" aria-hidden="true"></i>
            <p>{{ __('The email you entered matches an employee record. Your employee email and the email you entered are the same — verify your identity to create your customer account.') }}</p>
        </div>
        <p class="sub">{{ __('Your email is') }} <strong class="emp-masked-email">{{ $maskedEmail }}</strong>. {{ __('Enter your full email below to receive a verification code.') }}</p>
        <form method="post" action="{{ route('register.employee-verify.submit') }}" autocomplete="on">
            @csrf
            <div class="field">
                <label for="email">{{ __('Email address') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="{{ __('your@email.com') }}">
                <div class="error">@error('email'){{ $message }}@enderror</div>
            </div>
            <button type="submit" class="auth-btn">{{ __('Send verification code') }}</button>
        </form>
        <div class="auth-alt-links" role="navigation" aria-label="{{ __('Other options') }}">
            <a href="{{ route('register') }}" class="auth-alt-pill" title="{{ __('Back to registration') }}">
                <i class="fa fa-arrow-left" aria-hidden="true"></i><span>{{ __('Back') }}</span>
            </a>
            <a href="{{ route('hr.portal.login') }}" class="auth-alt-pill auth-alt-pill--hr" title="{{ __('Sign in to HR portal instead') }}">
                <i class="fa fa-users-gear" aria-hidden="true"></i><span>{{ __('HR portal') }}</span>
            </a>
        </div>
    </div>
@endsection

@push('auth-styles')
<style>
    .emp-notice{display:flex;gap:12px;align-items:flex-start;background:color-mix(in srgb,#6366f1 8%,var(--page));border:1px solid color-mix(in srgb,#6366f1 28%,var(--border));border-radius:10px;padding:14px 16px;margin-bottom:20px;}
    .emp-notice .fa-circle-info{color:#4f46e5;font-size:15px;flex-shrink:0;margin-top:2px;}
    .emp-notice p{margin:0;font-size:13px;line-height:1.5;color:#3730a3;}
    .emp-masked-email{font-family:ui-monospace,monospace;letter-spacing:.04em;color:var(--text);}
</style>
@endpush
