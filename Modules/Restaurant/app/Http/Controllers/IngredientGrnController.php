<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\IngredientGrn;
use Modules\Restaurant\Models\IngredientPurchaseOrder;
use Modules\Restaurant\Services\IngredientPurchaseService;

class IngredientGrnController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function __construct(private readonly IngredientPurchaseService $service) {}

    public function store(Request $request, IngredientPurchaseOrder $ingredientPurchaseOrder): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredientPurchaseOrder->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'received_date'  => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,cheque,credit'],
            'reference'      => ['nullable', 'string', 'max:120'],
            'notes'          => ['nullable', 'string', 'max:3000'],
            'items'          => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer'],
            'items.*.quantity_received'      => ['required', 'numeric', 'min:0'],
        ]);

        $lines = array_filter($data['items'], fn ($r) => (float) ($r['quantity_received'] ?? 0) > 0);

        if (empty($lines)) {
            return redirect()->route('restaurant.ingredients.purchases.show', $ingredientPurchaseOrder)
                ->withErrors(['items' => 'Enter at least one quantity to receive.']);
        }

        try {
            $grn = $this->service->createGrn($ingredientPurchaseOrder, $data, array_values($lines));
        } catch (ValidationException $e) {
            return redirect()->route('restaurant.ingredients.purchases.show', $ingredientPurchaseOrder)
                ->withErrors($e->errors());
        }

        return redirect()->route('restaurant.ingredients.grn.show', $grn)
            ->with('status', "Goods received ({$grn->grn_number}). Ingredient stock updated.");
    }

    public function show(Request $request, IngredientGrn $ingredientGrn): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $ingredientGrn->business_id === (int) $business->id, 404);

        $ingredientGrn->load(['purchaseOrder.supplier', 'items.ingredient', 'items.purchaseOrderItem']);

        return view('restaurant::ingredients.grn.show', [
            'business' => $business,
            'grn'      => $ingredientGrn,
            'currency' => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }
}
