<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users who only have an HR employee profile (no owned businesses) may only use HR portal routes (not workspace or settings).
 */
final class RedirectHrPortalOnlyUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isHrPortalOnlyUser()) {
            return $next($request);
        }

        if ($this->allowsPortalOnlyRequest($request)) {
            return $next($request);
        }

        return redirect()
            ->route('hr.portal.dashboard')
            ->with('status', __('Your account only has access to the HR employee portal.'));
    }

    private function allowsPortalOnlyRequest(Request $request): bool
    {
        $route = $request->route();
        $name = $route?->getName();

        if (is_string($name)) {
            if (str_starts_with($name, 'hr.portal.')) {
                return true;
            }

            if ($name === 'logout') {
                return true;
            }

            return false;
        }

        $path = ltrim($request->path(), '/');

        if (str_starts_with($path, 'hr-portal')) {
            return true;
        }

        if ($path === 'logout') {
            return true;
        }

        return false;
    }
}
