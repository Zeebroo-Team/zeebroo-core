<?php

namespace Modules\Service\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Service\Http\Controllers\Concerns\ResolvesServiceBusiness;
use Modules\Service\Models\ServiceBundle;
use Modules\Service\Models\ServiceItem;
use Modules\Service\Services\ServiceBundleService;

class ServiceBundleController extends Controller
{
    use ResolvesServiceBusiness;

    public function __construct(private readonly ServiceBundleService $service) {}

    public function index(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search  = trim((string) $request->query('q', ''));
        $bundles = $this->service->listForBusiness($business, $search ?: null);

        return view('service::bundles.index', [
            'business'   => $business,
            'bundles'    => $bundles,
            'search'     => $search,
            'hasItems'   => ServiceItem::where('business_id', $business->id)->exists(),
            'allServices'=> $this->loadServices($business),
            'currency'   => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $this->validated($request, $business);
        $bundle = $this->service->create($business, $data);

        return redirect()->route('service.bundles.show', $bundle)->with('status', 'Bundle created.');
    }

    public function show(Request $request, ServiceBundle $serviceBundle): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBundle($request, $serviceBundle);
        if ($business instanceof RedirectResponse) return $business;

        return view('service::bundles.show', [
            'business' => $business,
            'bundle'   => $serviceBundle->load('services'),
            'currency' => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function edit(Request $request, ServiceBundle $serviceBundle): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBundle($request, $serviceBundle);
        if ($business instanceof RedirectResponse) return $business;

        return view('service::bundles.edit', [
            'business'    => $business,
            'bundle'      => $serviceBundle->load('services'),
            'allServices' => $this->loadServices($business),
            'currency'    => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function update(Request $request, ServiceBundle $serviceBundle): RedirectResponse
    {
        $business = $this->requireBundle($request, $serviceBundle);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->update($serviceBundle, $this->validated($request, $business));

        return redirect()->route('service.bundles.show', $serviceBundle)->with('status', 'Bundle updated.');
    }

    public function destroy(Request $request, ServiceBundle $serviceBundle): RedirectResponse
    {
        $business = $this->requireBundle($request, $serviceBundle);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->delete($serviceBundle);

        return redirect()->route('service.bundles.index')->with('status', 'Bundle deleted.');
    }

    private function requireBundle(Request $request, ServiceBundle $bundle): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless($bundle->business_id === $business->id, 404);
        return $business;
    }

    private function loadServices(Business $business): \Illuminate\Support\Collection
    {
        return ServiceItem::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'duration_minutes']);
    }

    private function validated(Request $request, Business $business): array
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'price'             => ['nullable', 'numeric', 'min:0'],
            'is_active'         => ['nullable', 'boolean'],
            'bundle_svc_ids'    => ['nullable', 'array'],
            'bundle_svc_ids.*'  => [
                'integer',
                Rule::exists('service_items', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'bundle_svc_qtys'   => ['nullable', 'array'],
            'bundle_svc_qtys.*' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $ids  = array_map('intval', $validated['bundle_svc_ids']  ?? []);
        $qtys = $validated['bundle_svc_qtys'] ?? [];
        $lines = [];
        foreach ($ids as $i => $id) {
            if ($id > 0) {
                $lines[$id] = ['qty' => max(1, (int) ($qtys[$i] ?? 1))];
            }
        }
        $validated['service_lines'] = $lines;

        unset($validated['bundle_svc_ids'], $validated['bundle_svc_qtys']);

        return $validated;
    }
}
