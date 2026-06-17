<?php

namespace Modules\Service\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Service\Models\ServiceBundle;

class ServiceBundleService
{
    public function listForBusiness(Business $business, ?string $search = null): Collection
    {
        $query = ServiceBundle::where('business_id', $business->id)
            ->with('services');

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('services', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('name')->get();
    }

    public function create(Business $business, array $data): ServiceBundle
    {
        $lines = $data['service_lines'] ?? [];

        $bundle = ServiceBundle::create([
            'business_id' => $business->id,
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'price'       => isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
            'is_active'   => (bool) ($data['is_active'] ?? true),
        ]);

        if ($lines) {
            $bundle->services()->sync($lines);
        }

        return $bundle->load('services');
    }

    public function update(ServiceBundle $bundle, array $data): ServiceBundle
    {
        $lines = $data['service_lines'] ?? [];

        $bundle->update([
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'price'       => isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
            'is_active'   => (bool) ($data['is_active'] ?? true),
        ]);

        $bundle->services()->sync($lines);

        return $bundle->fresh()->load('services');
    }

    public function delete(ServiceBundle $bundle): void
    {
        $bundle->delete();
    }
}
