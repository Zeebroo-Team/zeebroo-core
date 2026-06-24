<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Account\Models\Property;
use Modules\Account\Services\PropertyService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosPropertyApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PropertyService $propertyService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $properties = Property::query()
            ->where('business_id', $business->id)
            ->latest()
            ->get();

        $today         = Carbon::today();
        $soonThreshold = Carbon::today()->addDays(60);
        $totalCost     = 0.0;
        $expiringCount = 0;

        $data = $properties->map(function (Property $p) use ($today, $soonThreshold, &$totalCost, &$expiringCount) {
            $totalCost += (float) $p->cost;
            $expiringSoon = $p->has_expiry && $p->expire_date !== null && $p->expire_date->lte($soonThreshold);
            if ($expiringSoon) {
                $expiringCount++;
            }

            return $this->format($p, $today);
        })->values();

        return response()->json([
            'data'           => $data,
            'total_count'    => $properties->count(),
            'expiring_count' => $expiringCount,
            'total_cost_fmt' => number_format($totalCost, 2, '.', ','),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $user     = $request->user();

        $request->merge([
            'has_expiry'  => $request->boolean('has_expiry'),
            'expire_date' => $request->filled('expire_date') ? $request->input('expire_date') : null,
        ]);

        $validated = $request->validate([
            'property_name'       => ['required', 'string', 'max:255'],
            'property_type'       => ['required', 'string', 'max:255'],
            'property_type_other' => ['nullable', 'string', 'max:255'],
            'cost'                => ['required', 'numeric', 'min:0'],
            'description'         => ['nullable', 'string', 'max:5000'],
            'has_expiry'          => ['nullable', 'boolean'],
            'expire_date'         => ['nullable', 'date'],
        ]);

        $typeOptions = array_keys(Property::typeOptions());
        if ($validated['property_type'] === 'other') {
            if (! $request->filled('property_type_other')) {
                return response()->json([
                    'message' => 'Provide the custom property type.',
                    'errors'  => ['property_type_other' => ['Required when type is Other.']],
                ], 422);
            }
            $validated['property_type'] = trim((string) ($validated['property_type_other'] ?? ''));
        } elseif (! in_array($validated['property_type'], $typeOptions, true)) {
            return response()->json(['message' => 'Invalid property type.'], 422);
        }

        $hasExpiry = (bool) ($validated['has_expiry'] ?? false);

        $property = $this->propertyService->create($user, $business, [
            'property_name' => $validated['property_name'],
            'property_type' => $validated['property_type'],
            'cost'          => $validated['cost'],
            'description'   => $validated['description'] ?? null,
            'has_expiry'    => $hasExpiry,
            'expire_date'   => $hasExpiry ? ($validated['expire_date'] ?? null) : null,
        ]);

        return response()->json(['message' => 'Property saved.', 'data' => $this->format($property, Carbon::today())], 201);
    }

    public function destroy(Request $request, Property $property): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $property->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Property not found.'], 404);
        }

        if (! $this->propertyService->deleteForUser($request->user(), $property)) {
            return response()->json(['message' => 'Unable to delete this property.'], 403);
        }

        return response()->json(['message' => 'Property deleted.']);
    }

    private function format(Property $property, Carbon $today): array
    {
        $expiringSoon = $property->has_expiry && $property->expire_date !== null
            && $property->expire_date->lte($today->copy()->addDays(60));
        $expired      = $property->has_expiry && $property->expire_date !== null
            && $property->expire_date->lt($today);

        return [
            'id'              => $property->id,
            'property_name'   => $property->property_name,
            'property_type'   => $property->property_type,
            'cost'            => (float) $property->cost,
            'cost_fmt'        => number_format((float) $property->cost, 2, '.', ','),
            'has_expiry'      => (bool) $property->has_expiry,
            'expire_date'     => $property->expire_date?->format('Y-m-d'),
            'expire_date_fmt' => $property->expire_date?->format('M j, Y'),
            'expiring_soon'   => $expiringSoon,
            'expired'         => $expired,
            'description'     => $property->description,
        ];
    }
}
