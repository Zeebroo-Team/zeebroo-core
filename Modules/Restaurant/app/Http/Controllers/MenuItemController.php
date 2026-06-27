<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Services\MenuService;

class MenuItemController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function __construct(private readonly MenuService $menu) {}

    public function index(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search     = trim((string) $request->query('q', ''));
        $status     = (string) $request->query('status', 'all');
        $categoryId = $request->query('category') ? (int) $request->query('category') : null;

        return view('restaurant::menu.items.index', [
            'business'   => $business,
            'hasItems'   => $this->menu->hasItems($business),
            'items'      => $this->menu->itemsForBusiness($business, $search, $status, $categoryId),
            'categories' => $this->menu->categoriesForBusiness($business),
            'currency'   => (string) (get_settings('business.currency', '', $business) ?: ''),
            'search'     => $search,
            'status'     => $status,
            'categoryId' => $categoryId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $this->validated($request);
        $this->menu->createItem($business, $data);

        return redirect()->route('restaurant.menu.items.index')->with('status', 'Menu item added.');
    }

    public function show(Request $request, MenuItem $menuItem): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $menuItem->business_id === (int) $business->id, 404);

        $activeTab = in_array($request->query('tab'), ['overview', 'ingredients']) ? $request->query('tab') : 'overview';

        return view('restaurant::menu.items.show', [
            'business'  => $business,
            'item'      => $menuItem->load(['categories', 'imageFile', 'ingredients']),
            'currency'  => (string) (get_settings('business.currency', '', $business) ?: ''),
            'activeTab' => $activeTab,
        ]);
    }

    public function edit(Request $request, MenuItem $menuItem): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $menuItem->business_id === (int) $business->id, 404);

        return view('restaurant::menu.items.edit', [
            'business'   => $business,
            'item'       => $menuItem->load(['categories', 'imageFile', 'ingredients']),
            'categories' => $this->menu->categoriesForBusiness($business),
            'currency'   => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function update(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $menuItem->business_id === (int) $business->id, 404);

        $this->menu->updateItem($menuItem, $this->validated($request), $business);

        return redirect()->route('restaurant.menu.items.show', $menuItem)->with('status', 'Menu item updated.');
    }

    public function destroy(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $menuItem->business_id === (int) $business->id, 404);

        $this->menu->deleteItem($menuItem);

        return redirect()->route('restaurant.menu.items.index')->with('status', 'Menu item deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string', 'max:3000'],
            'price'                 => ['required', 'numeric', 'min:0'],
            'is_available'          => ['nullable', 'boolean'],
            'prep_time_minutes'     => ['nullable', 'integer', 'min:1', 'max:9999'],
            'dietary_tags'          => ['nullable', 'array'],
            'dietary_tags.*'        => ['string', 'in:vegetarian,vegan,gluten_free,halal,spicy,nut_free,dairy_free'],
            'file_manager_file_id'  => ['nullable', 'integer', 'exists:file_manager_files,id'],
            'menu_category_ids'     => ['nullable', 'array'],
            'menu_category_ids.*'   => ['integer', 'exists:restaurant_menu_categories,id'],
            'new_category_names'    => ['nullable', 'array'],
            'new_category_names.*'  => ['string', 'max:100'],
        ]);
    }
}
