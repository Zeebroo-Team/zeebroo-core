<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Product\Http\Controllers\Concerns\ResolvesProductBusiness;
use Modules\Product\Models\ProductUnit;
use Modules\Product\Services\ProductUnitService;

class ProductUnitController extends Controller
{
    use ResolvesProductBusiness;

    public function __construct(private readonly ProductUnitService $unitService)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('product::units.index', [
            'business' => $business,
            'units' => $business->productUnits()->withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validatedUnit($request, $business);
        $this->unitService->create($business, $data);

        return redirect()->route('product.units.index')->with('status', 'Unit added.');
    }

    public function edit(Request $request, ProductUnit $unit): View|RedirectResponse
    {
        $business = $this->requireUnit($request, $unit);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('product::units.edit', [
            'business' => $business,
            'unit' => $unit,
        ]);
    }

    public function update(Request $request, ProductUnit $unit): RedirectResponse
    {
        $business = $this->requireUnit($request, $unit);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validatedUnit($request, $business, $unit);
        $this->unitService->update($unit, $data);

        return redirect()->route('product.units.index')->with('status', 'Unit updated.');
    }

    public function destroy(Request $request, ProductUnit $unit): RedirectResponse
    {
        $business = $this->requireUnit($request, $unit);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if ($unit->products()->exists()) {
            return redirect()->route('product.units.index')->withErrors([
                'unit' => 'Cannot delete a unit that is still assigned to products.',
            ]);
        }

        $this->unitService->delete($unit);

        return redirect()->route('product.units.index')->with('status', 'Unit removed.');
    }

    private function requireUnit(Request $request, ProductUnit $unit): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->unitService->unitForBusiness($business, $unit) instanceof ProductUnit, 404);

        return $business;
    }

    /**
     * @return array{name: string, abbreviation: ?string, sort_order: int, is_active: bool}
     */
    private function validatedUnit(Request $request, Business $business, ?ProductUnit $ignore = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('product_units', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id))
                    ->ignore($ignore?->id),
            ],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
