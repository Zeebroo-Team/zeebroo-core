<?php

namespace Modules\Service\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Service\Http\Controllers\Concerns\ResolvesServiceBusiness;
use Modules\Service\Models\ServiceCategory;
use Modules\Service\Services\ServiceCategoryService;

class ServiceCategoryController extends Controller
{
    use ResolvesServiceBusiness;

    public function __construct(private readonly ServiceCategoryService $service) {}

    public function index(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search  = trim((string) $request->query('q', ''));
        $status  = in_array($request->query('status'), ['active', 'inactive'], true)
            ? (string) $request->query('status') : null;

        $totalCount = ServiceCategory::where('business_id', $business->id)->count();

        return view('service::categories.index', [
            'business'    => $business,
            'hasItems'    => $totalCount > 0,
            'totalCount'  => $totalCount,
            'categories'  => $this->service->listForBusiness($business, $search, $status),
            'search'      => $search,
            'filterStatus'=> $status,
            'isFiltering' => $search !== '' || $status !== null,
        ]);
    }

    public function reorder(Request $request): \Illuminate\Http\JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'No business.'], 422);
        }

        $order = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']])['order'];

        foreach ($order as $position => $id) {
            ServiceCategory::where('id', (int) $id)
                ->where('business_id', $business->id)
                ->update(['sort_order' => $position]);
        }

        return response()->json(['ok' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $this->validated($request, $business);
        $this->service->create($business, $data);

        return redirect()->route('service.categories.index')->with('status', 'Category added.');
    }

    public function quickStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'No business selected.'], 422);
        }

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('service_categories', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
        ]);

        $category = $this->service->create($business, ['name' => $validated['name'], 'is_active' => true]);

        return response()->json(['id' => $category->id, 'name' => $category->name]);
    }

    public function edit(Request $request, ServiceCategory $serviceCategory): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireCategory($request, $serviceCategory);
        if ($business instanceof RedirectResponse) return $business;

        return view('service::categories.edit', [
            'business' => $business,
            'category' => $serviceCategory,
        ]);
    }

    public function update(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $business = $this->requireCategory($request, $serviceCategory);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->update($serviceCategory, $this->validated($request, $business, $serviceCategory));

        return redirect()->route('service.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $business = $this->requireCategory($request, $serviceCategory);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->delete($serviceCategory);

        return redirect()->route('service.categories.index')->with('status', 'Category deleted.');
    }

    private function requireCategory(Request $request, ServiceCategory $category): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        abort_unless($this->service->categoryForBusiness($business, $category) instanceof ServiceCategory, 404);

        return $business;
    }

    private function validated(Request $request, Business $business, ?ServiceCategory $ignore = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('service_categories', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id))
                    ->ignore($ignore?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $validated['is_active']  = $request->boolean('is_active', true);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }
}
