<?php

namespace Modules\Pos\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/**
 * Blocks Pos API access once a business's subscription payment has passed its
 * `due_at` deadline (see Payment::GRACE_PERIOD_DAYS). Routes the desktop app
 * needs even while locked — auth/me, billing endpoints, business switching,
 * notifications — are exempted via ->withoutMiddleware() in routes/api.php.
 */
class EnsureSubscriptionSettled
{
    use ResolvesPosBusinessForApi;

    public function handle(Request $request, Closure $next)
    {
        $business = $this->resolveBusinessForApi($request);
        if ($business instanceof JsonResponse) {
            // Let the real controller report its own business-resolution error.
            return $next($request);
        }

        $overdue = $business->overdueSubscriptionPayment();
        if ($overdue) {
            $requester = $request->user();
            $canPay = $requester instanceof User && (int) $overdue->user_id === (int) $requester->id;

            return response()->json([
                'message' => 'Your subscription payment is overdue. Please settle it to continue using Zeebroo POS.',
                'code' => 'subscription_payment_overdue',
                'payment_id' => $overdue->id,
                'due_at' => $overdue->due_at?->toIso8601String(),
                'can_pay' => $canPay,
            ], 402);
        }

        if ($business->subscriptionHasEnded()) {
            return response()->json([
                'message' => 'Your subscription has ended. Renew it to continue using Zeebroo POS.',
                'code' => 'subscription_ended',
            ], 402);
        }

        return $next($request);
    }
}
