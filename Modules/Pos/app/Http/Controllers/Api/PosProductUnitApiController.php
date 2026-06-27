<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Product\Models\ProductUnit;
use Modules\Product\Services\ProductUnitService;

class PosProductUnitApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly ProductUnitService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $units = $this->service->listForBusiness($business)->loadCount('products');

        return response()->json([
            'data' => $units->map(fn (ProductUnit $u) => $this->format($u))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:80'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'is_active'    => ['boolean'],
        ]);

        $unit = $this->service->create($business, $data);

        return response()->json(['data' => $this->format($unit->loadCount('products'))], 201);
    }

    public function update(Request $request, ProductUnit $productUnit): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        abort_unless((int) $productUnit->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:80'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'is_active'    => ['boolean'],
        ]);

        $unit = $this->service->update($productUnit, $data);

        return response()->json(['data' => $this->format($unit->loadCount('products'))]);
    }

    public function destroy(Request $request, ProductUnit $productUnit): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        abort_unless((int) $productUnit->business_id === (int) $business->id, 404);

        if ($productUnit->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a unit that is assigned to products.',
            ], 422);
        }

        $this->service->delete($productUnit);

        return response()->json(['message' => 'Unit deleted.']);
    }

    private function format(ProductUnit $u): array
    {
        return [
            'id'             => $u->id,
            'name'           => $u->name,
            'abbreviation'   => $u->abbreviation,
            'display_label'  => $u->displayLabel(),
            'is_active'      => (bool) $u->is_active,
            'sort_order'     => $u->sort_order,
            'products_count' => $u->products_count ?? 0,
        ];
    }
}
