@extends('theme::layouts.auth', ['title' => __('Forgot password')])

@section('content')
    <header class="auth-brand">
        <div class="auth-brand__mark" aria-hidden="true"><i class="fa fa-lock"></i></div>
        <div class="auth-brand__text">
            <h1>{{ __('Forgot password?') }}</h1>
            <p>{{ __('We\'ll email you a reset code') }}</p>
        </div>
    </header>
    <div class="auth-body">
        <p class="sub">{{ __('Enter the email address of your account and we\'ll send you a 6-digit code to reset your password.') }}</p>
        <form method="post" action="{{ route('password.email') }}" data-submit-once>
            @csrf
            <div class="field">
                <label for="email">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="{{ __('you@company.com') }}" autofocus>
                <div class="error">@error('email'){{ $message }}@enderror</div>
            </div>
            <button type="submit" class="auth-btn" data-label="{{ __('Send reset code') }}" data-loading-text="{{ __('Sending code…') }}">{{ __('Send reset code') }}</button>
        </form>
        <div class="auth-alt-links" role="navigation" aria-label="{{ __('Other options') }}">
            <a href="{{ route('login') }}" class="auth-alt-pill">
                <i class="fa fa-arrow-left" aria-hidden="true"></i><span>{{ __('Back to sign in') }}</span>
            </a>
        </div>
    </div>
@endsection
