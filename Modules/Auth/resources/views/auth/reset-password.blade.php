@extends('theme::layouts.auth', ['title' => __('Reset password')])

@section('content')
    <header class="auth-brand">
        <div class="auth-brand__mark" aria-hidden="true"><i class="fa fa-key"></i></div>
        <div class="auth-brand__text">
            <h1>{{ __('Reset your password') }}</h1>
            <p>{{ __('Check your inbox') }}</p>
        </div>
    </header>
    <div class="auth-body">
        @if(session('status'))
            <p class="sub pwd-status"><i class="fa fa-circle-check" aria-hidden="true"></i> {{ session('status') }}</p>
        @endif
        <p class="sub">{{ __('Enter the code sent to') }} <strong class="pwd-email">{{ $email }}</strong> {{ __('and choose a new password. The code expires in :minutes minutes.', ['minutes' => $minutes]) }}</p>
        <form method="post" action="{{ route('password.update') }}" autocomplete="off" data-submit-once>
            @csrf
            <div class="field">
                <label for="otp">{{ __('Reset code') }}</label>
                <input id="otp" name="otp" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6"
                    value="{{ old('otp') }}" required autocomplete="one-time-code"
                    placeholder="000000" class="otp-input" autofocus>
                <div class="error">@error('otp'){{ $message }}@enderror</div>
            </div>
            <div class="field">
                <label for="password">{{ __('New password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="new-password">
                <div class="error">@error('password'){{ $message }}@enderror</div>
            </div>
            <div class="field">
                <label for="password_confirmation">{{ __('Confirm new password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
            </div>
            <button type="submit" class="auth-btn" data-label="{{ __('Reset password') }}" data-loading-text="{{ __('Resetting…') }}">{{ __('Reset password') }}</button>
        </form>
        <div class="auth-alt-links" role="navigation" aria-label="{{ __('Other options') }}">
            <a href="{{ route('password.request') }}" class="auth-alt-pill" title="{{ __('Request a new code') }}">
                <i class="fa fa-rotate-right" aria-hidden="true"></i><span>{{ __('Resend code') }}</span>
            </a>
            <a href="{{ route('login') }}" class="auth-alt-pill">
                <i class="fa fa-arrow-left" aria-hidden="true"></i><span>{{ __('Back to sign in') }}</span>
            </a>
        </div>
    </div>
@endsection

@push('auth-styles')
<style>
    .otp-input{text-align:center;letter-spacing:.35em;font-size:24px;font-weight:700;font-family:ui-monospace,monospace;}
    .pwd-email{color:var(--text);word-break:break-all;}
    .pwd-status{color:#16a34a;}
</style>
@endpush
