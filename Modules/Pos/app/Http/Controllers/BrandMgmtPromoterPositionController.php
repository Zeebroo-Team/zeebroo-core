<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdvertisingAgency\Models\PromoterPosition;
use Modules\AdvertisingAgency\Services\PromoterPositionService;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;

class BrandMgmtPromoterPositionController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(private readonly PromoterPositionService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));
        $positions = $this->service->list($business, $search ?: null);

        return view('pos::brand-mgmt.promoter-positions.index', [
            'business'  => $business,
            'positions' => $positions,
            'search'    => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->create($business, $data);

        return redirect()->route('pos.brand-mgmt.promoter-positions.index')->with('status', 'Position added.');
    }

    public function update(Request $request, int $position): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $model = PromoterPosition::where('business_id', $business->id)->findOrFail($position);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->update($model, $data);

        return redirect()->route('pos.brand-mgmt.promoter-positions.index')->with('status', 'Position updated.');
    }

    public function destroy(Request $request, int $position): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $model = PromoterPosition::where('business_id', $business->id)->findOrFail($position);
        $this->service->delete($model);

        return redirect()->route('pos.brand-mgmt.promoter-positions.index')->with('status', 'Position deleted.');
    }

    public function quickStore(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['message' => 'Select or create a business first.'], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $position = $this->service->create($business, $data);

        return response()->json(['id' => $position->id, 'name' => $position->name]);
    }
}
