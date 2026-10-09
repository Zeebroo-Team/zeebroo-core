<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records when a user last used the web app or the desktop/mobile API (users.last_seen_at),
 * which drives the admin "inactivity reminder" email. Written at most every few minutes.
 */
final class TrackLastSeen
{
    private const THROTTLE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Resolved after the route ran so API requests see the user that auth:sanctum authenticated.
        try {
            $user = $request->user();

            if ($user instanceof User && ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subMinutes(self::THROTTLE_MINUTES)))) {
                // Base query: don't bump updated_at or fire model events for a heartbeat.
                User::query()->whereKey($user->getKey())->toBase()->update(['last_seen_at' => now()]);
            }
        } catch (Throwable) {
            // Tracking must never break a request.
        }

        return $response;
    }
}
