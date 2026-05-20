<?php

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\HrBusinessHoliday;
use Modules\HRManagement\Services\HrPayrollSettingsService;

class HrBusinessHolidayController extends Controller
{
    public function __construct(
        private readonly HrPayrollSettingsService $hrPayrollSettings,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'holiday_date' => [
                'required', 'date',
                Rule::unique('hr_business_holidays', 'holiday_date')->where(
                    fn ($q) => $q->where('business_id', $business->id)
                ),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $business->hrHolidays()->create([
            'name' => $validated['name'],
            'holiday_date' => $validated['holiday_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('settings.business', ['tab' => 'hr'])
            ->with('status', __('Holiday saved.'));
    }

    public function destroy(Request $request, HrBusinessHoliday $holiday): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_unless((int) $holiday->business_id === (int) $business->id, 404);

        $holiday->delete();

        return redirect()->route('settings.business', ['tab' => 'hr'])
            ->with('status', __('Holiday removed.'));
    }
}
