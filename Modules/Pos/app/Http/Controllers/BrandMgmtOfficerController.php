<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdvertisingAgency\Models\Officer;
use Modules\AdvertisingAgency\Services\OfficerService;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;

class BrandMgmtOfficerController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(private readonly OfficerService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));

        return view('pos::brand-mgmt.officers.index', [
            'business' => $business,
            'officers' => $this->service->list($business, $search ?: null),
            'search'   => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        if (Officer::where('business_id', $business->id)->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'An officer with this email already exists.'])->withInput();
        }

        $this->service->create($business, $data);

        return redirect()->route('pos.brand-mgmt.officers.index')->with('status', 'Officer added.');
    }

    public function update(Request $request, Officer $officer): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $officer->business_id === (int) $business->id, 403);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:100'],
        ]);

        if (Officer::where('business_id', $business->id)->where('email', $data['email'])->where('id', '!=', $officer->id)->exists()) {
            return back()->withErrors(['email' => 'Another officer is already using this email address.'])->withInput();
        }

        $this->service->update($officer, $data);

        return redirect()->route('pos.brand-mgmt.officers.index')->with('status', 'Officer updated.');
    }

    public function destroy(Request $request, Officer $officer): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $officer->business_id === (int) $business->id, 403);

        $this->service->delete($officer);

        return redirect()->route('pos.brand-mgmt.officers.index')->with('status', 'Officer deleted.');
    }
}
