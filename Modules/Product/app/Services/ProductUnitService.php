<?php

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Business\Models\Business;
use Modules\Product\Models\ProductUnit;

class ProductUnitService
{
    public function listForBusiness(?Business $business): Collection
    {
        if (!$business instanceof Business) {
            return new Collection();
        }

        return $business->productUnits()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function create(Business $business, array $data): ProductUnit
    {
        return $business->productUnits()->create($data);
    }

    public function update(ProductUnit $unit, array $data): ProductUnit
    {
        $unit->fill($data);
        $unit->save();

        return $unit->refresh();
    }

    public function delete(ProductUnit $unit): bool
    {
        return (bool) $unit->delete();
    }

    public function unitForBusiness(Business $business, ProductUnit $unit): ?ProductUnit
    {
        if ((int) $unit->business_id !== (int) $business->id) {
            return null;
        }

        return $unit;
    }
}
