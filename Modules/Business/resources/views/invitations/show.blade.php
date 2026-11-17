@extends('theme::layouts.auth', ['title' => __('Team invitation')])

@section('content')
    <header class="auth-brand">
        <div class="auth-brand__mark" aria-hidden="true"><i class="fa fa-user-plus"></i></div>
        <div class="auth-brand__text">
            <h1>{{ __('Team invitation') }}</h1>
            <p>{{ $invitation?->business?->name ?? config('app.name') }}</p>
        </div>
    </header>
    <div class="auth-body">
        @if(! $invitation || $invitation->status === \Modules\Business\Models\BusinessInvitation::STATUS_CANCELLED)
            <p class="sub inv-warn"><i class="fa fa-circle-xmark" aria-hidden="true"></i> {{ __('This invitation link is invalid or has been cancelled. Ask the business owner to send a new one.') }}</p>
        @elseif($invitation->status === \Modules\Business\Models\BusinessInvitation::STATUS_ACCEPTED)
            <p class="sub inv-ok"><i class="fa fa-circle-check" aria-hidden="true"></i> {{ __('This invitation has already been accepted. Sign in to access :business.', ['business' => $invitation->business->name]) }}</p>
        @elseif($invitation->isExpired())
            <p class="sub inv-warn"><i class="fa fa-clock" aria-hidden="true"></i> {{ __('This invitation has expired. Ask the business owner to resend it.') }}</p>
        @else
            <p class="sub">
                @if($hasAccount)
                    {{ __('You have been invited to join this business. Check your name and accept to add it to your Zeebroo account.') }}
                @else
                    {{ __('You have been invited to join this business. Choose a password to create your account.') }}
                @endif
            </p>

            <form method="post" action="{{ route('business.invitations.accept', $token) }}" autocomplete="off" data-submit-once>
                @csrf
                <div class="field">
                    <label for="inv-business">{{ __('Business') }}</label>
                    <input id="inv-business" type="text" value="{{ $invitation->business->name }}" readonly class="inv-readonly">
                </div>
                <div class="field">
                    <label for="inv-role">{{ __('Role') }}</label>
                    <input id="inv-role" type="text" value="{{ $roleName }}" readonly class="inv-readonly">
                </div>
                <div class="field">
                    <label for="name">{{ __('Your name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $accountName) }}" required autocomplete="name" autofocus>
                    <div class="error">@error('name'){{ $message }}@enderror</div>
                </div>
                <div class="field">
                    <label for="inv-email">{{ __('Email') }}</label>
                    <input id="inv-email" type="email" value="{{ $invitation->email }}" readonly class="inv-readonly">
                    <div class="error">@error('invitation'){{ $message }}@enderror</div>
                </div>

                @unless($hasAccount)
                    <div class="field">
                        <label for="password">{{ __('Password') }}</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password">
                        <div class="error">@error('password'){{ $message }}@enderror</div>
                    </div>
                    <div class="field">
                        <label for="password_confirmation">{{ __('Confirm password') }}</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    </div>
                @endunless

                @php($label = $hasAccount ? __('Accept invitation') : __('Create account & join'))
                <button type="submit" class="auth-btn" data-label="{{ $label }}" data-loading-text="{{ __('Joining…') }}">{{ $label }}</button>
            </form>
        @endif

        <div class="auth-alt-links" role="navigation" aria-label="{{ __('Other options') }}">
            <a href="{{ route('login') }}" class="auth-alt-pill">
                <i class="fa fa-arrow-right-to-bracket" aria-hidden="true"></i><span>{{ __('Sign in') }}</span>
            </a>
        </div>
    </div>
@endsection

@push('auth-styles')
<style>
    .inv-readonly{background:var(--surface-2, #f1f5f9);color:var(--text-muted, #64748b);cursor:not-allowed;}
    .inv-ok{color:#16a34a;}
    .inv-warn{color:#dc2626;}
</style>
@endpush
