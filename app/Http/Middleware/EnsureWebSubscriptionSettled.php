<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks the web workspace once the current business's subscription payment
 * has passed its due_at deadline, or the subscription has fully ended —
 * mirrors Modules\Pos\Http\Middleware\EnsureSubscriptionSettled, which
 * already does this for the desktop app's API. Billing, auth, and
 * business/account-switching routes stay reachable so the business can
 * actually resolve the overdue payment.
 */
final class EnsureWebSubscriptionSettled
{
    private const EXEMPT_ROUTE_PREFIXES = [
        'payment.',
        'business.platform-choice',
        'business.select',
        'account.select',
    ];

    private const EXEMPT_ROUTE_NAMES = [
        'login',
        'logout',
        'privacy-policy',
        'terms-of-service',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $this->isExempt($request)) {
            return $next($request);
        }

        $business = Business::currentForNavbar($user);
        if ($business === null) {
            return $next($request);
        }

        $overdue = $business->overdueSubscriptionPayment();
        $ended = ! $overdue && $business->subscriptionHasEnded();

        if (! $overdue && ! $ended) {
            return $next($request);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'message' => $ended
                    ? 'Your subscription has ended. Renew it to continue using Zeebroo.'
                    : 'Your subscription payment is overdue. Please settle it to continue using Zeebroo.',
                'code' => $ended ? 'subscription_ended' : 'subscription_payment_overdue',
            ], 402);
        }

        return redirect()->route('payment.billing.index')->with(
            'status',
            $ended
                ? 'Your subscription has ended. Renew it below to regain access.'
                : 'Your subscription payment is overdue. Complete payment below to regain access.'
        );
    }

    private function isExempt(Request $request): bool
    {
        $name = $request->route()?->getName();
        if (! is_string($name) || $name === '') {
            return false;
        }

        if (in_array($name, self::EXEMPT_ROUTE_NAMES, true)) {
            return true;
        }

        foreach (self::EXEMPT_ROUTE_PREFIXES as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
