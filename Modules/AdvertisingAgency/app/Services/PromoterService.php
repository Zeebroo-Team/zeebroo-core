<?php

namespace Modules\AdvertisingAgency\Services;

use Modules\AdvertisingAgency\Models\Promoter;
use Modules\Business\Models\Business;

class PromoterService
{
    public function list(Business $business, ?string $q = null): \Illuminate\Support\Collection
    {
        return Promoter::query()
            ->where('business_id', $business->id)
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('position', 'like', "%{$q}%")
                    ->orWhere('nic', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'position'     => $p->position,
                'nic'          => $p->nic,
                'phone'        => $p->phone,
                'bank_name'    => $p->bank_name,
                'bank_branch'  => $p->bank_branch,
                'bank_account' => $p->bank_account,
                'created_at'   => $p->created_at,
            ]);
    }

    public function create(Business $business, array $data): Promoter
    {
        return Promoter::create([
            'business_id'  => $business->id,
            'name'         => $data['name'],
            'position'     => $data['position']    ?? null,
            'nic'          => $data['nic']          ?? null,
            'phone'        => $data['phone']        ?? null,
            'bank_name'    => $data['bank_name'],
            'bank_branch'  => $data['bank_branch'],
            'bank_account' => $data['bank_account'],
        ]);
    }

    public function update(Promoter $promoter, array $data): Promoter
    {
        $promoter->update([
            'name'         => $data['name'],
            'position'     => $data['position']    ?? null,
            'nic'          => $data['nic']          ?? null,
            'phone'        => $data['phone']        ?? null,
            'bank_name'    => $data['bank_name'],
            'bank_branch'  => $data['bank_branch'],
            'bank_account' => $data['bank_account'],
        ]);

        return $promoter->fresh();
    }

    public function delete(Promoter $promoter): void
    {
        $promoter->delete();
    }
}
