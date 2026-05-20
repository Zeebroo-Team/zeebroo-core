<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Product\Http\Controllers\Concerns\ResolvesProductBusiness;
use Modules\Product\Models\ProductBrand;
use Modules\Product\Services\ProductBrandService;

class ProductBrandController extends Controller
{
    use ResolvesProductBusiness;

    public function __construct(private readonly ProductBrandService $brandService)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('product::brands.index', [
            'business' => $business,
            'brands' => $business->productBrands()->withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validatedBrand($request, $business);
        $this->brandService->create($business, $data);

        return redirect()->route('product.brands.index')->with('status', 'Brand added.');
    }

    public function edit(Request $request, ProductBrand $brand): View|RedirectResponse
    {
        $business = $this->requireBrand($request, $brand);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('product::brands.edit', [
            'business' => $business,
            'brand' => $brand,
        ]);
    }

    public function update(Request $request, ProductBrand $brand): RedirectResponse
    {
        $business = $this->requireBrand($request, $brand);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validatedBrand($request, $business, $brand);
        $this->brandService->update($brand, $data);

        return redirect()->route('product.brands.index')->with('status', 'Brand updated.');
    }

    public function destroy(Request $request, ProductBrand $brand): RedirectResponse
    {
        $business = $this->requireBrand($request, $brand);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if ($brand->products()->exists()) {
            return redirect()->route('product.brands.index')->withErrors([
                'brand' => 'Cannot delete a brand that still has products assigned.',
            ]);
        }

        $this->brandService->delete($brand);

        return redirect()->route('product.brands.index')->with('status', 'Brand removed.');
    }

    private function requireBrand(Request $request, ProductBrand $brand): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->brandService->brandForBusiness($business, $brand) instanceof ProductBrand, 404);

        return $business;
    }

    /**
     * @return array{name: string, description: ?string, website: ?string, is_active: bool}
     */
    private function validatedBrand(Request $request, Business $business, ?ProductBrand $ignore = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('product_brands', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id))
                    ->ignore($ignore?->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'url', 'max:500'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
