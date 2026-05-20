@extends('theme::layouts.auth', ['title' => __('Sign in')])

@section('content')
    <header class="auth-brand">
        <div class="auth-brand__mark" aria-hidden="true"><i class="fa fa-right-to-bracket"></i></div>
        <div class="auth-brand__text">
            <h1>{{ __('Welcome back') }}</h1>
            <p>{{ __('Sign in to your workspace') }}</p>
        </div>
    </header>
    <div class="auth-body">
        <p class="sub">{{ __('Use your email and password to access your dashboard.') }}</p>
        <form method="post" action="{{ route('login.submit') }}" autocomplete="on">
            @csrf
            <div class="field">
                <label for="email">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="{{ __('you@company.com') }}">
                <div class="error">@error('email'){{ $message }}@enderror</div>
            </div>
            <div class="field">
                <label for="password">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">
                <div class="error">@error('password'){{ $message }}@enderror</div>
            </div>
            <div class="auth-check">
                <input id="remember" type="checkbox" name="remember" value="1">
                <label for="remember">{{ __('Remember this device') }}</label>
            </div>
            <button type="submit" class="auth-btn">{{ __('Sign in') }}</button>
        </form>
        @if(! empty($googleAuthConfigured))
            <div class="auth-divider" role="presentation"><span>{{ __('Or continue with') }}</span></div>
            <a class="auth-oauth" href="{{ route('auth.google.redirect') }}">
                <i class="fa-brands fa-google" aria-hidden="true"></i>{{ __('Continue with Google') }}
            </a>
        @endif
        <div class="auth-alt-links" role="navigation" aria-label="{{ __('Other options') }}">
            <a href="{{ route('register') }}" class="auth-alt-pill" title="{{ __('Create a new workspace account') }}">
                <i class="fa fa-user-plus" aria-hidden="true"></i><span>{{ __('Create account') }}</span>
            </a>
            <a href="{{ route('hr.portal.login') }}" class="auth-alt-pill auth-alt-pill--hr" title="{{ __('Employee HR portal sign-in') }}">
                <i class="fa fa-users-gear" aria-hidden="true"></i><span>{{ __('HR portal') }}</span>
            </a>
        </div>
    </div>
@endsection
