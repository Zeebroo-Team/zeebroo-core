<?php

namespace Modules\Product\Services;

use Modules\Business\Models\Business;

class ProductCatalogOptionsService
{
    public function __construct(
        private readonly ProductCategoryService $categoryService,
        private readonly ProductBrandService $brandService,
        private readonly ProductUnitService $unitService,
    ) {
    }

    /**
     * @return array{
     *     categories: \Illuminate\Database\Eloquent\Collection,
     *     brands: \Illuminate\Database\Eloquent\Collection,
     *     units: \Illuminate\Database\Eloquent\Collection,
     * }
     */
    public function optionsForBusiness(?Business $business): array
    {
        return [
            'categories' => $this->categoryService->listForBusiness($business)->where('is_active', true)->values(),
            'brands' => $this->brandService->listForBusiness($business)->where('is_active', true)->values(),
            'units' => $this->unitService->listForBusiness($business)->where('is_active', true)->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeProductCatalogFields(Business $business, array $data): array
    {
        $newNames = array_values(array_filter(array_merge(
            (array) ($data['new_category_names'] ?? []),
            filled($data['new_category_name'] ?? null) ? [(string) $data['new_category_name']] : [],
        )));

        $data['product_category_ids'] = $this->resolveCategoryIds(
            $business,
            $data['product_category_ids'] ?? [],
            $newNames,
        );

        unset($data['new_category_name'], $data['new_category_names']);

        $newBrandNames = array_values(array_filter(array_merge(
            (array) ($data['new_brand_names'] ?? []),
            filled($data['new_brand_name'] ?? null) ? [(string) $data['new_brand_name']] : [],
        )));

        $data['product_brand_ids'] = $this->resolveBrandIds(
            $business,
            $data['product_brand_ids'] ?? [],
            $newBrandNames,
        );

        unset($data['new_brand_name'], $data['new_brand_names']);

        if (!empty($data['product_unit_id'])) {
            $unit = $business->productUnits()->whereKey((int) $data['product_unit_id'])->first();
            if ($unit) {
                $data['unit'] = $unit->abbreviation ?: $unit->name;
            }
        }

        return $data;
    }

    /**
     * @param  array<int|string>|mixed  $selectedIds
     * @param  array<int, string>|string|null  $newCategoryNames
     * @return list<int>
     */
    public function resolveCategoryIds(Business $business, mixed $selectedIds, mixed $newCategoryNames): array
    {
        $ids = collect(is_array($selectedIds) ? $selectedIds : [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique();

        $names = collect(is_array($newCategoryNames) ? $newCategoryNames : [])
            ->when(is_string($newCategoryNames) && trim($newCategoryNames) !== '', fn ($c) => $c->push($newCategoryNames));

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $category = $business->productCategories()->firstOrCreate(
                ['name' => $name, 'parent_id' => null],
                [
                    'is_active' => true,
                    'sort_order' => ((int) $business->productCategories()->whereNull('parent_id')->max('sort_order')) + 1,
                ],
            );
            $ids->push((int) $category->id);
        }

        if ($ids->isEmpty()) {
            return [];
        }

        return $business->productCategories()
            ->whereIn('id', $ids->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string>|mixed  $selectedIds
     * @param  array<int, string>|string|null  $newBrandNames
     * @return list<int>
     */
    public function resolveBrandIds(Business $business, mixed $selectedIds, mixed $newBrandNames): array
    {
        $ids = collect(is_array($selectedIds) ? $selectedIds : [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique();

        $names = collect(is_array($newBrandNames) ? $newBrandNames : [])
            ->when(is_string($newBrandNames) && trim($newBrandNames) !== '', fn ($c) => $c->push($newBrandNames));

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $brand = $business->productBrands()->firstOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
            $ids->push((int) $brand->id);
        }

        if ($ids->isEmpty()) {
            return [];
        }

        return $business->productBrands()
            ->whereIn('id', $ids->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
