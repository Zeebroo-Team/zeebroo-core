<?php

namespace Modules\Pos\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/**
 * Server-side package gate: a business whose package doesn't include a
 * feature can't use that module's endpoints, even by calling them directly
 * (the UI hiding a menu isn't enough). Data stays in the database — it just
 * can't be reached until the business moves to a package with the feature.
 *
 * API usage: the feature is derived from the first path segment after v1/pos/
 * (see URI_FEATURES). Web usage: pass the key, e.g. EnsureFeatureInPlan::class.':crm'.
 */
class EnsureFeatureInPlan
{
    use ResolvesPosBusinessForApi;

    /** v1/pos/{segment}/... => feature key */
    private const URI_FEATURES = [
        'crm' => 'crm',
        'ecom' => 'ecommerce',
        'mail' => 'mail',
        'automations' => 'automation_editor',
        'automation-notifications' => 'automation_editor',
        'developers' => 'developers',
    ];

    public function handle(Request $request, Closure $next, ?string $feature = null)
    {
        $feature ??= $this->featureFromUri($request);
        if ($feature === null) {
            return $next($request);
        }

        $business = $request->is('api/*')
            ? $this->resolveBusinessForApi($request)
            : Business::currentForNavbar($request->user());

        if (! $business instanceof Business) {
            return $next($request); // let the controller report its own error
        }

        if (in_array($feature, $business->effectiveFeatureKeys(), true)) {
            return $next($request);
        }

        $label = config('features.list.'.$feature, $feature);
        $message = $label.' is not included in your current plan. Upgrade your plan to use it again — your data is kept safe.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return new JsonResponse([
                'message' => $message,
                'code' => 'feature_not_in_plan',
                'feature' => $feature,
            ], 403);
        }

        return redirect()->route('dashboard')->withErrors(['feature' => $message]);
    }

    private function featureFromUri(Request $request): ?string
    {
        if (! preg_match('#^api/v1/pos/([a-z-]+)#', $request->path(), $m)) {
            return null;
        }

        return self::URI_FEATURES[$m[1]] ?? null;
    }
}
