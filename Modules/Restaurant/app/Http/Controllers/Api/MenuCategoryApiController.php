<?php

namespace Modules\Restaurant\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Services\MenuService;

class MenuCategoryApiController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function __construct(private readonly MenuService $menu) {}

    private function resolveBusiness(Request $request): Business|JsonResponse
    {
        $b = $this->requireBusiness($request);
        return $b instanceof Business ? $b : response()->json(['error' => 'Business not found'], 404);
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;

        $q = trim((string) $request->query('q', ''));

        $categories = $this->menu->categoriesForBusiness($business);

        if ($q !== '') {
            $categories = $categories->filter(fn ($c) =>
                str_contains(mb_strtolower($c->name), mb_strtolower($q)) ||
                str_contains(mb_strtolower((string) $c->description), mb_strtolower($q))
            );
        }

        return response()->json([
            'data' => $categories->values()->map(fn ($c) => $this->format($c)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $category = $this->menu->createCategory($business, $data);

        return response()->json(['data' => $this->format($category)], 201);
    }

    public function update(Request $request, MenuCategory $menuCategory): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $menuCategory->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $this->menu->updateCategory($menuCategory, $data);

        return response()->json(['data' => $this->format($menuCategory->fresh())]);
    }

    public function destroy(Request $request, MenuCategory $menuCategory): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $menuCategory->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $this->menu->deleteCategory($menuCategory);

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;

        $ids = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ])['ids'];

        foreach ($ids as $order => $id) {
            MenuCategory::where('id', $id)
                ->where('business_id', $business->id)
                ->update(['sort_order' => $order + 1]);
        }

        return response()->json(['success' => true]);
    }

    private function format(MenuCategory $c): array
    {
        return [
            'id'          => (int) $c->id,
            'name'        => $c->name,
            'description' => $c->description,
            'sort_order'  => (int) $c->sort_order,
            'is_active'   => (bool) $c->is_active,
            'item_count'  => $c->menuItems()->count(),
        ];
    }
}
