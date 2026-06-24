<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Services\ProductCategoryService;

class PosProductCategoryApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly ProductCategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $q      = (string) $request->query('q', '');
        $status = $request->query('status');

        $categories = $this->service->searchFlatForBusiness(
            $business,
            $q,
            is_string($status) ? $status : null,
            50,
        );

        return response()->json([
            'data' => collect($categories->items())->map(fn (ProductCategory $c) => $this->format($c))->values(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page'    => $categories->lastPage(),
                'total'        => $categories->total(),
            ],
        ]);
    }

    public function parentOptions(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $excludeId = $request->query('exclude');
        $exclude   = $excludeId ? ProductCategory::find((int) $excludeId) : null;

        $options = $this->service->parentOptionsForForm($business, $exclude);

        return response()->json([
            'data' => $options->map(fn ($opt) => [
                'id'    => $opt->id,
                'name'  => $opt->name,
                'label' => $opt->label,
                'depth' => $opt->depth,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id'   => ['nullable', 'integer'],
            'is_active'   => ['boolean'],
        ]);

        $category = $this->service->create($business, $data);

        return response()->json(['data' => $this->format($category->loadCount(['products', 'children']))], 201);
    }

    public function update(Request $request, ProductCategory $category): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        abort_unless((int) $category->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id'   => ['nullable', 'integer'],
            'is_active'   => ['boolean'],
        ]);

        $category = $this->service->update($category, $data);

        return response()->json(['data' => $this->format($category->loadCount(['products', 'children']))]);
    }

    public function destroy(Request $request, ProductCategory $category): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        abort_unless((int) $category->business_id === (int) $business->id, 404);

        try {
            $this->service->delete($category);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['message' => 'Category deleted.']);
    }

    private function format(ProductCategory $c): array
    {
        return [
            'id'             => $c->id,
            'name'           => $c->name,
            'description'    => $c->description,
            'parent_id'      => $c->parent_id,
            'parent_name'    => $c->parent?->name,
            'is_active'      => (bool) $c->is_active,
            'sort_order'     => $c->sort_order,
            'products_count' => $c->products_count ?? 0,
            'children_count' => $c->children_count ?? 0,
        ];
    }
}
