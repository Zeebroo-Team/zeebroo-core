<?php

namespace Modules\DesignStudio\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\DesignStudio\Models\SocialMediaConnection;
use Modules\DesignStudio\Services\FacebookConnectionService;
use Throwable;

class FacebookConnectionController extends Controller
{
    public function __construct(private FacebookConnectionService $facebook) {}

    /** Redirect user to Facebook OAuth consent screen. */
    public function redirect(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);

        abort_unless(
            filled(config('services.facebook.client_id')) && filled(config('services.facebook.client_secret')),
            503,
            'Facebook App ID / Secret are not configured. Add FACEBOOK_APP_ID and FACEBOOK_APP_SECRET to your .env file.'
        );

        $state = Str::random(40);
        $request->session()->put('fb_oauth_state', $state);
        $request->session()->put('fb_oauth_business_id', $business->id);

        return redirect()->away($this->facebook->authorizationUrl($state));
    }

    /** Handle OAuth callback — show page selection. */
    public function callback(Request $request): View|RedirectResponse
    {
        // Handle user denying permission
        if ($request->has('error')) {
            return redirect()->route('designstudio.social-media.index')
                ->with('error', 'Facebook connection was cancelled.');
        }

        // CSRF state check
        $sessionState = $request->session()->pull('fb_oauth_state');
        if (! hash_equals((string) $sessionState, (string) $request->input('state'))) {
            return redirect()->route('designstudio.social-media.index')
                ->with('error', 'Invalid OAuth state. Please try again.');
        }

        $businessId = $request->session()->pull('fb_oauth_business_id');
        $business   = Business::find($businessId);

        if (! $business || $business->user_id !== $request->user()?->id) {
            return redirect()->route('designstudio.social-media.index')
                ->with('error', 'Session expired. Please try connecting again.');
        }

        try {
            $tokenData = $this->facebook->exchangeCodeForLongLivedToken($request->input('code'));
            $pages     = $this->facebook->getUserPages($tokenData['access_token']);
        } catch (Throwable $e) {
            return redirect()->route('designstudio.social-media.index')
                ->with('error', 'Could not connect to Facebook: ' . $e->getMessage());
        }

        if (empty($pages)) {
            return redirect()->route('designstudio.social-media.index')
                ->with('error', 'No Facebook Pages found. Make sure you manage at least one Page.');
        }

        // Store long-lived user token temporarily in session for the page-selection step
        $request->session()->put('fb_user_token', $tokenData['access_token']);
        $request->session()->put('fb_pages', $pages);
        $request->session()->put('fb_business_id', $business->id);

        return view('designstudio::hub.facebook-pages', [
            'pages'    => $pages,
            'business' => $business,
        ]);
    }

    /** Store the selected page connection. */
    public function connectPage(Request $request): RedirectResponse
    {
        $request->validate(['page_id' => ['required', 'string']]);

        $userToken  = $request->session()->pull('fb_user_token');
        $pages      = $request->session()->pull('fb_pages', []);
        $businessId = $request->session()->pull('fb_business_id');

        $business = Business::find($businessId);
        abort_unless($business && $business->user_id === $request->user()?->id, 403);

        $pageId   = $request->input('page_id');
        $pageData = collect($pages)->firstWhere('id', $pageId);

        abort_unless($pageData !== null, 422, 'Selected page not found.');

        try {
            // Page access token derived from long-lived user token → never expires
            $pageToken = $this->facebook->getPageAccessToken($pageId, $userToken);
        } catch (Throwable $e) {
            return redirect()->route('designstudio.social-media.index')
                ->with('error', 'Could not retrieve page token: ' . $e->getMessage());
        }

        SocialMediaConnection::updateOrCreate(
            [
                'business_id' => $business->id,
                'platform'    => 'facebook',
                'external_id' => $pageId,
            ],
            [
                'name'             => $pageData['name'],
                'picture_url'      => $pageData['picture']['data']['url'] ?? null,
                'access_token'     => $pageToken,
                'token_expires_at' => null, // page tokens from long-lived user tokens never expire
                'metadata'         => [
                    'category'  => $pageData['category'] ?? null,
                    'fan_count' => $pageData['fan_count'] ?? null,
                ],
                'connected_by' => $request->user()->id,
            ]
        );

        return redirect()->route('designstudio.social-media.index')
            ->with('status', "Facebook Page \"{$pageData['name']}\" connected successfully.");
    }

    /** Remove a Facebook Page connection. */
    public function disconnect(Request $request, SocialMediaConnection $connection): RedirectResponse
    {
        $business = Business::query()->where('user_id', $request->user()?->id)->first();
        abort_unless($business && $connection->business_id === $business->id, 403);
        abort_unless($connection->platform === 'facebook', 403);

        $name = $connection->name;
        $connection->delete();

        return redirect()->route('designstudio.social-media.index')
            ->with('status', "Facebook Page \"{$name}\" disconnected.");
    }

    private function requireBusiness(Request $request): Business
    {
        $business = Business::query()->where('user_id', $request->user()?->id)->first();
        abort_unless($business !== null, 404, 'No business found.');
        return $business;
    }
}
