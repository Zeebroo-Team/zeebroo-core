<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Auth\Services\GoogleAuthService;
use Symfony\Component\HttpFoundation\Response;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleAuthService $googleAuthService) {}

    /** @return Response|RedirectResponse */
    public function redirect(Request $request)
    {
        $backRoute = $request->query('return') === 'register' ? 'register' : 'login';

        if (! $this->googleAuthService->isConfigured()) {
            return redirect()
                ->route($backRoute)
                ->withErrors([
                    'email' => __('Google sign-in is not configured. Add GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and authorize the redirect URI in Google Cloud Console.'),
                ]);
        }

        $request->session()->put('oauth_auth_fail_route', $backRoute);

        return $this->googleAuthService->redirectToGoogle();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->googleAuthService->isConfigured()) {
            return redirect()->route('login')->withErrors([
                'email' => __('Google sign-in is not available.'),
            ]);
        }

        return $this->googleAuthService->handleCallback($request);
    }
}
