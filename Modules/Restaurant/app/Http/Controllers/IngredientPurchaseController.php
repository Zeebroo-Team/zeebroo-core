<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Purchase\Models\Supplier;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\Ingredient;
use Modules\Restaurant\Models\IngredientPurchaseOrder;
use Modules\Restaurant\Services\IngredientPurchaseService;

class IngredientPurchaseController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function __construct(private readonly IngredientPurchaseService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $status = (string) $request->query('status', 'all');

        return view('restaurant::ingredients.purchases.index', [
            'business'    => $business,
            'orders'      => $this->service->listForBusiness($business, $status),
            'ingredients' => Ingredient::where('business_id', $business->id)->orderBy('name')->get(),
            'suppliers'   => Supplier::where('business_id', $business->id)->where('is_active', true)->orderBy('name')->get(),
            'currency'    => (string) (get_settings('business.currency', '', $business) ?: ''),
            'status'      => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $this->validateHeader($request, $business);

        try {
            $po = $this->service->create($business, $data, $request->input('items', []));
        } catch (ValidationException $e) {
            return redirect()->route('restaurant.ingredients.purchases.index')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('restaurant.ingredients.purchases.show', $po)
            ->with('status', "Purchase order {$po->po_number} created.");
    }

    public function show(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredientPurchaseOrder->business_id === (int) $business->id, 404);

        $ingredientPurchaseOrder->load(['supplier', 'items.ingredient', 'grns.items.ingredient']);

        return view('restaurant::ingredients.purchases.show', [
            'business'    => $business,
            'po'          => $ingredientPurchaseOrder,
            'ingredients' => Ingredient::where('business_id', $business->id)->orderBy('name')->get(),
            'suppliers'   => Supplier::where('business_id', $business->id)->where('is_active', true)->orderBy('name')->get(),
            'currency'    => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function update(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredientPurchaseOrder->business_id === (int) $business->id, 404);

        $data = $this->validateHeader($request, $business);

        try {
            $this->service->update($ingredientPurchaseOrder, $data, $request->input('items', []));
        } catch (ValidationException $e) {
            return redirect()->route('restaurant.ingredients.purchases.show', $ingredientPurchaseOrder)
                ->withErrors($e->errors());
        }

        return redirect()->route('restaurant.ingredients.purchases.show', $ingredientPurchaseOrder)
            ->with('status', 'Purchase order updated.');
    }

    public function placeOrder(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredientPurchaseOrder->business_id === (int) $business->id, 404);

        try {
            $this->service->markOrdered($ingredientPurchaseOrder);
        } catch (ValidationException $e) {
            return redirect()->route('restaurant.ingredients.purchases.show', $ingredientPurchaseOrder)
                ->withErrors($e->errors());
        }

        return redirect()->route('restaurant.ingredients.purchases.show', $ingredientPurchaseOrder)
            ->with('status', 'Purchase order placed with supplier.');
    }

    public function cancel(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredientPurchaseOrder->business_id === (int) $business->id, 404);

        try {
            $this->service->cancel($ingredientPurchaseOrder);
        } catch (ValidationException $e) {
            return redirect()->route('restaurant.ingredients.purchases.show', $ingredientPurchaseOrder)
                ->withErrors($e->errors());
        }

        return redirect()->route('restaurant.ingredients.purchases.show', $ingredientPurchaseOrder)
            ->with('status', 'Purchase order cancelled.');
    }

    public function destroy(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredientPurchaseOrder->business_id === (int) $business->id, 404);

        try {
            $this->service->delete($ingredientPurchaseOrder);
        } catch (ValidationException $e) {
            return redirect()->route('restaurant.ingredients.purchases.index')->withErrors($e->errors());
        }

        return redirect()->route('restaurant.ingredients.purchases.index')
            ->with('status', 'Purchase order deleted.');
    }

    private function validateHeader(Request $request, \Modules\Business\Models\Business $business): array
    {
        $supplierId = $request->input('supplier_id');
        $supplierId = ($supplierId === null || $supplierId === '' || $supplierId === '0') ? null : (int) $supplierId;

        $validated = $request->validate([
            'supplier_id'            => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'purchase_date'          => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'status'                 => ['required', 'string', Rule::in([IngredientPurchaseOrder::STATUS_DRAFT, IngredientPurchaseOrder::STATUS_ORDERED])],
            'notes'                  => ['nullable', 'string', 'max:3000'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.ingredient_id'  => ['required', 'integer', Rule::exists('restaurant_ingredients', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost'      => ['required', 'numeric', 'min:0'],
        ]);

        $validated['supplier_id'] = $supplierId;

        return $validated;
    }
}
