@extends('theme::layouts.auth', ['title' => __('Enter verification code')])

@section('content')
    <header class="auth-brand">
        <div class="auth-brand__mark" aria-hidden="true"><i class="fa fa-key"></i></div>
        <div class="auth-brand__text">
            <h1>{{ __('Enter the code') }}</h1>
            <p>{{ __('Check your inbox') }}</p>
        </div>
    </header>
    <div class="auth-body">
        <p class="sub">{{ __('We sent a 6-digit verification code to') }} <strong class="emp-masked-email">{{ $maskedEmail }}</strong>. {{ __('It expires in 10 minutes.') }}</p>
        <form method="post" action="{{ route('register.employee-verify.otp.submit') }}" autocomplete="off">
            @csrf
            <div class="field">
                <label for="otp">{{ __('Verification code') }}</label>
                <input id="otp" name="otp" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6"
                    value="{{ old('otp') }}" required autocomplete="one-time-code"
                    placeholder="000000" class="otp-input" autofocus>
                <div class="error">@error('otp'){{ $message }}@enderror</div>
            </div>
            <button type="submit" class="auth-btn">{{ __('Verify code') }}</button>
        </form>
        <div class="auth-alt-links" role="navigation" aria-label="{{ __('Other options') }}">
            <a href="{{ route('register.employee-verify') }}" class="auth-alt-pill" title="{{ __('Go back and resend code') }}">
                <i class="fa fa-rotate-right" aria-hidden="true"></i><span>{{ __('Resend code') }}</span>
            </a>
        </div>
    </div>
@endsection

@push('auth-styles')
<style>
    .otp-input{text-align:center;letter-spacing:.35em;font-size:24px;font-weight:700;font-family:ui-monospace,monospace;}
    .emp-masked-email{font-family:ui-monospace,monospace;letter-spacing:.04em;color:var(--text);}
</style>
@endpush
