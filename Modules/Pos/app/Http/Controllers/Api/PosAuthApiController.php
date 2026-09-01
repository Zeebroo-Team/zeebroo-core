<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Services\AuthService;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessCategory;

class PosAuthApiController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function token(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $deviceName = $validated['device_name'] ?? 'pos-api-client';
        $token = $user->createToken($deviceName);
        UserActivityLog::record($user, UserActivityLog::PLATFORM_DESKTOP, UserActivityLog::EVENT_LOGIN, $request, $deviceName);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'user' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'business_name'     => ['required', 'string', 'max:255'],
            'business_category' => ['required', 'string', 'max:120'],
            'features'          => ['nullable', 'array'],
            'features.*'        => ['string'],
            'email'             => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'          => ['required', 'confirmed', Password::min(8)],
            'device_name'       => ['nullable', 'string', 'max:120'],
        ]);

        $user = $this->authService->register([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        $categorySlug  = $validated['business_category'];
        $categoryLabel = BusinessCategory::labelForSlug($categorySlug) ?? $categorySlug;

        $business = Business::create([
            'user_id'               => $user->id,
            'name'                  => $validated['business_name'],
            'category'              => $categoryLabel,
            'company_category_slug' => $categorySlug,
        ]);

        // Save selected features
        $allKeys     = ['account_management','bill_management','human_resources','point_of_sale','product_management','project_management','service_management','social_media_campaign','stock_management'];
        $enabledKeys = array_fill_keys($validated['features'] ?? [], true);
        $features    = [];
        foreach ($allKeys as $k) { $features[$k] = isset($enabledKeys[$k]); }
        $features['account_management'] = true; // always on
        if (! $features['stock_management'] || ! $features['product_management']) {
            $features['point_of_sale'] = false;
        }
        $business->setSetting('business.features', $features);

        $deviceName = $validated['device_name'] ?? 'pos-api-client';
        $token = $user->createToken($deviceName);
        UserActivityLog::record($user, UserActivityLog::PLATFORM_DESKTOP, UserActivityLog::EVENT_REGISTER, $request, $deviceName);

        return response()->json([
            'token_type'   => 'Bearer',
            'access_token' => $token->plainTextToken,
            'user' => [
                'id'    => (int) $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function businessCategories(): JsonResponse
    {
        return response()->json([
            'data' => BusinessCategory::optionsForSelect(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id'    => (int) $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return response()->json([
            'data' => [
                'id'    => (int) $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($validated['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        return response()->json([
            'message' => 'Password updated.',
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Token revoked.',
        ]);
    }
}
