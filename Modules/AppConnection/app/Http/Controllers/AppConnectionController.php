<?php

namespace Modules\AppConnection\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AppConnection\Models\UserAppConnection;
use Modules\AppConnection\Services\GoogleAppConnectionService;

class AppConnectionController extends Controller
{
    public function __construct(
        private readonly GoogleAppConnectionService $googleAppConnectionService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $googleConnection = $user->appConnections()
            ->where('provider', UserAppConnection::PROVIDER_GOOGLE)
            ->first();

        $googleOAuthConfigured = $this->googleOAuthIsConfigured();

        $integrations = [
            [
                'key' => 'facebook',
                'label' => __('Facebook'),
                'description' => __('Connect Meta Business assets, catalog sync, or lead capture from Facebook pages.'),
                'icon_class' => 'fa-brands fa-facebook-f',
                'accent' => '#1877f2',
            ],
            [
                'key' => 'google',
                'label' => __('Google account'),
                'description' => __('Sign in with Google, Calendar, Drive, or Ads reporting through a single workspace link.'),
                'icon_class' => 'fa-brands fa-google',
                'accent' => '#4285f4',
            ],
            [
                'key' => 'woocommerce',
                'label' => __('WooCommerce'),
                'description' => __('Sync orders, customers, and product catalog from your WordPress WooCommerce store.'),
                'icon_class' => 'fa-brands fa-wordpress',
                'accent' => '#96588a',
            ],
            [
                'key' => 'tiktok',
                'label' => __('TikTok'),
                'description' => __('Link TikTok For Business or Shop for ads performance and commerce signals.'),
                'icon_class' => 'fa-brands fa-tiktok',
                'accent' => '#00f2ea',
            ],
            [
                'key' => 'linkedin',
                'label' => __('LinkedIn'),
                'description' => __('Connect LinkedIn pages or campaigns for B2B leads and sponsored updates.'),
                'icon_class' => 'fa-brands fa-linkedin-in',
                'accent' => '#0a66c2',
            ],
            [
                'key' => 'instagram',
                'label' => __('Instagram'),
                'description' => __('Attach Instagram Business for messaging, shops, and insights alongside Meta.'),
                'icon_class' => 'fa-brands fa-instagram',
                'accent' => '#e4405f',
            ],
            [
                'key' => 'x',
                'label' => __('X (Twitter)'),
                'description' => __('Optional connection for brand mentions and promoted posts analytics.'),
                'icon_class' => 'fa-brands fa-x-twitter',
                'accent' => '#000000',
            ],
            [
                'key' => 'shopify',
                'label' => __('Shopify'),
                'description' => __('Future option for storefront orders and inventory alongside SociBiz finance.'),
                'icon_class' => 'fa-brands fa-shopify',
                'accent' => '#95bf47',
            ],
        ];

        return view('appconnection::index', [
            'integrations' => $integrations,
            'googleConnection' => $googleConnection,
            'googleOAuthConfigured' => $googleOAuthConfigured,
        ]);
    }

    public function redirectGoogle(Request $request): RedirectResponse
    {
        if (! $this->googleOAuthIsConfigured()) {
            return redirect()->route('app-connection.index')
                ->withErrors(['google' => __('Google OAuth is not configured. Add GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI to your environment.')]);
        }

        return $this->googleAppConnectionService->redirectToGoogle();
    }

    public function callbackGoogle(Request $request): RedirectResponse
    {
        if (! $this->googleOAuthIsConfigured()) {
            return redirect()->route('app-connection.index')
                ->withErrors(['google' => __('Google OAuth is not configured.')]);
        }

        try {
            return $this->googleAppConnectionService->handleCallback($request->user());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('app-connection.index')
                ->withErrors(['google' => __('Could not complete Google sign-in. Try again or check your OAuth client settings.')]);
        }
    }

    public function disconnectGoogle(Request $request): RedirectResponse
    {
        return $this->googleAppConnectionService->disconnect($request->user());
    }

    private function googleOAuthIsConfigured(): bool
    {
        $g = config('services.google', []);

        return filled($g['client_id'] ?? null)
            && filled($g['client_secret'] ?? null)
            && filled($g['redirect'] ?? null);
    }
}
