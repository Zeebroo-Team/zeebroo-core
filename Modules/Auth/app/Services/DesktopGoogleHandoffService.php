<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Carries a Google sign-in done in the system browser back to the desktop app.
 *
 * The desktop app opens GET /auth/google/desktop?state=<random> in the browser.
 * After Google returns, the callback issues a short-lived one-time code bound to
 * that state and sends the browser to socibiz://oauth?code=…&state=…. The app
 * swaps code + state for a Sanctum token at POST /api/v1/pos/auth/google/exchange.
 *
 * The token never appears in a URL, a code works only once, and it only works
 * together with the state the app generated, so a code read from browser history
 * or intercepted by another app is useless on its own.
 */
final class DesktopGoogleHandoffService
{
    public const TTL_SECONDS = 300;

    private const CODE_PREFIX = 'desktop-google-handoff:';

    /** A state is the hex string the desktop app generates (crypto.randomBytes). */
    public static function isValidState(mixed $state): bool
    {
        return is_string($state) && preg_match('/^[A-Za-z0-9_-]{32,128}$/', $state) === 1;
    }

    public function issue(User $user, string $state, bool $isNewUser): string
    {
        $code = Str::random(64);

        Cache::put($this->codeKey($code), [
            'user_id' => $user->id,
            'state_hash' => hash('sha256', $state),
            'is_new_user' => $isNewUser,
        ], self::TTL_SECONDS);

        return $code;
    }

    /**
     * Use up a code. Returns null when the code is unknown, expired, already used,
     * does not match the state, or the account is disabled.
     *
     * @return array{user: User, is_new_user: bool}|null
     */
    public function redeem(string $code, string $state): ?array
    {
        $data = Cache::pull($this->codeKey($code));
        if (! is_array($data)) {
            return null;
        }

        if (! hash_equals((string) ($data['state_hash'] ?? ''), hash('sha256', $state))) {
            return null;
        }

        $user = User::query()->find($data['user_id'] ?? null);
        if (! $user instanceof User || ! $user->is_active) {
            return null;
        }

        return [
            'user' => $user,
            'is_new_user' => (bool) ($data['is_new_user'] ?? false),
        ];
    }

    private function codeKey(string $code): string
    {
        return self::CODE_PREFIX.hash('sha256', $code);
    }
}
