<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\Ingredient;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\StockTransaction;
use Modules\Restaurant\Services\IngredientStockService;

class IngredientController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function __construct(private readonly IngredientStockService $stockService) {}

    public function index(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $ingredients = Ingredient::where('business_id', $business->id)
            ->orderBy('name')
            ->get();

        return view('restaurant::ingredients.index', [
            'business'    => $business,
            'ingredients' => $ingredients,
            'units'       => Ingredient::units(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'unit'                => ['required', 'string', 'in:' . implode(',', array_keys(Ingredient::units()))],
            'quantity'            => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unit'       => ['nullable', 'numeric', 'min:0'],
        ]);

        Ingredient::create(array_merge($data, ['business_id' => $business->id]));

        return redirect()->route('restaurant.ingredients.index')->with('status', 'Ingredient added.');
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredient->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'unit'                => ['required', 'string', 'in:' . implode(',', array_keys(Ingredient::units()))],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unit'       => ['nullable', 'numeric', 'min:0'],
        ]);

        $ingredient->update($data);

        return redirect()->route('restaurant.ingredients.index')->with('status', 'Ingredient updated.');
    }

    public function destroy(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredient->business_id === (int) $business->id, 404);

        $ingredient->delete();

        return redirect()->route('restaurant.ingredients.index')->with('status', 'Ingredient deleted.');
    }

    public function stockIn(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredient->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $this->stockService->addStock($ingredient, (float) $data['quantity'], $data['notes'] ?? '');

        return redirect()->route('restaurant.ingredients.index')->with('status', "Stock added to {$ingredient->name}.");
    }

    public function waste(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredient->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $this->stockService->recordWaste($ingredient, (float) $data['quantity'], $data['notes'] ?? '');

        return redirect()->route('restaurant.ingredients.index')->with('status', "Waste recorded for {$ingredient->name}.");
    }

    public function transactions(Request $request, Ingredient $ingredient): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredient->business_id === (int) $business->id, 404);

        $transactions = StockTransaction::where('ingredient_id', $ingredient->id)
            ->latest()
            ->paginate(30);

        return view('restaurant::ingredients.transactions', [
            'business'     => $business,
            'ingredient'   => $ingredient,
            'transactions' => $transactions,
        ]);
    }

    public function saveRecipe(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $menuItem->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'recipe'                      => ['nullable', 'array'],
            'recipe.*.ingredient_id'      => ['required', 'integer', 'exists:restaurant_ingredients,id'],
            'recipe.*.quantity_required'  => ['required', 'numeric', 'gt:0'],
        ]);

        $sync = [];
        foreach ($data['recipe'] ?? [] as $row) {
            $sync[(int) $row['ingredient_id']] = ['quantity_required' => (float) $row['quantity_required']];
        }

        $menuItem->ingredients()->sync($sync);

        return redirect()->route('restaurant.menu.items.edit', $menuItem)->with('status', 'Recipe saved.');
    }
}
