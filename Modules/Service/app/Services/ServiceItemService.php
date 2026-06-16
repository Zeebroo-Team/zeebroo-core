<?php

namespace Modules\Service\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Service\Models\ServiceItem;

class ServiceItemService
{
    public function listForBusiness(Business $business, ?string $search = null, ?string $status = null): Collection
    {
        $query = ServiceItem::query()->where('business_id', $business->id);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->orderBy('name')->get();
    }

    public function businessHasItems(Business $business): bool
    {
        return ServiceItem::query()->where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data): ServiceItem
    {
        return ServiceItem::create([
            'business_id'      => $business->id,
            'name'             => $data['name'],
            'description'      => filled($data['description'] ?? '') ? $data['description'] : null,
            'price'            => isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
            'duration_minutes' => isset($data['duration_minutes']) && $data['duration_minutes'] !== '' ? (int) $data['duration_minutes'] : null,
            'category'         => filled($data['category'] ?? '') ? $data['category'] : null,
            'is_active'        => (bool) ($data['is_active'] ?? true),
        ]);
    }

    public function update(ServiceItem $item, array $data): ServiceItem
    {
        $item->update([
            'name'             => $data['name'],
            'description'      => filled($data['description'] ?? '') ? $data['description'] : null,
            'price'            => isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
            'duration_minutes' => isset($data['duration_minutes']) && $data['duration_minutes'] !== '' ? (int) $data['duration_minutes'] : null,
            'category'         => filled($data['category'] ?? '') ? $data['category'] : null,
            'is_active'        => (bool) ($data['is_active'] ?? true),
        ]);

        return $item->fresh();
    }

    public function delete(ServiceItem $item): void
    {
        $item->delete();
    }

    public function itemForBusiness(Business $business, ServiceItem $item): ?ServiceItem
    {
        return $item->business_id === $business->id ? $item : null;
    }

    public function categories(Business $business): Collection
    {
        return ServiceItem::query()
            ->where('business_id', $business->id)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }
}
