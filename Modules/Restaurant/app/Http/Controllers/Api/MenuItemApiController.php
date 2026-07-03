<?php

namespace Modules\Restaurant\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\Ingredient;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Services\MenuService;

class MenuItemApiController extends Controller
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

        $q      = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $catId  = $request->query('category') ? (int) $request->query('category') : null;

        $paginator = $this->menu->itemsForBusiness($business, $q, $status, $catId);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($i) => $this->format($i)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;

        $data = $this->validated($request);
        $item = $this->menu->createItem($business, $data);
        $item->load(['categories', 'imageFile']);

        return response()->json(['data' => $this->format($item)], 201);
    }

    public function show(Request $request, MenuItem $menuItem): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $menuItem->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $menuItem->load(['categories', 'imageFile']);

        return response()->json(['data' => $this->format($menuItem)]);
    }

    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $menuItem->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $this->validated($request);
        $this->menu->updateItem($menuItem, $data, $business);

        return response()->json(['data' => $this->format($menuItem->fresh(['categories', 'imageFile']))]);
    }

    public function destroy(Request $request, MenuItem $menuItem): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $menuItem->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $this->menu->deleteItem($menuItem);

        return response()->json(['success' => true]);
    }

    public function toggleAvailability(Request $request, MenuItem $menuItem): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $menuItem->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $menuItem->update(['is_available' => ! $menuItem->is_available]);

        return response()->json(['data' => ['is_available' => $menuItem->is_available]]);
    }

    public function getIngredients(Request $request, MenuItem $menuItem): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $menuItem->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $rows = $menuItem->ingredients()->get()->map(fn ($i) => [
            'id'                => (int) $i->id,
            'name'              => $i->name,
            'unit'              => $i->unit,
            'quantity_required' => (float) $i->pivot->quantity_required,
        ]);

        return response()->json(['data' => $rows->values()]);
    }

    public function syncIngredients(Request $request, MenuItem $menuItem): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof JsonResponse) return $business;
        if ((int) $menuItem->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $rows = $request->validate([
            'ingredients'                    => ['present', 'array'],
            'ingredients.*.ingredient_id'    => ['required', 'integer'],
            'ingredients.*.quantity_required' => ['required', 'numeric', 'min:0.001'],
        ])['ingredients'];

        $sync = [];
        foreach ($rows as $row) {
            $id = (int) $row['ingredient_id'];
            // Ensure ingredient belongs to this business
            if (Ingredient::where('id', $id)->where('business_id', $business->id)->exists()) {
                $sync[$id] = ['quantity_required' => (float) $row['quantity_required']];
            }
        }

        $menuItem->ingredients()->sync($sync);

        return response()->json(['success' => true]);
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
            'menu_category_ids'     => ['nullable', 'array'],
            'menu_category_ids.*'   => ['integer'],
            'file_manager_file_id'  => ['nullable', 'integer', 'exists:file_manager_files,id'],
        ]);
    }

    private function format(MenuItem $i): array
    {
        $imageUrl = null;
        if ($i->relationLoaded('imageFile') && $i->imageFile) {
            $imageUrl = $i->imageFile->url ?? null;
        }

        return [
            'id'                  => (int) $i->id,
            'name'                => $i->name,
            'description'         => $i->description,
            'price'               => (float) $i->price,
            'is_available'        => (bool) $i->is_available,
            'prep_time_minutes'   => $i->prep_time_minutes,
            'prep_label'          => $i->prepLabel(),
            'dietary_tags'        => $i->dietary_tags ?? [],
            'sort_order'          => (int) $i->sort_order,
            'file_manager_file_id' => $i->file_manager_file_id,
            'image_url'           => $imageUrl,
            'categories'          => $i->relationLoaded('categories')
                ? $i->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all()
                : [],
        ];
    }
}
