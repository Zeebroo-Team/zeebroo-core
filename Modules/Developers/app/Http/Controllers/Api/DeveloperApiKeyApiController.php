<?php

namespace Modules\Developers\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Developers\Models\ApiKey;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class DeveloperApiKeyApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $keys = ApiKey::where('business_id', $business->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($k) => $this->formatKey($k));

        return response()->json(['data' => $keys]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'permissions' => 'nullable|array',
            'expires_at'  => 'nullable|date|after:today',
        ]);

        ['token' => $token, 'hash' => $hash, 'prefix' => $prefix] = ApiKey::generateToken();

        $key = ApiKey::create([
            'business_id' => $business->id,
            'name'        => $validated['name'],
            'token_hash'  => $hash,
            'token_prefix'=> $prefix,
            'permissions' => $validated['permissions'] ?? null,
            'expires_at'  => $validated['expires_at'] ?? null,
            'is_active'   => true,
        ]);

        return response()->json([
            'data'  => $this->formatKey($key),
            'token' => $token, // returned only once
        ], 201);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $key = ApiKey::where('business_id', $business->id)->where('id', $id)->firstOrFail();
        $key->update(['is_active' => !$key->is_active]);

        return response()->json(['data' => $this->formatKey($key->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        ApiKey::where('business_id', $business->id)->where('id', $id)->delete();

        return response()->json(['message' => 'API key deleted.']);
    }

    private function formatKey(ApiKey $k): array
    {
        return [
            'id'           => $k->id,
            'name'         => $k->name,
            'token_prefix' => $k->token_prefix,
            'permissions'  => $k->permissions,
            'is_active'    => $k->is_active,
            'expires_at'   => $k->expires_at?->toDateString(),
            'last_used_at' => $k->last_used_at?->toDateTimeString(),
            'created_at'   => $k->created_at?->toDateTimeString(),
        ];
    }
}
