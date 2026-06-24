<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosProductQuickCreateService;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductService;

class PosProductApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PosProductQuickCreateService $quickCreate,
        private readonly ProductService $productService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        try {
            $quickResult = $this->quickCreate->create($business, $request->all());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not save product.',
                'errors'  => $e->errors(),
            ], 422);
        }

        // Sync extended fields when provided
        if (isset($quickResult['id'])) {
            $product = $business->products()->find($quickResult['id']);
            if ($product) {
                $fill = [];
                if ($request->filled('description'))  $fill['description'] = $request->input('description');
                if ($request->has('is_active'))        $fill['is_active']   = $request->boolean('is_active');
                if ($fill) $product->fill($fill)->save();

                if ($request->has('product_category_ids')) {
                    $ids = array_filter(array_map('intval', (array) $request->input('product_category_ids', [])), fn ($id) => $id > 0);
                    $product->categories()->sync($ids);
                }
                if ($request->has('product_brand_ids')) {
                    $ids = array_filter(array_map('intval', (array) $request->input('product_brand_ids', [])), fn ($id) => $id > 0);
                    $product->brands()->sync($ids);
                }
                if ($request->has('file_manager_file_ids')) {
                    $fileIds = array_filter(array_map('intval', (array) $request->input('file_manager_file_ids', [])), fn ($id) => $id > 0);
                    if ($fileIds) {
                        $this->productService->update($product, ['file_manager_file_ids' => $fileIds]);
                    }
                }
                if ($request->has('is_bundle')) {
                    $isBundle    = $request->boolean('is_bundle');
                    $bundleItems = (array) $request->input('bundle_items', []);
                    $this->productService->update($product, ['is_bundle' => $isBundle, 'bundle_items' => $bundleItems]);
                }
            }
        }

        return response()->json([
            'message' => 'Product added.',
            'data'    => $quickResult,
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->productService->productForBusiness($business, $product)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'name'                      => 'sometimes|required|string|max:255',
            'sku'                       => 'nullable|string|max:120',
            'description'               => 'nullable|string|max:5000',
            'unit_price'                => 'nullable|numeric|min:0',
            'stock_quantity'            => 'nullable|numeric|min:0',
            'product_unit_id'           => 'nullable|integer',
            'is_active'                 => 'boolean',
            'is_bundle'                 => 'boolean',
            'product_category_ids'      => 'nullable|array',
            'product_category_ids.*'    => 'integer',
            'product_brand_ids'         => 'nullable|array',
            'product_brand_ids.*'       => 'integer',
            'file_manager_file_ids'     => 'nullable|array',
            'file_manager_file_ids.*'   => 'integer',
            'bundle_items'              => 'nullable|array',
            'bundle_items.*.product_id' => 'required_with:bundle_items|integer',
            'bundle_items.*.quantity'   => 'required_with:bundle_items|numeric|min:0.001',
        ]);

        if ($request->has('is_active')) $data['is_active'] = $request->boolean('is_active');
        if ($request->has('is_bundle')) $data['is_bundle'] = $request->boolean('is_bundle');

        $this->productService->update($product, $data);

        return response()->json([
            'message' => 'Product updated.',
            'data'    => ['id' => $product->id, 'name' => $product->fresh()->name],
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->productService->productForBusiness($business, $product)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->productService->delete($product);

        return response()->json(['message' => 'Product deleted.']);
    }
}
