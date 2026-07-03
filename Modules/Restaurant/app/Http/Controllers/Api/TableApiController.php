<?php

namespace Modules\Restaurant\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\RestaurantTable;

class TableApiController extends Controller
{
    use ResolvesRestaurantBusiness;

    private function resolveBusiness(Request $request): Business|JsonResponse
    {
        $b = $this->requireBusiness($request);
        return $b instanceof Business ? $b : response()->json(['error' => 'Business not found'], 404);
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;

        $tables = RestaurantTable::where('business_id', $business->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => $this->format($t));

        return response()->json(['data' => $tables->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $table = RestaurantTable::create(array_merge($data, [
            'business_id' => $business->id,
            'status'      => 'available',
        ]));

        return response()->json(['data' => $this->format($table)], 201);
    }

    public function update(Request $request, RestaurantTable $restaurantTable): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $restaurantTable->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'status'   => ['required', 'in:available,occupied,reserved,inactive'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $restaurantTable->update($data);

        return response()->json(['data' => $this->format($restaurantTable->fresh())]);
    }

    public function destroy(Request $request, RestaurantTable $restaurantTable): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $restaurantTable->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $restaurantTable->delete();

        return response()->json(['success' => true]);
    }

    public function savePositions(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;

        $positions = $request->validate([
            'positions'       => ['required', 'array'],
            'positions.*.id'  => ['required', 'integer'],
            'positions.*.x'   => ['required', 'integer', 'min:0', 'max:9000'],
            'positions.*.y'   => ['required', 'integer', 'min:0', 'max:9000'],
        ])['positions'];

        foreach ($positions as $pos) {
            RestaurantTable::where('id', $pos['id'])
                ->where('business_id', $business->id)
                ->update(['pos_x' => $pos['x'], 'pos_y' => $pos['y']]);
        }

        return response()->json(['success' => true]);
    }

    private function format(RestaurantTable $t): array
    {
        return [
            'id'       => (int) $t->id,
            'name'     => $t->name,
            'capacity' => (int) $t->capacity,
            'status'   => $t->status,
            'notes'    => $t->notes,
            'pos_x'    => $t->pos_x,
            'pos_y'    => $t->pos_y,
        ];
    }
}
