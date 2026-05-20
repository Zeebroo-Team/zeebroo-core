<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosSettingsService;

class PosSettingsApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PosSettingsService $posSettings,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        return response()->json([
            'data' => $this->posSettings->forBusiness($business),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'default_deposit_account_id' => ['nullable', 'integer', 'min:1'],
            'discount_field_enabled' => ['nullable', 'boolean'],
            'display_theme' => ['nullable', 'string', 'in:light,dark'],
        ]);

        $this->posSettings->saveForBusiness($business, $validated);

        return response()->json([
            'message' => 'POS settings saved.',
            'data' => $this->posSettings->forBusiness($business),
        ]);
    }
}
