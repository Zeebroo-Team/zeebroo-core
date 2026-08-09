<?php

namespace Modules\AdvertisingAgency\Services;

use Modules\AdvertisingAgency\Models\Coordinator;
use Modules\Business\Models\Business;

class CoordinatorService
{
    public function list(Business $business, ?string $q = null): \Illuminate\Support\Collection
    {
        return Coordinator::query()
            ->where('business_id', $business->id)
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('nic', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'nic'          => $c->nic,
                'phone'        => $c->phone,
                'status'       => $c->status,
                'bank_name'    => $c->bank_name,
                'bank_branch'  => $c->bank_branch,
                'bank_account' => $c->bank_account,
                'created_at'   => $c->created_at,
            ]);
    }

    public function create(Business $business, array $data): Coordinator
    {
        return Coordinator::create([
            'business_id'  => $business->id,
            'name'         => $data['name'],
            'nic'          => $data['nic'],
            'phone'        => $data['phone'],
            'status'       => $data['status'],
            'bank_name'    => $data['bank_name'],
            'bank_branch'  => $data['bank_branch'],
            'bank_account' => $data['bank_account'],
        ]);
    }

    public function update(Coordinator $coordinator, array $data): Coordinator
    {
        $coordinator->update([
            'name'         => $data['name'],
            'nic'          => $data['nic'],
            'phone'        => $data['phone'],
            'status'       => $data['status'],
            'bank_name'    => $data['bank_name'],
            'bank_branch'  => $data['bank_branch'],
            'bank_account' => $data['bank_account'],
        ]);

        return $coordinator->fresh();
    }

    public function delete(Coordinator $coordinator): void
    {
        $coordinator->delete();
    }
}
