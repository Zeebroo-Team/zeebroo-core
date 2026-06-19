<?php

namespace Modules\Service\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Customer;
use Modules\Service\Http\Controllers\Concerns\ResolvesServiceBusiness;
use Modules\Service\Models\ServiceItem;
use Modules\Service\Models\ServiceRequest;
use Modules\Service\Services\ServiceRequestService;

class ServiceRequestController extends Controller
{
    use ResolvesServiceBusiness;

    public function __construct(
        private readonly ServiceRequestService $service,
    ) {}

    public function index(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');

        return view('service::requests.index', [
            'business'    => $business,
            'hasRequests' => $this->service->businessHasRequests($business),
            'requests'    => $this->service->listForBusiness($business, $search, $status),
            'serviceItems'=> ServiceItem::where('business_id', $business->id)->where('is_active', true)->orderBy('name')->get(),
            'customers'   => Customer::where('business_id', $business->id)->orderBy('name')->get(),
            'currency'    => (string) (get_settings('business.currency', '', $business) ?: ''),
            'search'      => $search,
            'statusFilter'=> $status,
            'statusTabs'  => $this->statusTabs(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $req = $this->service->create($business, $this->validated($request, $business));

        return redirect()->route('service.requests.show', $req)
            ->with('status', 'Service request ' . $req->request_number . ' created.');
    }

    public function show(Request $request, ServiceRequest $serviceRequest): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireRequest($request, $serviceRequest);
        if ($business instanceof RedirectResponse) return $business;

        $serviceRequest->load(['serviceItem', 'customer']);

        return view('service::requests.show', [
            'business' => $business,
            'req'      => $serviceRequest,
            'currency' => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function edit(Request $request, ServiceRequest $serviceRequest): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireRequest($request, $serviceRequest);
        if ($business instanceof RedirectResponse) return $business;

        if (!$serviceRequest->isEditable()) {
            return redirect()->route('service.requests.show', $serviceRequest)
                ->withErrors(['req' => 'This request can no longer be edited.']);
        }

        $serviceRequest->load(['serviceItem', 'customer']);

        return view('service::requests.edit', [
            'business'    => $business,
            'req'         => $serviceRequest,
            'serviceItems'=> ServiceItem::where('business_id', $business->id)->where('is_active', true)->orderBy('name')->get(),
            'customers'   => Customer::where('business_id', $business->id)->orderBy('name')->get(),
            'currency'    => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function update(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $business = $this->requireRequest($request, $serviceRequest);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->update($serviceRequest, $this->validated($request, $business));

        return redirect()->route('service.requests.show', $serviceRequest)->with('status', 'Request updated.');
    }

    public function markInProgress(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $business = $this->requireRequest($request, $serviceRequest);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->markInProgress($serviceRequest);

        return redirect()->route('service.requests.show', $serviceRequest)->with('status', 'Marked as in progress.');
    }

    public function markCompleted(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $business = $this->requireRequest($request, $serviceRequest);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->markCompleted($serviceRequest);

        return redirect()->route('service.requests.show', $serviceRequest)->with('status', 'Marked as completed.');
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $business = $this->requireRequest($request, $serviceRequest);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->cancel($serviceRequest);

        return redirect()->route('service.requests.show', $serviceRequest)->with('status', 'Request cancelled.');
    }

    public function destroy(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $business = $this->requireRequest($request, $serviceRequest);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->delete($serviceRequest);

        return redirect()->route('service.requests.index')->with('status', 'Request deleted.');
    }

    private function requireRequest(Request $request, ServiceRequest $sr): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        abort_unless($this->service->requestForBusiness($business, $sr) instanceof ServiceRequest, 404);

        return $business;
    }

    private function validated(Request $request, Business $business): array
    {
        return $request->validate([
            'service_item_id' => ['nullable', 'integer', Rule::exists('service_items', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'customer_id'     => ['nullable', 'integer', Rule::exists('pos_customers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'title'           => ['required', 'string', 'max:255'],
            'reference'       => ['nullable', 'string', 'max:120'],
            'notes'           => ['nullable', 'string', 'max:5000'],
            'scheduled_at'    => ['nullable', 'date'],
            'total_price'     => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function statusTabs(): array
    {
        return [
            'all'                              => 'All',
            ServiceRequest::STATUS_PENDING     => 'Pending',
            ServiceRequest::STATUS_IN_PROGRESS => 'In Progress',
            ServiceRequest::STATUS_COMPLETED   => 'Completed',
            ServiceRequest::STATUS_CANCELLED   => 'Cancelled',
        ];
    }
}
