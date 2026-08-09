<?php

namespace Modules\AdvertisingAgency\Services;

use Modules\AdvertisingAgency\Models\Brand;
use Modules\Business\Models\Business;

class BrandService
{
    public function list(Business $business, ?string $q = null)
    {
        return Brand::query()
            ->where('business_id', $business->id)
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('short_code', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%")
                    ->orWhere('contact_person', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->get();
    }

    public function create(Business $business, array $data): Brand
    {
        return Brand::create([
            'business_id'    => $business->id,
            'name'           => $data['name'],
            'short_code'     => strtoupper($data['short_code']),
            'email'          => $data['email'],
            'phone'          => $data['phone'] ?? null,
            'company_name'   => $data['company_name'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'address'        => $data['address'] ?? null,
        ]);
    }

    public function update(Brand $brand, array $data): Brand
    {
        $brand->update([
            'name'           => $data['name'],
            'short_code'     => strtoupper($data['short_code']),
            'email'          => $data['email'],
            'phone'          => $data['phone'] ?? null,
            'company_name'   => $data['company_name'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'address'        => $data['address'] ?? null,
        ]);

        return $brand->fresh();
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }
}
