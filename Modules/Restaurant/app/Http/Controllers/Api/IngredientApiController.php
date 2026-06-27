<?php

namespace Modules\Restaurant\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\Ingredient;
use Modules\Restaurant\Models\StockTransaction;
use Modules\Restaurant\Services\IngredientStockService;

class IngredientApiController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function __construct(private readonly IngredientStockService $stockService) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if (! ($business instanceof \Modules\Business\Models\Business)) {
            return response()->json(['error' => 'Business not found'], 404);
        }

        $ingredients = Ingredient::where('business_id', $business->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($i) => [
                'id'                  => $i->id,
                'name'                => $i->name,
                'unit'                => $i->unit,
                'quantity'            => (float) $i->quantity,
                'low_stock_threshold' => $i->low_stock_threshold ? (float) $i->low_stock_threshold : null,
                'cost_per_unit'       => (float) $i->cost_per_unit,
                'is_low_stock'        => $i->isLowStock(),
            ]);

        return response()->json(['data' => $ingredients]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if (! ($business instanceof \Modules\Business\Models\Business)) {
            return response()->json(['error' => 'Business not found'], 404);
        }

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'unit'                => ['required', 'string', 'in:' . implode(',', array_keys(Ingredient::units()))],
            'quantity'            => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unit'       => ['nullable', 'numeric', 'min:0'],
        ]);

        $ingredient = Ingredient::create(array_merge($data, ['business_id' => $business->id]));

        return response()->json(['data' => $ingredient], 201);
    }

    public function update(Request $request, Ingredient $ingredient): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if (! ($business instanceof \Modules\Business\Models\Business)) {
            return response()->json(['error' => 'Business not found'], 404);
        }
        if ((int) $ingredient->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'unit'                => ['required', 'string', 'in:' . implode(',', array_keys(Ingredient::units()))],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unit'       => ['nullable', 'numeric', 'min:0'],
        ]);

        $ingredient->update($data);

        return response()->json(['data' => $ingredient]);
    }

    public function destroy(Request $request, Ingredient $ingredient): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if (! ($business instanceof \Modules\Business\Models\Business)) {
            return response()->json(['error' => 'Business not found'], 404);
        }
        if ((int) $ingredient->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $ingredient->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function stockIn(Request $request, Ingredient $ingredient): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if (! ($business instanceof \Modules\Business\Models\Business)) {
            return response()->json(['error' => 'Business not found'], 404);
        }
        if ((int) $ingredient->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $this->stockService->addStock($ingredient, (float) $data['quantity'], $data['notes'] ?? '');
        $ingredient->refresh();

        return response()->json(['data' => ['quantity' => (float) $ingredient->quantity]]);
    }

    public function transactions(Request $request, Ingredient $ingredient): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if (! ($business instanceof \Modules\Business\Models\Business)) {
            return response()->json(['error' => 'Business not found'], 404);
        }
        if ((int) $ingredient->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $transactions = StockTransaction::where('ingredient_id', $ingredient->id)
            ->latest()
            ->paginate(30);

        return response()->json($transactions);
    }
}
