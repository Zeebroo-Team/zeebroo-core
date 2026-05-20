@extends('theme::layouts.auth', ['title' => __('Employee HR portal')])

@section('content')
    <header class="auth-brand">
        <div class="auth-brand__mark" aria-hidden="true"><i class="fa fa-users-gear"></i></div>
        <div class="auth-brand__text">
            <h1>{{ __('Employee HR portal') }}</h1>
            <p>{{ __('Sign in with the email your employer has on file') }}</p>
        </div>
    </header>
    <div class="auth-body">
        @if(! empty($hasAccountButNoEmployee))
            <p class="sub" role="status" style="border:1px solid var(--border);padding:12px;border-radius:10px;background:color-mix(in srgb,var(--card)96%,transparent);">
                {{ __('You are signed in, but no employee profile matches this account. Sign out and use the email stored in HR (personal email on your record), or ask your administrator to link your login.') }}
            </p>
            <form method="post" action="{{ route('logout') }}" style="margin:0 0 18px;">
                @csrf
                <button type="submit" class="auth-btn" style="width:100%;background:transparent;color:var(--text);border:2px solid var(--border);">{{ __('Sign out') }}</button>
            </form>
        @endif
        <p class="sub">{{ __('Use the same SociBiz account as your team. We connect your profile when your work email matches your HR record.') }}</p>
        <form method="post" action="{{ route('hr.portal.login.submit') }}" autocomplete="on">
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
            <button type="submit" class="auth-btn">{{ __('Sign in to HR portal') }}</button>
        </form>
        @if(! empty($googleAuthConfigured))
            <div class="auth-divider" role="presentation"><span>{{ __('Or continue with') }}</span></div>
            <a class="auth-oauth" href="{{ route('auth.google.redirect') }}">
                <i class="fa-brands fa-google" aria-hidden="true"></i>{{ __('Continue with Google') }}
            </a>
        @endif
        <p class="auth-meta">{!! __('Need the business workspace? :link', ['link' => '<a href="'.e(route('login')).'">'.__('Main sign in').'</a>']) !!}</p>
    </div>
@endsection
