<?php

namespace Modules\Service\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Service\Models\ServiceCategory;

class ServiceCategoryService
{
    public function listForBusiness(Business $business, ?string $search = null, ?string $status = null): Collection
    {
        $query = ServiceCategory::query()->where('business_id', $business->id);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->withCount('serviceItems')->orderBy('sort_order')->orderBy('name')->get();
    }

    public function businessHasCategories(Business $business): bool
    {
        return ServiceCategory::query()->where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data): ServiceCategory
    {
        return ServiceCategory::create([
            'business_id' => $business->id,
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'is_active'   => (bool) ($data['is_active'] ?? true),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function update(ServiceCategory $category, array $data): ServiceCategory
    {
        $category->update([
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'is_active'   => (bool) ($data['is_active'] ?? true),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
        ]);

        return $category->fresh();
    }

    public function delete(ServiceCategory $category): void
    {
        $category->delete();
    }

    public function categoryForBusiness(Business $business, ServiceCategory $category): ?ServiceCategory
    {
        return $category->business_id === $business->id ? $category : null;
    }

    /**
     * Resolve existing IDs and create any new-name categories, returning a flat list of IDs.
     *
     * @param  array<int|string>  $existingIds
     * @param  array<string>      $newNames
     * @return list<int>
     */
    public function resolveOrCreateIds(Business $business, array $existingIds, array $newNames): array
    {
        $ids = collect($existingIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique();

        foreach ($newNames as $name) {
            $name = trim((string) $name);
            if ($name === '') continue;

            $cat = ServiceCategory::firstOrCreate(
                ['business_id' => $business->id, 'name' => $name],
                ['is_active' => true, 'sort_order' => 0],
            );
            $ids->push((int) $cat->id);
        }

        return ServiceCategory::query()
            ->where('business_id', $business->id)
            ->whereIn('id', $ids->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
