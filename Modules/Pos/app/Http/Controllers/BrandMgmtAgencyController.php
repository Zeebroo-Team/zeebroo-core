<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdvertisingAgency\Models\Agency;
use Modules\AdvertisingAgency\Services\AgencyService;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;

class BrandMgmtAgencyController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(private readonly AgencyService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));

        return view('pos::brand-mgmt.agencies.index', [
            'business'  => $business,
            'agencies'  => $this->service->list($business, $search ?: null),
            'search'    => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->create($business, $this->validateAgency($request));

        return redirect()->route('pos.brand-mgmt.agencies.index')->with('status', 'Agency added.');
    }

    public function update(Request $request, Agency $agency): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $agency->business_id === (int) $business->id, 403);

        $this->service->update($agency, $this->validateAgency($request));

        return redirect()->route('pos.brand-mgmt.agencies.index')->with('status', 'Agency updated.');
    }

    public function destroy(Request $request, Agency $agency): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $agency->business_id === (int) $business->id, 403);

        $this->service->delete($agency);

        return redirect()->route('pos.brand-mgmt.agencies.index')->with('status', 'Agency deleted.');
    }

    private function validateAgency(Request $request): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'email'          => ['nullable', 'email', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'address'        => ['nullable', 'string', 'max:300'],
            'status'         => ['required', 'string', 'in:active,inactive'],
        ]);
    }
}
