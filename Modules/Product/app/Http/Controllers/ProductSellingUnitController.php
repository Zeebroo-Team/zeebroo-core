<?php
namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Product\Http\Controllers\Concerns\ResolvesProductBusiness;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductSellingUnit;

class ProductSellingUnitController extends Controller
{
    use ResolvesProductBusiness;

    public function store(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $product->business_id === (int) $business->id, 403);

        $data = $request->validate([
            'label'             => ['required', 'string', 'max:80'],
            'conversion_factor' => ['required', 'numeric', 'min:0.000001'],
            'selling_price'     => ['nullable', 'numeric', 'min:0'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
        ]);

        $unit = ProductSellingUnit::query()->create(array_merge($data, [
            'product_id'  => $product->id,
            'business_id' => $business->id,
            'is_active'   => true,
            'sort_order'  => $data['sort_order'] ?? 0,
            'selling_price' => isset($data['selling_price']) && $data['selling_price'] !== '' ? $data['selling_price'] : null,
        ]));

        if ($request->expectsJson()) {
            return response()->json(['id' => $unit->id, 'label' => $unit->label, 'conversion_factor' => (float) $unit->conversion_factor, 'selling_price' => $unit->selling_price ? (float) $unit->selling_price : null], 201);
        }
        return back()->with('status', 'Selling unit added.');
    }

    public function update(Request $request, Product $product, ProductSellingUnit $sellingUnit): JsonResponse|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $product->business_id === (int) $business->id, 403);
        abort_unless((int) $sellingUnit->product_id === (int) $product->id, 404);

        $data = $request->validate([
            'label'             => ['required', 'string', 'max:80'],
            'conversion_factor' => ['required', 'numeric', 'min:0.000001'],
            'selling_price'     => ['nullable', 'numeric', 'min:0'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
        ]);

        $sellingUnit->update(array_merge($data, [
            'selling_price' => isset($data['selling_price']) && $data['selling_price'] !== '' ? $data['selling_price'] : null,
            'sort_order' => $data['sort_order'] ?? $sellingUnit->sort_order,
        ]));

        if ($request->expectsJson()) {
            return response()->json(['id' => $sellingUnit->id, 'label' => $sellingUnit->label]);
        }
        return back()->with('status', 'Selling unit updated.');
    }

    public function destroy(Request $request, Product $product, ProductSellingUnit $sellingUnit): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $product->business_id === (int) $business->id, 403);
        abort_unless((int) $sellingUnit->product_id === (int) $product->id, 404);

        $sellingUnit->delete();
        return back()->with('status', 'Selling unit removed.');
    }
}
