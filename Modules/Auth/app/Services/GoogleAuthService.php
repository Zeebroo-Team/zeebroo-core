<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Modules\Auth\Models\AffiliateReferral;
use Modules\Mail\Services\AutomatedEmailService;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

final class GoogleAuthService
{
    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectToGoogle(): Response|RedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl($this->authRedirectUrl())
            ->scopes(['openid', 'profile', 'email'])
            ->with([
                'prompt' => 'select_account',
            ])
            ->redirect();
    }

    public function handleCallback(Request $request): Response
    {
        $failRoute    = (string) $request->session()->pull('oauth_auth_fail_route', 'login');
        $portalOrigin = (bool)   $request->session()->pull('oauth_origin_portal', false);
        $desktopState = $request->session()->pull('oauth_desktop_state');
        $desktopState = DesktopGoogleHandoffService::isValidState($desktopState) ? (string) $desktopState : null;

        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->authRedirectUrl())
                ->user();
        } catch (\Throwable) {
            $message = __('Google sign-in was cancelled or failed. Please try again.');

            return $desktopState !== null
                ? $this->desktopReturn($desktopState, null, $message)
                : redirect()->route($failRoute)->withErrors(['email' => $message]);
        }

        $email = $googleUser->getEmail();
        if (! is_string($email) || $email === '') {
            $message = __('Your Google account did not share an email address.');

            return $desktopState !== null
                ? $this->desktopReturn($desktopState, null, $message)
                : redirect()->route($failRoute)->withErrors(['email' => $message]);
        }

        $gid = (string) $googleUser->getId();

        // ── HR portal origin: employee must already exist — never auto-create ──────
        if ($portalOrigin) {
            return $this->handlePortalCallback($gid, $email, $request);
        }

        // ── Main workspace flow (web and desktop app) ──────────────────────────────
        $resolved = $this->resolveWorkspaceUser($googleUser, $gid, $email);
        if (is_string($resolved)) {
            return $desktopState !== null
                ? $this->desktopReturn($desktopState, null, $resolved)
                : redirect()->route($failRoute)->withErrors(['email' => $resolved]);
        }

        [$user, $isNewUser] = $resolved;

        // Google sign-up after opening an affiliate's shared link: credit that affiliate.
        if ($isNewUser) {
            $affiliates = app(AffiliateService::class);
            $affiliates->recordReferral($user, $affiliates->pendingCode($request), AffiliateReferral::PLATFORM_GOOGLE);
            $affiliates->forgetPending($request);
        }

        // ── Desktop app: hand a one-time code back to the app, don't sign the browser in ─
        if ($desktopState !== null) {
            if (! $user->is_active) {
                return $this->desktopReturn($desktopState, null, __('Your account has been disabled. Contact an administrator.'));
            }

            UserActivityLog::record(
                $user,
                UserActivityLog::PLATFORM_DESKTOP,
                $isNewUser ? UserActivityLog::EVENT_REGISTER : UserActivityLog::EVENT_LOGIN,
                $request,
                'google-desktop',
            );

            $code = app(DesktopGoogleHandoffService::class)->issue($user, $desktopState, $isNewUser);

            return $this->desktopReturn($desktopState, $code, null);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        UserActivityLog::record(
            $user,
            UserActivityLog::PLATFORM_WEB,
            $isNewUser ? UserActivityLog::EVENT_REGISTER : UserActivityLog::EVENT_LOGIN,
            $request,
        );

        if ($user->isHrPortalOnlyUser()) {
            return redirect()->route('hr.portal.dashboard');
        }

        if ($user->hasRole('admin')) {
            return redirect()->intended(route('admin.panel'));
        }

        if ($user->hasRole(AffiliateService::ROLE)) {
            return redirect()->intended(route('affiliate.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Find the workspace user for this Google account, linking it by email or
     * creating a new account (Google sign-up) when needed.
     *
     * @return array{0: User, 1: bool}|string [user, isNewUser], or an error message
     */
    private function resolveWorkspaceUser(SocialiteUser $googleUser, string $gid, string $email): array|string
    {
        $user = User::query()->where('google_id', $gid)->first();
        $isNewUser = false;

        if ($user === null) {
            $existing = User::query()->where('email', $email)->first();
            if ($existing !== null) {
                if ($existing->google_id !== null && $existing->google_id !== $gid) {
                    return __('This email is already linked to a different Google account.');
                }
                $existing->forceFill(['google_id' => $gid])->save();
                $user = $existing->refresh();
            } else {
                $name = $googleUser->getName();
                if (! is_string($name) || trim($name) === '') {
                    $name = (string) (data_get($googleUser->user, 'given_name') ?: __('Google user'));
                }

                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                    'google_id' => $gid,
                    'email_verified_at' => now(),
                ]);
                Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
                $user->assignRole('user');
                $isNewUser = true;
                app(AutomatedEmailService::class)->queueWelcome($user);
            }
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return [$user, $isNewUser];
    }

    /**
     * Page shown in the browser at the end of a desktop-app Google sign-in. It
     * opens socibiz://oauth so the app picks up the one-time code (or the error).
     */
    private function desktopReturn(string $state, ?string $code, ?string $error): Response
    {
        $query = $code !== null
            ? ['code' => $code, 'state' => $state]
            : ['error' => (string) $error, 'state' => $state];

        return response()
            ->view('auth::auth.google-desktop-return', [
                'deepLink' => 'socibiz://oauth?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986),
                'success' => $code !== null,
                'error' => $error,
            ])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Portal-specific callback: the employee account MUST already exist.
     * We never auto-create a new user from the portal — employees are provisioned
     * by HR admins, not by self-registration.
     */
    private function handlePortalCallback(string $gid, string $email, Request $request): RedirectResponse
    {
        // Find by google_id first (returning user), then by email (first-time Google link)
        $user = User::query()->where('google_id', $gid)->first()
            ?? User::query()->where('email', $email)->first();

        if ($user === null) {
            return redirect()->route('hr.portal.login')->withErrors([
                'email' => __('No employee account was found for this Google email address. Please contact your HR administrator.'),
            ]);
        }

        // Guard: email already linked to a different Google account
        if ($user->google_id !== null && $user->google_id !== $gid) {
            return redirect()->route('hr.portal.login')->withErrors([
                'email' => __('This email is already linked to a different Google account.'),
            ]);
        }

        // First-time Google link for this employee account
        if ($user->google_id === null) {
            $user->forceFill(['google_id' => $gid])->save();
            $user = $user->refresh();
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        UserActivityLog::record($user, UserActivityLog::PLATFORM_WEB, UserActivityLog::EVENT_LOGIN, $request);

        return redirect()->route('hr.portal.dashboard');
    }

    private function authRedirectUrl(): string
    {
        $fromEnv = config('services.google.auth_redirect');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        return route('auth.google.callback', [], true);
    }
}
