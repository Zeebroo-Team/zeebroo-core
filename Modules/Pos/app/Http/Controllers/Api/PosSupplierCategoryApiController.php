<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Purchase\Models\SupplierCategory;

class PosSupplierCategoryApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $categories = SupplierCategory::query()
            ->where('business_id', $business->id)
            ->withCount('suppliers')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn (SupplierCategory $c) => $this->format($c))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_suppliers');

        $validated = $request->validate([
            'name'        => [
                'required', 'string', 'max:120',
                Rule::unique('supplier_categories', 'name')->where('business_id', $business->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $category = SupplierCategory::create(array_merge($validated, ['business_id' => $business->id]));
        $category->loadCount('suppliers');

        return response()->json(['data' => $this->format($category)], 201);
    }

    public function update(Request $request, SupplierCategory $category): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $category->business_id !== (int) $business->id) abort(403);
        $this->abortUnlessPerm($request, $business, 'inv_suppliers');

        $validated = $request->validate([
            'name'        => [
                'required', 'string', 'max:120',
                Rule::unique('supplier_categories', 'name')->where('business_id', $business->id)->ignore($category->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $category->update($validated);
        $category->loadCount('suppliers');

        return response()->json(['data' => $this->format($category)]);
    }

    public function destroy(Request $request, SupplierCategory $category): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $category->business_id !== (int) $business->id) abort(403);
        $this->abortUnlessPerm($request, $business, 'inv_suppliers');

        if ($category->suppliers()->exists()) {
            return response()->json([
                'message' => 'This category is assigned to one or more suppliers. Reassign or clear those suppliers before deleting it.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    private function format(SupplierCategory $c): array
    {
        return [
            'id'              => $c->id,
            'name'            => $c->name,
            'description'     => $c->description,
            'suppliers_count' => $c->suppliers_count ?? 0,
        ];
    }
}
