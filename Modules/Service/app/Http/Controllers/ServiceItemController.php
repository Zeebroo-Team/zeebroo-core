<?php

namespace Modules\Service\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\View;
use Modules\Business\Models\Business;
use Modules\Service\Http\Controllers\Concerns\ResolvesServiceBusiness;
use Modules\Service\Models\ServiceItem;
use Modules\Service\Services\ServiceItemService;

class ServiceItemController extends Controller
{
    use ResolvesServiceBusiness;

    public function __construct(
        private readonly ServiceItemService $service,
    ) {}

    public function index(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');

        return view('service::catalog.index', [
            'business'  => $business,
            'hasItems'  => $this->service->businessHasItems($business),
            'items'     => $this->service->listForBusiness($business, $search, $status),
            'categories'=> $this->service->categories($business),
            'currency'  => (string) (get_settings('business.currency', '', $business) ?: ''),
            'search'    => $search,
            'status'    => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $this->validated($request);
        $this->service->create($business, $data);

        return redirect()->route('service.catalog.index')->with('status', 'Service added.');
    }

    public function show(Request $request, ServiceItem $serviceItem): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireItem($request, $serviceItem);
        if ($business instanceof RedirectResponse) return $business;

        return view('service::catalog.show', [
            'business' => $business,
            'item'     => $serviceItem,
            'currency' => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function edit(Request $request, ServiceItem $serviceItem): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireItem($request, $serviceItem);
        if ($business instanceof RedirectResponse) return $business;

        return view('service::catalog.edit', [
            'business' => $business,
            'item'     => $serviceItem,
            'currency' => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function update(Request $request, ServiceItem $serviceItem): RedirectResponse
    {
        $business = $this->requireItem($request, $serviceItem);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->update($serviceItem, $this->validated($request));

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

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:5000'],
            'price'            => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'category'         => ['nullable', 'string', 'max:120'],
            'is_active'        => ['nullable', 'boolean'],
        ]);
    }
}
