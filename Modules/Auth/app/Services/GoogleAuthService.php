<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
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

    public function handleCallback(Request $request): RedirectResponse
    {
        $failRoute = (string) $request->session()->pull('oauth_auth_fail_route', 'login');

        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->authRedirectUrl())
                ->user();
        } catch (\Throwable) {
            return redirect()->route($failRoute)->withErrors([
                'email' => __('Google sign-in was cancelled or failed. Please try again.'),
            ]);
        }

        $email = $googleUser->getEmail();
        if (! is_string($email) || $email === '') {
            return redirect()->route($failRoute)->withErrors([
                'email' => __('Your Google account did not share an email address.'),
            ]);
        }

        $gid = (string) $googleUser->getId();

        $user = User::query()->where('google_id', $gid)->first();

        if ($user === null) {
            $existing = User::query()->where('email', $email)->first();
            if ($existing !== null) {
                if ($existing->google_id !== null && $existing->google_id !== $gid) {
                    return redirect()->route($failRoute)->withErrors([
                        'email' => __('This email is already linked to a different Google account.'),
                    ]);
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
            }
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        if ($user->isHrPortalOnlyUser()) {
            return redirect()->route('hr.portal.dashboard');
        }

        return redirect()->intended(route('dashboard'));
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
