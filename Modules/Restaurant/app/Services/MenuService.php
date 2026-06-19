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
            ->withCount('menuItems')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function itemsForBusiness(Business $business, string $search = '', string $status = 'all', ?int $categoryId = null): LengthAwarePaginator
    {
        $query = MenuItem::where('business_id', $business->id)->with('category');

        if ($search !== '') {
            $query->where('name', 'like', '%' . $search . '%');
        }

        if ($status === 'available') {
            $query->where('is_available', true);
        } elseif ($status === 'unavailable') {
            $query->where('is_available', false);
        }

        if ($categoryId) {
            $query->where('menu_category_id', $categoryId);
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
        $data['sort_order'] = MenuItem::where('business_id', $business->id)->max('sort_order') + 1;

        return MenuItem::create(['business_id' => $business->id] + $data);
    }

    public function updateItem(MenuItem $item, array $data): void
    {
        $item->update($data);
    }

    public function deleteItem(MenuItem $item): void
    {
        $item->delete();
    }
}
