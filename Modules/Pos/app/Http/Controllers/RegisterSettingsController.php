<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Models\PosCashier;
use Modules\Pos\Models\PosCounter;

class RegisterSettingsController extends Controller
{
    use ResolvesPosBusiness;

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $counters = PosCounter::where('business_id', $business->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $cashiers = PosCashier::where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'is_active', 'created_at']);

        return view('pos::register-settings.index', [
            'business' => $business,
            'counters' => $counters,
            'cashiers' => $cashiers,
            'branches' => $business->multiWarehouseBranchEnabled() ? $business->branches()->orderBy('name')->get() : collect(),
        ]);
    }

    public function storeCounter(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'branch_id'  => [
                'nullable', 'integer', 'min:1',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
        ]);

        PosCounter::create([
            'business_id' => $business->id,
            'branch_id'   => $validated['branch_id'] ?? null,
            'name'        => trim($validated['name']),
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'is_active'   => true,
        ]);

        return back()->with('status', 'Counter created.');
    }

    public function updateCounter(Request $request, PosCounter $counter): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $counter->business_id === (int) $business->id, 404);

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['nullable', 'boolean'],
            'branch_id'  => [
                'nullable', 'integer', 'min:1',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
        ]);

        $counter->update([
            'name'       => trim($validated['name']),
            'sort_order' => (int) ($validated['sort_order'] ?? $counter->sort_order),
            'is_active'  => $request->boolean('is_active'),
            'branch_id'  => $validated['branch_id'] ?? null,
        ]);

        return back()->with('status', 'Counter updated.');
    }

    public function destroyCounter(Request $request, PosCounter $counter): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $counter->business_id === (int) $business->id, 404);

        $counter->delete();

        return back()->with('status', 'Counter deleted.');
    }

    public function storeCashier(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'max:80',
                Rule::unique('pos_cashiers')->where('business_id', $business->id),
            ],
            'password' => ['required', 'string', 'min:4', 'max:255'],
        ]);

        PosCashier::create([
            'business_id' => $business->id,
            'name'        => trim($validated['name']),
            'username'    => trim($validated['username']),
            'password'    => $validated['password'],
            'is_active'   => true,
        ]);

        return back()->with('status', 'Cashier created.');
    }

    public function updateCashier(Request $request, PosCashier $cashier): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $cashier->business_id === (int) $business->id, 404);

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'username'  => [
                'required', 'string', 'max:80',
                Rule::unique('pos_cashiers')->where('business_id', $business->id)->ignore($cashier->id),
            ],
            'password'  => ['nullable', 'string', 'min:4', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $cashier->name = trim($validated['name']);
        $cashier->username = trim($validated['username']);
        if (!empty($validated['password'])) {
            $cashier->password = $validated['password'];
        }
        $cashier->is_active = $request->boolean('is_active');
        $cashier->save();

        return back()->with('status', 'Cashier updated.');
    }

    public function destroyCashier(Request $request, PosCashier $cashier): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $cashier->business_id === (int) $business->id, 404);

        $cashier->delete();

        return back()->with('status', 'Cashier deleted.');
    }
}
