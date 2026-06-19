<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
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

        $search     = trim((string) $request->query('q', ''));
        $categories = $this->menu->categoriesForBusiness($business);
        $totalCount = $categories->count();

        $filtered = $search !== ''
            ? $categories->filter(fn ($c) => str_contains(mb_strtolower($c->name), mb_strtolower($search))
                || str_contains(mb_strtolower((string) $c->description), mb_strtolower($search)))
            : null;

        return view('restaurant::menu.categories.index', [
            'business'    => $business,
            'categories'  => $categories,
            'filtered'    => $filtered,
            'totalCount'  => $totalCount,
            'search'      => $search,
            'isFiltering' => $search !== '',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) abort(403);

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        foreach ($ids as $order => $id) {
            MenuCategory::where('id', $id)
                ->where('business_id', $business->id)
                ->update(['sort_order' => $order + 1]);
        }

        return response()->json(['success' => true]);
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
