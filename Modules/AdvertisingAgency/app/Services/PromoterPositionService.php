<?php

namespace Modules\AdvertisingAgency\Services;

use Modules\AdvertisingAgency\Models\PromoterPosition;
use Modules\Business\Models\Business;

class PromoterPositionService
{
    public function list(Business $business, ?string $q = null): \Illuminate\Support\Collection
    {
        return PromoterPosition::query()
            ->where('business_id', $business->id)
            ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'description' => $p->description,
                'created_at'  => $p->created_at,
            ]);
    }

    public function create(Business $business, array $data): PromoterPosition
    {
        return PromoterPosition::create([
            'business_id' => $business->id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
    }

    public function update(PromoterPosition $position, array $data): PromoterPosition
    {
        $position->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return $position->fresh();
    }

    public function delete(PromoterPosition $position): void
    {
        $position->delete();
    }
}
