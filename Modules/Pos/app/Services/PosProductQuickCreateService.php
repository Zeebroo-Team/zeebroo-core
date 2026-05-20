<?php

namespace Modules\Pos\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductCatalogOptionsService;
use Modules\Product\Services\ProductService;
use Modules\Product\Services\ProductStockLayerService;

class PosProductQuickCreateService
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductCatalogOptionsService $catalogOptions,
        private readonly PosCatalogService $posCatalog,
        private readonly ProductStockLayerService $stockLayers,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(Business $business, array $input): array
    {
        $validated = $this->validate($business, $input);

        $openingStock = (float) ($validated['stock_quantity'] ?? 0);
        $unitPrice = (float) ($validated['unit_price'] ?? 0);

        $data = $this->catalogOptions->normalizeProductCatalogFields($business, [
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'unit_price' => $unitPrice,
            'stock_quantity' => 0,
            'product_unit_id' => $validated['product_unit_id'] ?? null,
            'product_category_ids' => [],
            'product_brand_ids' => [],
            'is_active' => true,
            'is_bundle' => false,
        ]);

        $product = $this->productService->create($business, $data);

        if ($openingStock > 0) {
            $this->stockLayers->createManualLayer(
                $business,
                $product,
                $openingStock,
                $unitPrice,
                $unitPrice,
            );
        }

        return $this->posCatalog->productCardForProduct($product->fresh([
            'productUnit',
            'imageFile',
            'categories',
        ]));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(Business $business, array $input): array
    {
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:120'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'product_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('product_units', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $validated['unit_price'] = isset($validated['unit_price']) ? (float) $validated['unit_price'] : null;
        $validated['stock_quantity'] = isset($validated['stock_quantity']) ? (float) $validated['stock_quantity'] : 0;

        if (filled($validated['sku'] ?? null)) {
            $sku = trim((string) $validated['sku']);
            $exists = $business->products()->where('sku', $sku)->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'sku' => 'This SKU is already used by another product.',
                ]);
            }
            $validated['sku'] = $sku;
        }

        return $validated;
    }
}
