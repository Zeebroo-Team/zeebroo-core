<?php

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;
use Modules\Business\Services\BranchService;

class BranchController extends Controller
{
    public function __construct(private readonly BranchService $branchService)
    {
    }

    public function singleLocationSetup(Request $request): View|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        if ($business->multiWarehouseBranchEnabled()) {
            return redirect()->route('business.branches.index');
        }

        if ($business->branches()->exists()) {
            return redirect()->route('dashboard');
        }

        return view('business::branches.setup-single', [
            'business' => $business,
        ]);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        if (!$business->multiWarehouseBranchEnabled()) {
            return redirect()->route('dashboard')->withErrors(['branch' => 'Branch management is available when multi-location mode is enabled.']);
        }

        return view('business::branches.index', [
            'business' => $business,
            'branches' => $this->branchService->listForBusiness($business),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'No business selected.']);
        }

        $singleSetup = $request->boolean('single_location_setup');

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        if ($singleSetup) {
            if ($business->multiWarehouseBranchEnabled() || $business->branches()->exists()) {
                return redirect()->route('dashboard')->withErrors(['branch' => 'Location is already configured.']);
            }
        } elseif (!$business->multiWarehouseBranchEnabled() && $business->branches()->exists()) {
            return redirect()->route('dashboard')->withErrors(['branch' => 'Single-location mode supports one branch. Enable multi-location in Business Settings for more sites.']);
        }

        $data = $this->validatedBranch($request);

        $this->branchService->create($business, $data);

        if ($singleSetup) {
            return redirect()->route('dashboard')->with('status', 'Your location was saved. You can continue from Overview.');
        }

        return redirect()->route('business.branches.index')->with('status', 'Branch added.');
    }

    public function edit(Request $request, Branch $branch): View|RedirectResponse
    {
        $this->authorizeBranchForUser($request, $branch);

        if (!$branch->business->multiWarehouseBranchEnabled()) {
            return redirect()->route('dashboard');
        }

        return view('business::branches.edit', [
            'business' => $branch->business,
            'branch' => $branch,
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranchForUser($request, $branch);

        if (!$branch->business->multiWarehouseBranchEnabled()) {
            return redirect()->route('dashboard');
        }

        $data = $this->validatedBranch($request);

        $this->branchService->update($branch, $data);

        return redirect()->route('business.branches.index')->with('status', 'Branch updated.');
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranchForUser($request, $branch);

        if (!$branch->business->multiWarehouseBranchEnabled()) {
            return redirect()->route('dashboard')->withErrors([
                'branch' => 'This location cannot be deleted while using single-location mode.',
            ]);
        }

        $this->branchService->delete($branch);

        return redirect()->route('business.branches.index')->with('status', 'Branch removed.');
    }

    private function authorizeBranchForUser(Request $request, Branch $branch): void
    {
        $business = Business::currentForNavbar($request->user());
        abort_unless($business instanceof Business && (int) $branch->business_id === (int) $business->id, 403);
        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
    }

    /**
     * @return array{name: string, description: ?string, address: ?string, phone: ?string, email: ?string, is_active: bool}
     */
    private function validatedBranch(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
