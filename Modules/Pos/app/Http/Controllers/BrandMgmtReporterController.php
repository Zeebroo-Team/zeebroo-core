<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdvertisingAgency\Models\Reporter;
use Modules\AdvertisingAgency\Services\ReporterService;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;

class BrandMgmtReporterController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(private readonly ReporterService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));

        return view('pos::brand-mgmt.reporters.index', [
            'business'  => $business,
            'reporters' => $this->service->list($business, $search ?: null),
            'search'    => $search,
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

        if (Reporter::where('business_id', $business->id)->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'A reporter with this email already exists.'])->withInput();
        }

        $this->service->create($business, $data);

        return redirect()->route('pos.brand-mgmt.reporters.index')->with('status', 'Reporter added.');
    }

    public function update(Request $request, Reporter $reporter): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $reporter->business_id === (int) $business->id, 403);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:100'],
        ]);

        if (Reporter::where('business_id', $business->id)->where('email', $data['email'])->where('id', '!=', $reporter->id)->exists()) {
            return back()->withErrors(['email' => 'Another reporter is already using this email address.'])->withInput();
        }

        $this->service->update($reporter, $data);

        return redirect()->route('pos.brand-mgmt.reporters.index')->with('status', 'Reporter updated.');
    }

    public function destroy(Request $request, Reporter $reporter): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $reporter->business_id === (int) $business->id, 403);

        $this->service->delete($reporter);

        return redirect()->route('pos.brand-mgmt.reporters.index')->with('status', 'Reporter deleted.');
    }
}
