<?php

namespace Modules\Restaurant\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Models\MenuItem;

class MenuService
{
    public function categoriesForBusiness(Business $business): Collection
    {
        return MenuCategory::where('business_id', $business->id)
            ->withCount('menuItemsMulti')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function itemsForBusiness(Business $business, string $search = '', string $status = 'all', ?int $categoryId = null): LengthAwarePaginator
    {
        $query = MenuItem::where('business_id', $business->id)
            ->with(['categories', 'imageFile']);

        if ($search !== '') {
            $query->where('name', 'like', '%' . $search . '%');
        }

        if ($status === 'available') {
            $query->where('is_available', true);
        } elseif ($status === 'unavailable') {
            $query->where('is_available', false);
        }

        if ($categoryId) {
            $query->whereHas('categories', fn ($q) => $q->where('restaurant_menu_categories.id', $categoryId));
        }

        return $query->orderBy('sort_order')->orderBy('name')->paginate(25);
    }

    public function hasItems(Business $business): bool
    {
        return MenuItem::where('business_id', $business->id)->exists();
    }

    public function createCategory(Business $business, array $data): MenuCategory
    {
        $data['sort_order'] = MenuCategory::where('business_id', $business->id)->max('sort_order') + 1;

        return MenuCategory::create(['business_id' => $business->id] + $data);
    }

    public function updateCategory(MenuCategory $category, array $data): void
    {
        $category->update($data);
    }

    public function deleteCategory(MenuCategory $category): void
    {
        MenuItem::where('menu_category_id', $category->id)->update(['menu_category_id' => null]);
        $category->delete();
    }

    public function createItem(Business $business, array $data): MenuItem
    {
        $categoryIds     = $data['menu_category_ids']   ?? [];
        $newCategoryNames = $data['new_category_names'] ?? [];
        unset($data['menu_category_ids'], $data['new_category_names']);

        $data['sort_order'] = MenuItem::where('business_id', $business->id)->max('sort_order') + 1;

        $item = MenuItem::create(['business_id' => $business->id] + $data);

        $this->syncCategories($item, $business, $categoryIds, $newCategoryNames);

        return $item;
    }

    public function updateItem(MenuItem $item, array $data, ?Business $business = null): void
    {
        $business         = $business ?? $item->business;
        $categoryIds      = $data['menu_category_ids']  ?? [];
        $newCategoryNames = $data['new_category_names'] ?? [];
        unset($data['menu_category_ids'], $data['new_category_names']);

        $item->update($data);

        $this->syncCategories($item, $business, $categoryIds, $newCategoryNames);
    }

    public function deleteItem(MenuItem $item): void
    {
        $item->delete();
    }

    private function syncCategories(MenuItem $item, ?Business $business, array $categoryIds, array $newNames): void
    {
        $resolvedIds = array_map('intval', array_filter($categoryIds));

        if ($business && ! empty($newNames)) {
            foreach ($newNames as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $cat = MenuCategory::firstOrCreate(
                    ['business_id' => $business->id, 'name' => $name],
                    ['sort_order' => MenuCategory::where('business_id', $business->id)->max('sort_order') + 1]
                );
                $resolvedIds[] = (int) $cat->id;
            }
        }

        $resolvedIds = array_values(array_unique(array_filter($resolvedIds)));

        $syncData = [];
        foreach ($resolvedIds as $i => $id) {
            $syncData[$id] = ['sort_order' => $i];
        }

        $item->categories()->sync($syncData);

        // Keep legacy menu_category_id in sync (first category)
        $item->updateQuietly(['menu_category_id' => $resolvedIds[0] ?? null]);
    }
}
