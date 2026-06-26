<?php

declare(strict_types=1);

namespace Modules\DataRouter\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\DataRouter\Exceptions\VaultUnavailableException;
use Modules\DataRouter\Models\BusinessDataVaultConfig;
use Modules\DataRouter\Services\DataRouterSettingsService;
use Modules\DataRouter\Services\DataVaultClient;
use Modules\HRManagement\Models\Employee;

class DataRouterConfigApiController extends Controller
{
    public function __construct(
        private readonly DataRouterSettingsService $settings,
        private readonly DataVaultClient $client,
    ) {}

    /** GET /api/v1/data-router/config */
    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $config = $this->settings->getConfig($business);

        return response()->json($this->formatConfig($config));
    }

    /** PUT /api/v1/data-router/config */
    public function upsert(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'vault_url'         => ['required', 'url', 'max:512'],
            'shared_secret'     => ['nullable', 'string', 'min:32', 'max:512'],
            'is_enabled'        => ['required', 'boolean'],
            'enabled_modules'   => ['required', 'array'],
            'enabled_modules.*' => ['string', Rule::in([
                'sales',
                'payroll',
                'employees',
            ])],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $config = $this->settings->upsertConfig($business, $validated);

        return response()->json($this->formatConfig($config));
    }

    /** DELETE /api/v1/data-router/config */
    public function destroy(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $this->settings->deleteConfig($business);

        return response()->json(['message' => 'Data Vault configuration removed.']);
    }

    /** POST /api/v1/data-router/config/health */
    public function health(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $config = $this->settings->getConfig($business);

        if ($config === null) {
            return response()->json([
                'status'  => 'not_configured',
                'message' => 'No Data Vault configuration found for this business.',
            ], 422);
        }

        $start = microtime(true);

        try {
            $this->client->get(
                $config->vault_url,
                $config->shared_secret,
                '/api/v1/health',
                [],
                'health'
            );

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return response()->json([
                'status'     => 'ok',
                'latency_ms' => $latencyMs,
            ]);
        } catch (VaultUnavailableException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function businessOrAbort(Request $request): Business
    {
        $user = $request->user();
        if ($user === null) {
            throw new HttpResponseException(
                response()->json(['message' => 'Unauthenticated.'], 401)
            );
        }

        $rawId = $request->header('X-Business-Id')
            ?? $request->query('business_id')
            ?? session('selected_business_id');

        $business = null;
        if ($rawId !== null && $rawId !== '') {
            $employeeBusinessIds = Employee::query()
                ->where('user_id', $user->id)
                ->whereNotNull('user_id')
                ->pluck('business_id');

            $business = Business::query()
                ->where(function ($q) use ($user, $employeeBusinessIds) {
                    $q->where('user_id', $user->id)
                      ->orWhereIn('id', $employeeBusinessIds);
                })
                ->whereKey((int) $rawId)
                ->first();
        } else {
            $business = Business::currentForNavbar($user);
        }

        if ($business === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'No business selected. Send X-Business-Id header or business_id query parameter.',
                'errors'  => [
                    'business_id' => ['Select a business the authenticated user can access.'],
                ],
            ], 422));
        }

        return $business;
    }

    private function formatConfig(?BusinessDataVaultConfig $config): array
    {
        if ($config === null) {
            return ['configured' => false];
        }

        return [
            'configured'      => true,
            'vault_url'       => $config->vault_url,
            'is_enabled'      => $config->is_enabled,
            'enabled_modules' => $config->enabled_modules ?? [],
            'label'           => $config->label,
            'has_secret'      => filled($config->shared_secret),
            'updated_at'      => $config->updated_at?->toIso8601String(),
        ];
    }
}
