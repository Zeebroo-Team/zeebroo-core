@extends('theme::layouts.auth', ['title' => __('Create account')])

@php
    $initialStep = (int) old('register_step', 1);
    if ($errors->any()) {
        if (old('register_step') == 2 || old('register_step') === '2') {
            if ($errors->has('password')) {
                $initialStep = 1;
            } elseif ($errors->has('email') && (string) old('email') === (string) old('email_confirmation')) {
                $initialStep = 1;
            } else {
                $initialStep = 2;
            }
        } else {
            $initialStep = 1;
        }
    }
@endphp

@section('content')
    <header class="auth-brand">
        <div class="auth-brand__mark" aria-hidden="true"><i class="fa fa-user-plus"></i></div>
        <div class="auth-brand__text">
            <h1>{{ __('Create your account') }}</h1>
            <p>{{ __('Get started in two quick steps') }}</p>
        </div>
    </header>
    <div class="auth-body">
        <p class="register-steps" aria-live="polite">
            <span class="register-steps__label">{{ __('Step') }} <strong id="registerStepNum">{{ $initialStep }}</strong> {{ __('of') }} 2</span>
            <span class="register-steps__dots" role="presentation">
                <span class="register-steps__dot @if($initialStep === 1) is-active @endif" data-step-dot="1"></span>
                <span class="register-steps__dot @if($initialStep === 2) is-active @endif" data-step-dot="2"></span>
            </span>
        </p>
        <p class="sub">{{ __('New accounts receive standard user access. Administrative roles are assigned separately by your organization.') }}</p>
        <form method="post" action="{{ route('register.submit') }}" autocomplete="on" id="registerForm" class="register-form">
            @csrf
            <input type="hidden" name="register_step" id="registerStepField" value="{{ old('register_step', $initialStep) }}">

            <div class="register-panel" id="registerPanel1" @if($initialStep !== 1) hidden @endif>
                <div class="field">
                    <label for="email">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="{{ __('you@company.com') }}">
                    <div class="error">@error('email'){{ $message }}@enderror</div>
                </div>
                <div class="field">
                    <label for="password">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password">
                    <div class="error">@error('password'){{ $message }}@enderror</div>
                </div>
                <button type="button" class="auth-btn register-form__next" id="registerNextBtn">{{ __('Continue') }}</button>
            </div>

            <div class="register-panel" id="registerPanel2" @if($initialStep !== 2) hidden @endif>
                <div class="field">
                    <label for="name">{{ __('Full name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="255" placeholder="{{ __('Your name') }}" @if($initialStep === 2) required @endif>
                    <div class="error">@error('name'){{ $message }}@enderror</div>
                </div>
                <div class="field">
                    <label for="email_confirmation">{{ __('Confirm your email') }}</label>
                    <input id="email_confirmation" name="email_confirmation" type="email" value="{{ old('email_confirmation') }}" autocomplete="email" placeholder="{{ __('Re-enter your email') }}" @if($initialStep === 2) required @endif>
                    <div class="error">@error('email_confirmation'){{ $message }}@enderror</div>
                </div>
                <div class="register-form__actions">
                    <button type="button" class="auth-btn auth-btn--secondary register-form__back" id="registerBackBtn">{{ __('Back') }}</button>
                    <button type="submit" class="auth-btn register-form__submit">{{ __('Create account') }}</button>
                </div>
            </div>
        </form>
        @if(! empty($googleAuthConfigured))
            <div class="auth-divider" role="presentation"><span>{{ __('or') }}</span></div>
            <a class="auth-oauth" href="{{ route('auth.google.redirect', ['return' => 'register']) }}" aria-label="{{ __('Sign up with Google') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                {{ __('Sign up with Google') }}
            </a>
        @endif
        <div class="auth-alt-links" role="navigation" aria-label="{{ __('Other options') }}">
            <a href="{{ route('login') }}" class="auth-alt-pill" title="{{ __('Sign in with an existing account') }}">
                <i class="fa fa-right-to-bracket" aria-hidden="true"></i><span>{{ __('Sign in') }}</span>
            </a>
            <a href="{{ route('hr.portal.login') }}" class="auth-alt-pill auth-alt-pill--hr" title="{{ __('Employee HR portal sign-in') }}">
                <i class="fa fa-users-gear" aria-hidden="true"></i><span>{{ __('HR portal') }}</span>
            </a>
        </div>
    </div>
@endsection

@push('auth-styles')
    <style>
        .register-steps{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 12px;padding:0;font-size:13px;color:var(--muted);font-weight:600;}
        .register-steps__label strong{color:var(--text);font-weight:800;}
        .register-steps__dots{display:flex;gap:8px;}
        .register-steps__dot{width:8px;height:8px;border-radius:50%;background:var(--border);transition:background .2s ease,transform .2s ease;}
        .register-steps__dot.is-active{background:var(--btn);transform:scale(1.15);}
        .register-form__actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px;}
        .auth-btn--secondary{background:var(--card);color:var(--text);border-color:var(--border);}
        .auth-btn--secondary:hover{background:var(--btn-hover);color:var(--btn-hover-text);border-color:var(--btn-hover);}
    </style>
@endpush

@push('auth-scripts')
    <script>
    (function () {
        var form = document.getElementById('registerForm');
        if (!form) return;
        var p1 = document.getElementById('registerPanel1');
        var p2 = document.getElementById('registerPanel2');
        var stepField = document.getElementById('registerStepField');
        var stepNum = document.getElementById('registerStepNum');
        var dots = [document.querySelector('[data-step-dot="1"]'), document.querySelector('[data-step-dot="2"]')];
        var email = document.getElementById('email');
        var pass = document.getElementById('password');
        var emailC = document.getElementById('email_confirmation');
        var nameEl = document.getElementById('name');
        var initialStep = {{ (int) $initialStep }};

        function updateRequiredForStep(n) {
            if (n === 2) {
                if (nameEl) nameEl.setAttribute('required', 'required');
                if (emailC) emailC.setAttribute('required', 'required');
            } else {
                if (nameEl) nameEl.removeAttribute('required');
                if (emailC) emailC.removeAttribute('required');
            }
        }

        function setStep(n) {
            var is1 = n === 1;
            p1.hidden = !is1;
            p2.hidden = is1;
            stepField.value = n;
            if (stepNum) stepNum.textContent = n;
            dots.forEach(function (d, i) {
                if (d) d.classList.toggle('is-active', i + 1 === n);
            });
            updateRequiredForStep(n);
            // If step 2 is shown without a confirmation yet (e.g. deep-link / odd state), align once.
            if (!is1 && email && emailC && !emailC.value) {
                emailC.value = email.value;
            }
        }

        document.getElementById('registerNextBtn').addEventListener('click', function () {
            if (!email.checkValidity()) {
                email.reportValidity();
                return;
            }
            if (!pass.value) {
                pass.setCustomValidity({!! json_encode(__('Please enter a password.')) !!});
                pass.reportValidity();
                pass.setCustomValidity('');
                return;
            }
            pass.setCustomValidity('');
            // Always match confirmation to the current email when leaving step 1 so a stale
            // re-enter field cannot fail Laravel's `confirmed` rule after the user edits email and continues again.
            if (emailC) {
                emailC.value = email.value;
            }
            setStep(2);
            if (nameEl) nameEl.focus();
        });

        document.getElementById('registerBackBtn').addEventListener('click', function () {
            if (emailC) {
                emailC.value = '';
            }
            setStep(1);
            if (email) email.focus();
        });

        form.addEventListener('submit', function (e) {
            if (String(stepField.value) === '1') {
                e.preventDefault();
                document.getElementById('registerNextBtn').click();
            }
        });

        setStep(initialStep);
    })();
    </script>
@endpush
