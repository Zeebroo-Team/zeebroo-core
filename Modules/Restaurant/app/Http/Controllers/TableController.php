<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\RestaurantTable;

class TableController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function index(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $tables = RestaurantTable::where('business_id', $business->id)
            ->orderBy('name')
            ->get();

        return view('restaurant::tables.index', [
            'business' => $business,
            'tables'   => $tables,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        RestaurantTable::create(['business_id' => $business->id] + $data + ['status' => 'available']);

        return redirect()->route('restaurant.tables.index')->with('status', 'Table added.');
    }

    public function update(Request $request, RestaurantTable $restaurantTable): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $restaurantTable->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'status'   => ['required', 'in:available,occupied,reserved,inactive'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $restaurantTable->update($data);

        return redirect()->route('restaurant.tables.index')->with('status', 'Table updated.');
    }

    public function savePositions(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) abort(403);

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

    public function statuses(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) abort(403);

        return response()->json(
            RestaurantTable::where('business_id', $business->id)
                ->pluck('status', 'id')
        );
    }

    public function destroy(Request $request, RestaurantTable $restaurantTable): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $restaurantTable->business_id === (int) $business->id, 404);

        $restaurantTable->delete();

        return redirect()->route('restaurant.tables.index')->with('status', 'Table removed.');
    }
}
