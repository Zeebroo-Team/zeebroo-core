<?php

namespace Modules\Purchase\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Business\Models\Business;
use Modules\Purchase\Models\Supplier;

class SupplierService
{
    public function listForBusiness(?Business $business): Collection
    {
        if (!$business instanceof Business) {
            return new Collection();
        }

        return $business->suppliers()->orderBy('name')->get();
    }

    public function create(Business $business, array $data): Supplier
    {
        return $business->suppliers()->create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->fill($data);
        $supplier->save();

        return $supplier->refresh();
    }

    public function delete(Supplier $supplier): bool
    {
        return (bool) $supplier->delete();
    }

    public function supplierForBusiness(Business $business, Supplier $supplier): ?Supplier
    {
        if ((int) $supplier->business_id !== (int) $business->id) {
            return null;
        }

        return $supplier;
    }
}
