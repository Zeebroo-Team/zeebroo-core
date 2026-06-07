@extends('theme::layouts.auth', ['title' => __('Create your password')])

@section('content')
    <header class="auth-brand">
        <div class="auth-brand__mark" aria-hidden="true"><i class="fa fa-lock"></i></div>
        <div class="auth-brand__text">
            <h1>{{ __('Create your password') }}</h1>
            <p>{{ __('Almost done') }}</p>
        </div>
    </header>
    <div class="auth-body">
        <p class="sub">{{ __('Choose a strong password for your new customer account.') }}</p>
        <form method="post" action="{{ route('register.employee-verify.password.submit') }}" autocomplete="off">
            @csrf
            <div class="field">
                <label for="password">{{ __('New password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" autofocus>
                <div class="error">@error('password'){{ $message }}@enderror</div>
            </div>
            <div class="field">
                <label for="password_confirmation">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                <div class="error">@error('password_confirmation'){{ $message }}@enderror</div>
            </div>
            <button type="submit" class="auth-btn">{{ __('Create account') }}</button>
        </form>
    </div>
@endsection
