<?php

declare(strict_types=1);

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\HrComplaint;
use Modules\HRManagement\Services\HrPayrollSettingsService;

class HrComplaintController extends Controller
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

        abort_unless(Business::canAccess($request->user(), $business), 403);

        $validated = $request->validate([
            'complaint_employee_id' => [
                'required', 'integer',
                Rule::exists('hr_employees', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'complaint_subject' => ['required', 'string', 'max:255'],
            'complaint_body' => ['required', 'string', 'max:10000'],
        ]);

        HrComplaint::query()->create([
            'business_id' => $business->id,
            'employee_id' => (int) $validated['complaint_employee_id'],
            'subject' => $validated['complaint_subject'],
            'body' => $validated['complaint_body'],
            'status' => HrComplaint::STATUS_OPEN,
            'recorded_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('hr.index')->with('status', __('HR complaint logged.'));
    }

    public function updateStatus(Request $request, HrComplaint $hrComplaint): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);
        abort_unless((int) $hrComplaint->business_id === (int) $business->id, 404);
        abort_unless($hrComplaint->isOpen(), 404);

        $validated = $request->validate([
            'complaint_status' => ['required', 'string', Rule::in([HrComplaint::STATUS_RESOLVED, HrComplaint::STATUS_DISMISSED])],
        ]);

        $hrComplaint->update(['status' => $validated['complaint_status']]);

        $msg = $validated['complaint_status'] === HrComplaint::STATUS_RESOLVED
            ? __('Complaint marked resolved.')
            : __('Complaint dismissed.');

        return redirect()->route('hr.index')->with('status', $msg);
    }
}
