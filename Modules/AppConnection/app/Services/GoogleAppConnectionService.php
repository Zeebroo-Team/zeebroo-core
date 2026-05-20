<?php

namespace Modules\AppConnection\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleOAuthUser;
use Modules\AppConnection\Models\UserAppConnection;

class GoogleAppConnectionService
{
    public function redirectToGoogle(): RedirectResponse
    {
        $scopes = ['openid', 'profile', 'email'];
        if (config('services.google.business_manage_scope')) {
            $scopes[] = 'https://www.googleapis.com/auth/business.manage';
        }
        $prompt = (string) config('services.google.oauth_prompt', 'select_account');
        if ($prompt === '') {
            $prompt = 'select_account';
        }

        return Socialite::driver('google')
            ->scopes($scopes)
            ->with([
                'access_type' => 'offline',
                'prompt' => $prompt,
            ])
            ->redirect();
    }

    /**
     * Complete OAuth and persist tokens for the authenticated user.
     */
    public function handleCallback(User $user): RedirectResponse
    {
        /** @var GoogleOAuthUser $googleUser */
        $googleUser = Socialite::driver('google')->user();

        $expiresAt = null;
        if ($googleUser->expiresIn !== null) {
            $expiresAt = Carbon::now()->addSeconds((int) $googleUser->expiresIn);
        }

        $existing = UserAppConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', UserAppConnection::PROVIDER_GOOGLE)
            ->first();

        $refreshToken = $googleUser->refreshToken ?? $existing?->refresh_token;

        DB::transaction(function () use ($user, $googleUser, $expiresAt, $refreshToken): void {
            UserAppConnection::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => UserAppConnection::PROVIDER_GOOGLE,
                ],
                [
                    'provider_user_id' => (string) $googleUser->getId(),
                    'email' => $googleUser->getEmail(),
                    'name' => $googleUser->getName(),
                    'access_token' => (string) $googleUser->token,
                    'refresh_token' => $refreshToken,
                    'token_expires_at' => $expiresAt,
                    'meta' => array_filter([
                        'avatar' => $googleUser->getAvatar(),
                        'nickname' => $googleUser->getNickname(),
                    ]),
                ],
            );
        });

        return redirect()->route('app-connection.index')
            ->with('status', __('Google account connected.'));
    }

    public function disconnect(User $user): RedirectResponse
    {
        UserAppConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', UserAppConnection::PROVIDER_GOOGLE)
            ->delete();

        return redirect()->route('app-connection.index')
            ->with('status', __('Google account disconnected.'));
    }
}
