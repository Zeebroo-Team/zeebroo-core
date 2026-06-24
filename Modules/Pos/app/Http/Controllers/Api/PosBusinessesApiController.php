<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessCategory;
use Modules\HRManagement\Models\Employee;

class PosBusinessesApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Businesses owned by this user + businesses where they are an employee
        $ownedIds = Business::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        $employeeBusinessIds = Employee::query()
            ->where('user_id', $user->id)
            ->whereNotNull('user_id')
            ->pluck('business_id');

        $allIds = $ownedIds->merge($employeeBusinessIds)->unique()->values();

        $businesses = Business::query()
            ->whereIn('id', $allIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $businesses->map(static fn (Business $business) => [
                'id' => (int) $business->id,
                'name' => $business->name,
            ])->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'category'   => ['required', 'string', 'max:120'],
            'features'   => ['nullable', 'array'],
            'features.*' => ['string'],
        ]);

        $categorySlug  = $validated['category'];
        $categoryLabel = BusinessCategory::labelForSlug($categorySlug) ?? $categorySlug;

        $business = Business::create([
            'user_id'               => $user->id,
            'name'                  => $validated['name'],
            'category'              => $categoryLabel,
            'company_category_slug' => $categorySlug,
        ]);

        $allKeys     = ['account_management', 'bill_management', 'human_resources', 'point_of_sale',
                        'product_management', 'service_management', 'social_media_campaign', 'stock_management'];
        $enabledKeys = array_fill_keys($validated['features'] ?? [], true);
        $features    = [];
        foreach ($allKeys as $k) {
            $features[$k] = isset($enabledKeys[$k]);
        }
        $features['account_management'] = true;
        if (! $features['stock_management'] || ! $features['product_management']) {
            $features['point_of_sale'] = false;
        }
        $business->setSetting('business.features', $features);

        return response()->json([
            'data' => ['id' => (int) $business->id, 'name' => $business->name],
        ], 201);
    }
}
