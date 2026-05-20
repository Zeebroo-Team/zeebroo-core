<?php

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Business\Models\Business;
use Modules\Product\Models\ProductBrand;

class ProductBrandService
{
    public function listForBusiness(?Business $business): Collection
    {
        if (!$business instanceof Business) {
            return new Collection();
        }

        return $business->productBrands()->orderBy('name')->get();
    }

    public function create(Business $business, array $data): ProductBrand
    {
        return $business->productBrands()->create($data);
    }

    public function update(ProductBrand $brand, array $data): ProductBrand
    {
        $brand->fill($data);
        $brand->save();

        return $brand->refresh();
    }

    public function delete(ProductBrand $brand): bool
    {
        return (bool) $brand->delete();
    }

    public function brandForBusiness(Business $business, ProductBrand $brand): ?ProductBrand
    {
        if ((int) $brand->business_id !== (int) $business->id) {
            return null;
        }

        return $brand;
    }
}
