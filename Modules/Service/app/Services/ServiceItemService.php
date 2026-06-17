<?php

namespace Modules\Service\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Service\Models\ServiceItem;

class ServiceItemService
{
    public function listForBusiness(Business $business, ?string $search = null, ?string $status = null): Collection
    {
        $query = ServiceItem::query()
            ->with('categories')
            ->where('business_id', $business->id);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('categories', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
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
        $categoryIds  = $data['service_category_ids'] ?? [];
        $employeeIds  = $data['employee_ids'] ?? [];
        $productLines = $data['product_lines'] ?? [];

        $item = ServiceItem::create([
            'business_id'      => $business->id,
            'name'             => $data['name'],
            'description'      => filled($data['description'] ?? '') ? $data['description'] : null,
            'price'            => isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
            'duration_minutes' => isset($data['duration_minutes']) && $data['duration_minutes'] !== '' ? (int) $data['duration_minutes'] : null,
            'is_active'        => (bool) ($data['is_active'] ?? true),
        ]);

        $item->categories()->sync($categoryIds);
        $item->employees()->sync($employeeIds);
        $item->products()->sync($productLines);

        return $item->load(['categories', 'employees', 'products']);
    }

    public function update(ServiceItem $item, array $data): ServiceItem
    {
        $categoryIds  = $data['service_category_ids'] ?? null;
        $employeeIds  = $data['employee_ids'] ?? null;
        $productLines = $data['product_lines'] ?? null;

        $item->update([
            'name'             => $data['name'],
            'description'      => filled($data['description'] ?? '') ? $data['description'] : null,
            'price'            => isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
            'duration_minutes' => isset($data['duration_minutes']) && $data['duration_minutes'] !== '' ? (int) $data['duration_minutes'] : null,
            'is_active'        => (bool) ($data['is_active'] ?? true),
        ]);

        if ($categoryIds !== null)  $item->categories()->sync($categoryIds);
        if ($employeeIds !== null)  $item->employees()->sync($employeeIds);
        if ($productLines !== null) $item->products()->sync($productLines);

        return $item->fresh()->load(['categories', 'employees', 'products']);
    }

    public function delete(ServiceItem $item): void
    {
        $item->delete();
    }

    public function itemForBusiness(Business $business, ServiceItem $item): ?ServiceItem
    {
        return $item->business_id === $business->id ? $item : null;
    }
}
