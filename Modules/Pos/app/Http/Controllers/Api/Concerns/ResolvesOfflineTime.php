<?php

namespace Modules\Pos\Http\Controllers\Api\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\PosOfflineSaleApiController;

/**
 * Desktop offline mode: an action queued while offline is uploaded later
 * with `occurred_at` = when it really happened, so it counts on the right
 * day (cash drawer), settles only the sales made before it (end of day), and
 * dates / late fees are worked out at that time.
 *
 * Returns null when the request has no occurred_at (a normal online call).
 * Same limits as offline sales: at most 7 days old; a clock running ahead
 * is pulled back to now.
 */
trait ResolvesOfflineTime
{
    protected function offlineOccurredAt(Request $request): ?CarbonImmutable
    {
        $raw = $request->input('occurred_at');
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            // The desktop sends UTC (…Z); work in the app's own timezone.
            $at = CarbonImmutable::parse((string) $raw)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            throw new HttpResponseException(response()->json([
                'message' => 'occurred_at is not a valid date.',
                'errors'  => ['occurred_at' => ['occurred_at is not a valid date.']],
            ], 422));
        }

        $maxDays = PosOfflineSaleApiController::MAX_AGE_DAYS;
        if ($at->lt(now()->subDays($maxDays))) {
            throw new HttpResponseException(response()->json([
                'message' => "This offline action is older than {$maxDays} days and can no longer be uploaded.",
                'code'    => 'offline_action_too_old',
                'errors'  => ['occurred_at' => ["Offline actions must be uploaded within {$maxDays} days."]],
            ], 422));
        }

        return $at->gt(now()) ? CarbonImmutable::now() : $at;
    }
}
