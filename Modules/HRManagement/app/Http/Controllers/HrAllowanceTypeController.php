<?php

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\AllowanceType;
use Modules\HRManagement\Services\AllowanceTypeService;
use Modules\HRManagement\Services\HrPayrollSettingsService;

class HrAllowanceTypeController extends Controller
{
    public function __construct(
        private readonly HrPayrollSettingsService $hrPayrollSettings,
        private readonly AllowanceTypeService $allowanceTypeService,
    ) {}

    public function index(Request $request): RedirectResponse|View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        return view('hrmanagement::allowance-types.index', [
            'business' => $business,
            'allowanceTypes' => $business->allowanceTypes()->withCount('employeeAllowances')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('hr_allowance_types', 'name')->where(
                    fn ($query) => $query->where('business_id', $business->id)
                ),
            ],
        ]);

        $this->allowanceTypeService->create($business, $validated['name']);

        return redirect()->route('hr.allowance-types.index')->with('status', __('Allowance type saved.'));
    }

    public function destroy(Request $request, AllowanceType $allowanceType): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_unless((int) $allowanceType->business_id === (int) $business->id, 403);

        if ($allowanceType->employeeAllowances()->exists()) {
            return redirect()->route('hr.allowance-types.index')->withErrors([
                'allowance_type' => __('Cannot delete an allowance type that is still assigned to employees.'),
            ]);
        }

        $allowanceType->delete();

        return redirect()->route('hr.allowance-types.index')->with('status', __('Allowance type removed.'));
    }
}
