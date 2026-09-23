<?php

namespace Modules\Developers\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Developers\Models\ApiKey;
use Modules\Developers\Models\Webhook;

class DeveloperSettingsController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $keys = ApiKey::where('business_id', $business->id)
            ->orderByDesc('created_at')
            ->get();

        $webhooks = Webhook::where('business_id', $business->id)
            ->orderByDesc('created_at')
            ->get();

        return view('developers::index', [
            'business'        => $business,
            'keys'            => $keys,
            'webhooks'        => $webhooks,
            'availableEvents' => Webhook::availableEvents(),
            'newToken'        => $request->session()->get('developers.new_token'),
        ]);
    }

    public function storeKey(Request $request): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'expires_at'  => ['nullable', 'date', 'after:today'],
        ]);

        ['token' => $token, 'hash' => $hash, 'prefix' => $prefix] = ApiKey::generateToken();

        ApiKey::create([
            'business_id'  => $business->id,
            'name'         => $validated['name'],
            'token_hash'   => $hash,
            'token_prefix' => $prefix,
            'permissions'  => null,
            'expires_at'   => $validated['expires_at'] ?? null,
            'is_active'    => true,
        ]);

        return redirect()->route('developers.index')
            ->with('status', 'API key created — copy it now, you will not see it again.')
            ->with('developers.new_token', $token);
    }

    public function toggleKey(Request $request, int $key): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $apiKey = ApiKey::where('business_id', $business->id)->where('id', $key)->firstOrFail();
        $apiKey->update(['is_active' => ! $apiKey->is_active]);

        return response()->json(['data' => ['id' => $apiKey->id, 'is_active' => $apiKey->fresh()->is_active]]);
    }

    public function destroyKey(Request $request, int $key): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        ApiKey::where('business_id', $business->id)->where('id', $key)->delete();

        return redirect()->route('developers.index')->with('status', 'API key deleted.');
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'url'       => ['required', 'url', 'max:500'],
            'events'    => ['required', 'array', 'min:1'],
            'events.*'  => ['string', 'in:' . implode(',', array_keys(Webhook::availableEvents()))],
        ]);

        Webhook::create([
            'business_id'   => $business->id,
            'name'          => $validated['name'],
            'url'           => $validated['url'],
            'events'        => $validated['events'],
            'secret'        => Webhook::generateSecret(),
            'is_active'     => true,
            'failure_count' => 0,
        ]);

        return redirect()->route('developers.index')->with('status', 'Webhook created.');
    }

    public function toggleWebhook(Request $request, int $webhook): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $hook = Webhook::where('business_id', $business->id)->where('id', $webhook)->firstOrFail();
        $hook->update(['is_active' => ! $hook->is_active]);

        return response()->json(['data' => ['id' => $hook->id, 'is_active' => $hook->fresh()->is_active]]);
    }

    public function regenerateWebhookSecret(Request $request, int $webhook): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $hook = Webhook::where('business_id', $business->id)->where('id', $webhook)->firstOrFail();
        $hook->update(['secret' => Webhook::generateSecret(), 'failure_count' => 0]);

        return redirect()->route('developers.index')->with('status', 'Webhook secret regenerated.');
    }

    public function destroyWebhook(Request $request, int $webhook): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        Webhook::where('business_id', $business->id)->where('id', $webhook)->delete();

        return redirect()->route('developers.index')->with('status', 'Webhook deleted.');
    }

    private function resolveBusiness(Request $request): Business|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $business = Business::currentForNavbar($user);

        if ($business === null) {
            return redirect()->route('business.create');
        }

        if (! $business->hasFeature('developers')) {
            return redirect()->route('dashboard')->with('error', 'Developer Tools is not enabled for this business.');
        }

        return $business;
    }
}
