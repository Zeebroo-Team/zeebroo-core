<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Carries a signed-in web session over to the POS web app (/pos-app), and
 * keeps the two signed in / signed out together.
 *
 * The web dashboard uses a session cookie; the POS app uses a Sanctum Bearer
 * token kept in localStorage. GET /pos-app/launch (session auth) issues a
 * short-lived one-time code and redirects to /pos-app?handoff=<code>; the
 * browser shim swaps that code for a token at POST /api/v1/pos/auth/web-handoff.
 * The token itself never appears in a URL, and a code works only once.
 *
 * Tokens made this way are remembered against the web session that made them:
 * signing out of the website revokes them (RevokePosWebAppTokens), and signing
 * out of the POS web app ends the web session too (see browser-shim.js).
 */
class PosWebAppHandoffService
{
    public const TTL_SECONDS = 60;

    /** How long a web session's token list is kept — longer than any web session lives. */
    private const SESSION_TOKENS_TTL_SECONDS = 60 * 60 * 24 * 30;

    private const CODE_PREFIX = 'pos-web-handoff:';

    private const SESSION_PREFIX = 'pos-web-session-tokens:';

    /** Create a one-time code for this user and the business selected in the web session. */
    public function issue(User $user, ?int $businessId, ?string $sessionId = null): string
    {
        $code = Str::random(64);

        Cache::put($this->codeKey($code), [
            'user_id' => $user->id,
            'business_id' => $businessId,
            'session_id' => $sessionId,
        ], self::TTL_SECONDS);

        return $code;
    }

    /**
     * Use up a code and mint a POS API token.
     *
     * @return array{user: User, token: string, business_id: ?int}|null null when the code is unknown, expired, used or the account is disabled
     */
    public function redeem(string $code, string $deviceName): ?array
    {
        $data = Cache::pull($this->codeKey($code));
        if (! is_array($data)) {
            return null;
        }

        $user = User::query()->find($data['user_id'] ?? null);
        if (! $user instanceof User || ! $user->is_active) {
            return null;
        }

        $token = $user->createToken($deviceName);

        if (! empty($data['session_id'])) {
            $this->rememberToken((string) $data['session_id'], (int) $token->accessToken->getKey());
        }

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
            'business_id' => isset($data['business_id']) ? (int) $data['business_id'] : null,
        ];
    }

    /** Revoke every POS web-app token handed out from this web session. Returns how many. */
    public function revokeForSession(string $sessionId): int
    {
        $ids = Cache::pull($this->sessionKey($sessionId));
        if (! is_array($ids) || $ids === []) {
            return 0;
        }

        return PersonalAccessToken::query()->whereKey($ids)->delete();
    }

    /** @return list<int> */
    public function tokenIdsForSession(string $sessionId): array
    {
        return array_values((array) Cache::get($this->sessionKey($sessionId), []));
    }

    private function rememberToken(string $sessionId, int $tokenId): void
    {
        $ids = $this->tokenIdsForSession($sessionId);
        $ids[] = $tokenId;

        Cache::put($this->sessionKey($sessionId), array_values(array_unique($ids)), self::SESSION_TOKENS_TTL_SECONDS);
    }

    private function codeKey(string $code): string
    {
        return self::CODE_PREFIX.hash('sha256', $code);
    }

    private function sessionKey(string $sessionId): string
    {
        return self::SESSION_PREFIX.hash('sha256', $sessionId);
    }
}
