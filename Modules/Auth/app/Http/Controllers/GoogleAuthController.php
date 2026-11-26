<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Auth\Services\DesktopGoogleHandoffService;
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
        // A desktop sign-in abandoned earlier in this browser must not hijack this one.
        $request->session()->forget('oauth_desktop_state');

        return $this->googleAuthService->redirectToGoogle();
    }

    /**
     * GET /auth/google/desktop?state=… - opened in the system browser by the
     * desktop app's "Continue with Google" button. Works whether or not this
     * browser is already signed in to the website; the website session is not
     * touched. See DesktopGoogleHandoffService.
     *
     * @return Response|RedirectResponse
     */
    public function desktopRedirect(Request $request)
    {
        $state = $request->query('state');
        if (! DesktopGoogleHandoffService::isValidState($state)) {
            abort(400, __('Invalid sign-in request. Start again from the Zeebroo POS app.'));
        }

        if (! $this->googleAuthService->isConfigured()) {
            return response()->view('auth::auth.google-desktop-return', [
                'deepLink' => 'socibiz://oauth?'.http_build_query([
                    'error' => __('Google sign-in is not available right now.'),
                    'state' => $state,
                ], '', '&', PHP_QUERY_RFC3986),
                'success' => false,
                'error' => __('Google sign-in is not available right now.'),
            ]);
        }

        $request->session()->put('oauth_desktop_state', (string) $state);
        $request->session()->put('oauth_auth_fail_route', 'login');
        $request->session()->forget('oauth_origin_portal');

        return $this->googleAuthService->redirectToGoogle();
    }

    public function callback(Request $request): Response
    {
        if (! $this->googleAuthService->isConfigured()) {
            return redirect()->route('login')->withErrors([
                'email' => __('Google sign-in is not available.'),
            ]);
        }

        return $this->googleAuthService->handleCallback($request);
    }
}
