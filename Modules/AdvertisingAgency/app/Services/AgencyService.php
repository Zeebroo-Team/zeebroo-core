<?php

namespace Modules\AdvertisingAgency\Services;

use Modules\AdvertisingAgency\Models\Agency;
use Modules\Business\Models\Business;

class AgencyService
{
    public function list(Business $business, ?string $q = null): \Illuminate\Support\Collection
    {
        return Agency::query()
            ->where('business_id', $business->id)
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('contact_person', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->get()
            ->map(fn ($a) => [
                'id'             => $a->id,
                'name'           => $a->name,
                'contact_person' => $a->contact_person,
                'email'          => $a->email,
                'phone'          => $a->phone,
                'address'        => $a->address,
                'status'         => $a->status,
                'created_at'     => $a->created_at,
            ]);
    }

    public function create(Business $business, array $data): Agency
    {
        return Agency::create([
            'business_id'    => $business->id,
            'name'           => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'email'          => $data['email']          ?? null,
            'phone'          => $data['phone']          ?? null,
            'address'        => $data['address']        ?? null,
            'status'         => $data['status'],
        ]);
    }

    public function update(Agency $agency, array $data): Agency
    {
        $agency->update([
            'name'           => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'email'          => $data['email']          ?? null,
            'phone'          => $data['phone']          ?? null,
            'address'        => $data['address']        ?? null,
            'status'         => $data['status'],
        ]);
        return $agency->fresh();
    }

    public function delete(Agency $agency): void
    {
        $agency->delete();
    }
}
