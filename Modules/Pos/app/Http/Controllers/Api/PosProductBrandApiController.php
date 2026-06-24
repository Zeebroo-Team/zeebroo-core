<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Product\Models\ProductBrand;
use Modules\Product\Services\ProductBrandService;

class PosProductBrandApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly ProductBrandService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $q      = (string) $request->query('q', '');
        $status = (string) $request->query('status', '');

        $query = $business->productBrands()
            ->withCount('products')
            ->orderBy('name');

        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $brands = $query->get();

        return response()->json([
            'data' => $brands->map(fn ($b) => $this->format($b)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'website'     => 'nullable|url|max:500',
            'is_active'   => 'boolean',
        ]);

        $brand = $this->service->create($business, $data);
        $brand->loadCount('products');

        return response()->json([
            'message' => 'Brand created.',
            'data'    => $this->format($brand),
        ], 201);
    }

    public function update(Request $request, ProductBrand $brand): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->brandForBusiness($business, $brand)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'website'     => 'nullable|url|max:500',
            'is_active'   => 'boolean',
        ]);

        $brand = $this->service->update($brand, $data);
        $brand->loadCount('products');

        return response()->json([
            'message' => 'Brand updated.',
            'data'    => $this->format($brand),
        ]);
    }

    public function destroy(Request $request, ProductBrand $brand): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->brandForBusiness($business, $brand)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($brand->products()->exists()) {
            $count = $brand->products()->count();

            return response()->json([
                'message' => "Cannot delete a brand that has {$count} product(s) assigned.",
            ], 422);
        }

        $this->service->delete($brand);

        return response()->json(['message' => 'Brand deleted.']);
    }

    private function format(ProductBrand $b): array
    {
        return [
            'id'             => $b->id,
            'name'           => $b->name,
            'description'    => $b->description,
            'website'        => $b->website,
            'is_active'      => (bool) $b->is_active,
            'products_count' => (int) ($b->products_count ?? 0),
        ];
    }
}
