<?php

namespace Modules\Pos\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exactly-once POS writes for the desktop app's offline queue.
 *
 * A write sent with an `Idempotency-Key` header runs once: its successful
 * answer is stored, and any retry with the same key (e.g. the connection
 * dropped before the app got the answer) gets that stored answer back with
 * an `Idempotent-Replayed: true` header instead of running again.
 *
 * Requests without the header — every normal online call — are untouched.
 * Only 2xx answers are stored, so a rejected action can be retried after the
 * user fixes the cause.
 */
class PosIdempotency
{
    public const HEADER = 'Idempotency-Key';

    private const KEEP_DAYS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header(self::HEADER, ''));
        if ($key === '' || $request->isMethodSafe()) {
            return $next($request);
        }
        if (strlen($key) > 100) {
            return response()->json(['message' => 'Idempotency-Key is too long.'], 422);
        }

        $scope = hash('sha256', ((string) $request->header('X-Business-Id', '')).'|'.$key);

        // Serialise two copies of the same upload racing each other.
        return Cache::lock('pos-idempotency:'.$scope, 120)->block(30, function () use ($request, $next, $scope) {
            $saved = DB::table('pos_idempotency_keys')->where('scope', $scope)->first();
            if ($saved !== null) {
                if ($saved->method !== $request->method() || $saved->path !== $request->path()) {
                    return response()->json(['message' => 'This Idempotency-Key was already used for a different request.'], 422);
                }

                return response($saved->response, (int) $saved->status_code)
                    ->header('Content-Type', 'application/json')
                    ->header('Idempotent-Replayed', 'true');
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                DB::table('pos_idempotency_keys')->insertOrIgnore([
                    'scope'       => $scope,
                    'method'      => $request->method(),
                    'path'        => mb_substr($request->path(), 0, 255),
                    'status_code' => $response->getStatusCode(),
                    'response'    => (string) $response->getContent(),
                    'created_at'  => now(),
                ]);
                // Keep the table small: old keys can't be retried anyway (offline uploads expire after 7 days).
                if (random_int(1, 100) === 1) {
                    DB::table('pos_idempotency_keys')->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
                }
            }

            return $response;
        });
    }
}
