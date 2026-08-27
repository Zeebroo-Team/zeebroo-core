<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\CustomerCategory;

class PosCustomerCategoryApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $categories = CustomerCategory::query()
            ->where('business_id', $business->id)
            ->withCount('customers')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn (CustomerCategory $c) => $this->format($c))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'pos_customers');

        $validated = $request->validate([
            'name'        => [
                'required', 'string', 'max:120',
                Rule::unique('pos_customer_categories', 'name')->where('business_id', $business->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $category = CustomerCategory::create(array_merge($validated, ['business_id' => $business->id]));
        $category->loadCount('customers');

        return response()->json(['data' => $this->format($category)], 201);
    }

    public function update(Request $request, CustomerCategory $category): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $category->business_id !== (int) $business->id) abort(403);
        $this->abortUnlessPerm($request, $business, 'pos_customers');

        $validated = $request->validate([
            'name'        => [
                'required', 'string', 'max:120',
                Rule::unique('pos_customer_categories', 'name')->where('business_id', $business->id)->ignore($category->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $category->update($validated);
        $category->loadCount('customers');

        return response()->json(['data' => $this->format($category)]);
    }

    public function destroy(Request $request, CustomerCategory $category): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $category->business_id !== (int) $business->id) abort(403);
        $this->abortUnlessPerm($request, $business, 'pos_customers');

        if ($category->customers()->exists()) {
            return response()->json([
                'message' => 'This category is assigned to one or more customers. Reassign or clear those customers before deleting it.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    private function format(CustomerCategory $c): array
    {
        return [
            'id'             => $c->id,
            'name'           => $c->name,
            'description'    => $c->description,
            'customers_count' => $c->customers_count ?? 0,
        ];
    }
}
