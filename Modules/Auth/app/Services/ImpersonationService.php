<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Lets an admin sign in as another user to reproduce what they see (errors, missing data),
 * then return to their own admin session. The admin's id is kept in the session so the
 * "Return to admin" bar can swap back without a password.
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';

    /** Session keys tied to whoever is signed in, which must not leak across the swap. */
    private const USER_SCOPED_SESSION_KEYS = ['selected_business_id'];

    public function cannotImpersonateReason(User $admin, User $target): ?string
    {
        if ((int) $admin->id === (int) $target->id) {
            return __('You are already signed in as this user.');
        }

        if ($target->hasRole('admin')) {
            return __('You cannot log in as another admin.');
        }

        if (! $target->is_active) {
            return __('This account is disabled. Enable it first to log in as this user.');
        }

        return null;
    }

    public function start(Request $request, User $admin, User $target): void
    {
        $this->clearUserScopedSession($request);

        Auth::login($target);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $admin->id);

        Log::info('Admin impersonation started', ['admin_id' => $admin->id, 'user_id' => $target->id, 'ip' => $request->ip()]);
    }

    public function isImpersonating(Request $request): bool
    {
        return $request->session()->has(self::SESSION_KEY);
    }

    /**
     * Swaps back to the original admin. Returns the user that was being impersonated,
     * or null when the session held no (valid) impersonation.
     */
    public function stop(Request $request): ?User
    {
        $adminId = $request->session()->pull(self::SESSION_KEY);
        $admin = $adminId ? User::find($adminId) : null;
        $impersonated = $request->user();

        if (! $admin || ! $admin->hasRole('admin')) {
            return null;
        }

        $this->clearUserScopedSession($request);

        Auth::login($admin);
        $request->session()->regenerate();

        Log::info('Admin impersonation ended', ['admin_id' => $admin->id, 'user_id' => $impersonated?->id]);

        return $impersonated;
    }

    private function clearUserScopedSession(Request $request): void
    {
        $request->session()->forget(self::USER_SCOPED_SESSION_KEYS);
    }
}
