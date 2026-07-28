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
        $supplier = $business->suppliers()->create($data);

        try {
            app(\Modules\AutomationEditor\Services\AutomationRunnerService::class)->dispatch('supplier.created', $business, [
                'event'    => 'supplier.created',
                'supplier' => [
                    'id'           => $supplier->id,
                    'name'         => $supplier->name,
                    'contact_name' => $supplier->contact_name,
                    'email'        => $supplier->email,
                    'phone'        => $supplier->phone,
                    'created_at'   => $supplier->created_at?->toIso8601String(),
                ],
            ]);
        } catch (\Throwable) {}

        return $supplier;
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
