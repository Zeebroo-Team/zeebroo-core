<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdvertisingAgency\Models\Coordinator;
use Modules\AdvertisingAgency\Services\CoordinatorService;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;

class BrandMgmtCoordinatorController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(private readonly CoordinatorService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));

        return view('pos::brand-mgmt.coordinators.index', [
            'business'     => $business,
            'coordinators' => $this->service->list($business, $search ?: null),
            'search'       => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->create($business, $this->validateCoordinator($request));

        return redirect()->route('pos.brand-mgmt.coordinators.index')->with('status', 'Coordinator added.');
    }

    public function update(Request $request, Coordinator $coordinator): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $coordinator->business_id === (int) $business->id, 403);

        $this->service->update($coordinator, $this->validateCoordinator($request));

        return redirect()->route('pos.brand-mgmt.coordinators.index')->with('status', 'Coordinator updated.');
    }

    public function destroy(Request $request, Coordinator $coordinator): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $coordinator->business_id === (int) $business->id, 403);

        $this->service->delete($coordinator);

        return redirect()->route('pos.brand-mgmt.coordinators.index')->with('status', 'Coordinator deleted.');
    }

    private function validateCoordinator(Request $request): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'nic'          => ['nullable', 'string', 'max:50'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'status'       => ['required', 'string', 'in:active,inactive'],
            'bank_name'    => ['required', 'string', 'max:100'],
            'bank_branch'  => ['required', 'string', 'max:100'],
            'bank_account' => ['required', 'string', 'max:50'],
        ]);
    }
}
