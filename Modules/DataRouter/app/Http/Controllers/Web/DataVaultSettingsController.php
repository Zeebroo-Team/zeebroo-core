<?php

declare(strict_types=1);

namespace Modules\DataRouter\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\DataRouter\Exceptions\VaultUnavailableException;
use Modules\DataRouter\Services\DataRouterSettingsService;
use Modules\DataRouter\Services\DataVaultClient;

class DataVaultSettingsController extends Controller
{
    public function __construct(
        private readonly DataRouterSettingsService $settings,
        private readonly DataVaultClient $client,
    ) {}

    public function index(Request $request): View
    {
        $business = $this->resolveBusiness($request);

        $config = $business ? $this->settings->getConfig($business) : null;

        return view('datarouter::index', [
            'business' => $business,
            'config'   => $config,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $business = $this->resolveBusiness($request);

        if (! $business) {
            return back()->withErrors(['business' => 'No business selected.']);
        }

        $validated = $request->validate([
            'vault_url'         => ['required', 'url', 'max:512'],
            'shared_secret'     => ['nullable', 'string', 'max:512'],
            'is_enabled'        => ['sometimes', 'boolean'],
            'enabled_modules'   => ['nullable', 'array'],
            'enabled_modules.*' => ['string', Rule::in(['sales', 'payroll', 'employees'])],
            'label'             => ['nullable', 'string', 'max:255'],
        ]);

        $validated['is_enabled']      = (bool) $request->input('is_enabled', false);
        $validated['enabled_modules'] = $validated['enabled_modules'] ?? [];

        $this->settings->upsertConfig($business, $validated);

        return back()->with('status', 'Data Vault configuration saved.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $business = $this->resolveBusiness($request);

        if ($business) {
            $this->settings->deleteConfig($business);
        }

        return back()->with('status', 'Data Vault disconnected.');
    }

    public function testConnection(Request $request): \Illuminate\Http\JsonResponse
    {
        $business = $this->resolveBusiness($request);

        if (! $business) {
            return response()->json(['status' => 'error', 'message' => 'No business selected.'], 422);
        }

        $config = $this->settings->getConfig($business);

        if (! $config) {
            return response()->json(['status' => 'not_configured', 'message' => 'No vault configuration found. Save your settings first.'], 422);
        }

        $start = microtime(true);

        try {
            $this->client->get($config->vault_url, $config->shared_secret, '/api/v1/health', [], 'health');
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return response()->json(['status' => 'ok', 'latency_ms' => $latencyMs]);
        } catch (VaultUnavailableException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function resolveBusiness(Request $request): ?Business
    {
        return Business::currentForNavbar($request->user());
    }
}
