<?php

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;

class ProductService
{
    public function __construct(
        private readonly ProductImageService $productImageService,
        private readonly ProductBundleService $productBundleService,
    ) {
    }

    public function listForBusiness(?Business $business): Collection
    {
        if (!$business instanceof Business) {
            return new Collection();
        }

        return $business->products()
            ->with([
                'categories',
                'brands',
                'productUnit',
                'imageFile',
                'productImages.file',
                'bundleItems.itemProduct',
            ])
            ->orderBy('name')
            ->get();
    }

    public function create(Business $business, array $data): Product
    {
        $categoryIds = $data['product_category_ids'] ?? [];
        $brandIds = $data['product_brand_ids'] ?? [];
        $fileIds = $data['file_manager_file_ids'] ?? null;
        $isBundle = (bool) ($data['is_bundle'] ?? false);
        $bundleItems = $data['bundle_items'] ?? [];
        unset(
            $data['product_category_ids'],
            $data['product_brand_ids'],
            $data['file_manager_file_ids'],
            $data['bundle_items'],
        );
        $data['is_bundle'] = false;

        $product = $business->products()->create($data);
        $product->categories()->sync($categoryIds);
        $product->brands()->sync($brandIds);

        if (is_array($fileIds)) {
            $this->productImageService->syncProductImages($product, $fileIds);
        }

        $this->productBundleService->syncBundleItems($product, $isBundle, is_array($bundleItems) ? $bundleItems : []);

        return $product->load(['categories', 'brands', 'productUnit', 'imageFile', 'productImages.file', 'bundleItems.itemProduct']);
    }

    public function update(Product $product, array $data): Product
    {
        $categoryIds = $data['product_category_ids'] ?? null;
        $brandIds = $data['product_brand_ids'] ?? null;
        $fileIds = $data['file_manager_file_ids'] ?? null;
        $isBundle = array_key_exists('is_bundle', $data) ? (bool) $data['is_bundle'] : $product->is_bundle;
        $bundleItems = $data['bundle_items'] ?? null;
        unset(
            $data['product_category_ids'],
            $data['product_brand_ids'],
            $data['file_manager_file_ids'],
            $data['bundle_items'],
        );
        unset($data['is_bundle']);

        $product->fill($data);
        $product->save();

        if (is_array($categoryIds)) {
            $product->categories()->sync($categoryIds);
        }

        if (is_array($brandIds)) {
            $product->brands()->sync($brandIds);
        }

        if (is_array($fileIds)) {
            $this->productImageService->syncProductImages($product, $fileIds);
        }

        $this->productBundleService->syncBundleItems(
            $product,
            $isBundle,
            is_array($bundleItems) ? $bundleItems : [],
        );

        return $product->load(['categories', 'brands', 'productUnit', 'imageFile', 'productImages.file', 'bundleItems.itemProduct']);
    }

    public function delete(Product $product): bool
    {
        if ($product->is_bundle) {
            $product->bundleItems()->delete();
        }

        return (bool) $product->delete();
    }

    public function productForBusiness(Business $business, Product $product): ?Product
    {
        if ((int) $product->business_id !== (int) $business->id) {
            return null;
        }

        return $product;
    }

    public function loadForShow(Product $product): Product
    {
        return $product->load([
            'categories',
            'brands',
            'productUnit',
            'imageFile',
            'productImages.file',
            'bundleItems.itemProduct.productUnit',
            'bundleItems.itemProduct.imageFile',
            'sellingUnits',
        ]);
    }
}
