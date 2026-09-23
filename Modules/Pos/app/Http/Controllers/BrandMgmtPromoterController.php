<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdvertisingAgency\Models\Promoter;
use Modules\AdvertisingAgency\Services\PromoterPositionService;
use Modules\AdvertisingAgency\Services\PromoterService;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;

class BrandMgmtPromoterController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly PromoterService $service,
        private readonly PromoterPositionService $positionService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));

        return view('pos::brand-mgmt.promoters.index', [
            'business'  => $business,
            'promoters' => $this->service->list($business, $search ?: null),
            'positions' => $this->positionService->list($business),
            'search'    => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->create($business, $this->validatePromoter($request));

        return redirect()->route('pos.brand-mgmt.promoters.index')->with('status', 'Promoter added.');
    }

    public function update(Request $request, Promoter $promoter): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $promoter->business_id === (int) $business->id, 403);

        $this->service->update($promoter, $this->validatePromoter($request));

        return redirect()->route('pos.brand-mgmt.promoters.index')->with('status', 'Promoter updated.');
    }

    public function destroy(Request $request, Promoter $promoter): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $promoter->business_id === (int) $business->id, 403);

        $this->service->delete($promoter);

        return redirect()->route('pos.brand-mgmt.promoters.index')->with('status', 'Promoter deleted.');
    }

    private function validatePromoter(Request $request): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'position'     => ['nullable', 'string', 'max:100'],
            'nic'          => ['nullable', 'string', 'max:50'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'bank_name'    => ['required', 'string', 'max:100'],
            'bank_branch'  => ['required', 'string', 'max:100'],
            'bank_account' => ['required', 'string', 'max:50'],
        ]);
    }
}
