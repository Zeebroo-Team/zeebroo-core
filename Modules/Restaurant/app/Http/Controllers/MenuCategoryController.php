<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Services\MenuService;

class MenuCategoryController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function __construct(private readonly MenuService $menu) {}

    public function index(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        return view('restaurant::menu.categories.index', [
            'business'   => $business,
            'categories' => $this->menu->categoriesForBusiness($business),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $this->menu->createCategory($business, $data);

        return redirect()->route('restaurant.menu.categories.index')->with('status', 'Category added.');
    }

    public function update(Request $request, MenuCategory $menuCategory): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $menuCategory->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $this->menu->updateCategory($menuCategory, $data);

        return redirect()->route('restaurant.menu.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Request $request, MenuCategory $menuCategory): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $menuCategory->business_id === (int) $business->id, 404);

        $this->menu->deleteCategory($menuCategory);

        return redirect()->route('restaurant.menu.categories.index')->with('status', 'Category deleted.');
    }
}
