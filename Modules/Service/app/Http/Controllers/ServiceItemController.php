<?php

namespace Modules\Service\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Service\Http\Controllers\Concerns\ResolvesServiceBusiness;
use Modules\HRManagement\Models\Employee;
use Modules\Product\Models\Product;
use Modules\Service\Models\ServiceCategory;
use Modules\Service\Models\ServiceItem;
use Modules\Service\Services\ServiceCategoryService;
use Modules\Service\Services\ServiceItemService;

class ServiceItemController extends Controller
{
    use ResolvesServiceBusiness;

    public function __construct(
        private readonly ServiceItemService    $service,
        private readonly ServiceCategoryService $categoryService,
    ) {}

    public function index(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');

        return view('service::catalog.index', [
            'business'          => $business,
            'hasItems'          => $this->service->businessHasItems($business),
            'items'             => $this->service->listForBusiness($business, $search, $status),
            'serviceCategories' => $this->loadCategories($business),
            'employees'         => $this->loadEmployees($business),
            'products'          => $this->loadProducts($business),
            'currency'          => (string) (get_settings('business.currency', '', $business) ?: ''),
            'search'            => $search,
            'status'            => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $this->validated($request, $business);
        $this->service->create($business, $data);

        return redirect()->route('service.catalog.index')->with('status', 'Service added.');
    }

    public function show(Request $request, ServiceItem $serviceItem): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireItem($request, $serviceItem);
        if ($business instanceof RedirectResponse) return $business;

        return view('service::catalog.show', [
            'business' => $business,
            'item'     => $serviceItem->load(['categories', 'employees.jobTitle', 'products.stockLayers', 'discounts']),
            'currency' => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function edit(Request $request, ServiceItem $serviceItem): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireItem($request, $serviceItem);
        if ($business instanceof RedirectResponse) return $business;

        return view('service::catalog.edit', [
            'business'          => $business,
            'item'              => $serviceItem->load(['categories', 'employees', 'products']),
            'serviceCategories' => $this->loadCategories($business),
            'employees'         => $this->loadEmployees($business),
            'products'          => $this->loadProducts($business),
            'currency'          => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function update(Request $request, ServiceItem $serviceItem): RedirectResponse
    {
        $business = $this->requireItem($request, $serviceItem);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->update($serviceItem, $this->validated($request, $business));

        return redirect()->route('service.catalog.show', $serviceItem)->with('status', 'Service updated.');
    }

    public function destroy(Request $request, ServiceItem $serviceItem): RedirectResponse
    {
        $business = $this->requireItem($request, $serviceItem);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->delete($serviceItem);

        return redirect()->route('service.catalog.index')->with('status', 'Service deleted.');
    }

    private function requireItem(Request $request, ServiceItem $item): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        abort_unless($this->service->itemForBusiness($business, $item) instanceof ServiceItem, 404);

        return $business;
    }

    private function loadCategories(Business $business): \Illuminate\Support\Collection
    {
        return ServiceCategory::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function loadEmployees(Business $business): \Illuminate\Support\Collection
    {
        return Employee::where('business_id', $business->id)
            ->with('jobTitle')
            ->orderBy('full_name')
            ->get();
    }

    private function loadProducts(Business $business): \Illuminate\Support\Collection
    {
        return Product::where('business_id', $business->id)
            ->where('is_active', true)
            ->with('stockLayers')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit_price', 'unit']);
    }

    private function validated(Request $request, Business $business): array
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string', 'max:5000'],
            'price'                 => ['nullable', 'numeric', 'min:0'],
            'duration_minutes'      => ['nullable', 'integer', 'min:1', 'max:99999'],
            'is_active'             => ['nullable', 'boolean'],
            'service_category_ids'  => ['nullable', 'array'],
            'service_category_ids.*'=> [
                'integer',
                Rule::exists('service_categories', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'new_category_names'    => ['nullable', 'array'],
            'new_category_names.*'  => ['string', 'max:255'],
            'employee_ids'          => ['nullable', 'array'],
            'employee_ids.*'        => [
                'integer',
                Rule::exists('hr_employees', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'svc_product_ids'       => ['nullable', 'array'],
            'svc_product_ids.*'     => [
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $business->id)->where('is_active', true)),
            ],
            'svc_product_qtys'      => ['nullable', 'array'],
            'svc_product_qtys.*'    => ['nullable', 'numeric', 'min:0.001', 'max:999999'],
        ]);

        $newNames = array_values(array_filter(array_map('trim', (array) ($validated['new_category_names'] ?? []))));
        $validated['service_category_ids'] = $this->categoryService->resolveOrCreateIds(
            $business,
            $validated['service_category_ids'] ?? [],
            $newNames,
        );

        unset($validated['new_category_names']);

        $validated['employee_ids'] = $request->boolean('assign_employees')
            ? array_map('intval', $validated['employee_ids'] ?? [])
            : [];

        // Build product_lines: [product_id => ['qty' => n], ...]  for sync()
        if ($request->boolean('assign_products')) {
            $productIds  = array_map('intval', $validated['svc_product_ids'] ?? []);
            $productQtys = $validated['svc_product_qtys'] ?? [];
            $lines = [];
            foreach ($productIds as $i => $pid) {
                if ($pid > 0) {
                    $lines[$pid] = ['qty' => max(0.001, (float) ($productQtys[$i] ?? 1))];
                }
            }
            $validated['product_lines'] = $lines;
        } else {
            $validated['product_lines'] = [];
        }

        unset($validated['svc_product_ids'], $validated['svc_product_qtys']);

        return $validated;
    }
}
