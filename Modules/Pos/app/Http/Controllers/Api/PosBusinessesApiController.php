<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessCategory;
use Modules\Business\Models\BusinessMember;
use Modules\HRManagement\Models\Employee;
use Modules\Package\Models\Package;

class PosBusinessesApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Businesses owned by this user
        $ownedIds = Business::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        // Businesses where they are an HR employee
        $employeeBusinessIds = Employee::query()
            ->where('user_id', $user->id)
            ->whereNotNull('user_id')
            ->pluck('business_id');

        // Businesses where they are a business member (POS team)
        $memberBusinessIds = BusinessMember::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('business_id');

        $allIds = $ownedIds
            ->merge($employeeBusinessIds)
            ->merge($memberBusinessIds)
            ->unique()
            ->values();

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
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'features'   => ['nullable', 'array'],
            'features.*' => ['string'],
        ]);

        $package = null;
        if (isset($validated['package_id'])) {
            $package = Package::query()->where('is_active', true)->find($validated['package_id']);
            if (! $package) {
                throw ValidationException::withMessages([
                    'package_id' => ['The selected package is no longer available.'],
                ]);
            }
        }

        $categorySlug  = $validated['category'];
        $categoryLabel = BusinessCategory::labelForSlug($categorySlug) ?? $categorySlug;

        $business = Business::create([
            'user_id'               => $user->id,
            'name'                  => $validated['name'],
            'category'              => $categoryLabel,
            'company_category_slug' => $categorySlug,
            'package_id'            => $package?->id,
        ]);

        // Save selected features. When a package is selected, its feature list is
        // authoritative — the client only shows those toggles as a locked preview,
        // so the submitted `features` array is ignored to prevent tampering.
        $allKeys     = array_keys(config('features.list', []));
        $sourceKeys  = $package ? ($package->features ?? []) : ($validated['features'] ?? []);
        $enabledKeys = array_fill_keys($sourceKeys, true);
        $features    = [];
        foreach ($allKeys as $k) {
            $features[$k] = isset($enabledKeys[$k]);
        }
        $features['account_management'] = true;
        $business->setSetting('business.features', $features);

        return response()->json([
            'data' => ['id' => (int) $business->id, 'name' => $business->name],
        ], 201);
    }
}
