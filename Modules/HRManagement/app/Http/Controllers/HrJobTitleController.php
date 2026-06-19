<?php

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\JobTitle;
use Modules\HRManagement\Services\HrPayrollSettingsService;
use Modules\HRManagement\Services\JobTitleService;

class HrJobTitleController extends Controller
{
    public function __construct(
        private readonly HrPayrollSettingsService $hrPayrollSettings,
        private readonly JobTitleService $jobTitleService,
    ) {}

    public function index(Request $request): RedirectResponse|View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        return view('hrmanagement::job-titles.index', [
            'business' => $business,
            'jobTitles' => $business->jobTitles()->withCount('employees')->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('hr_job_titles', 'name')->where(
                    fn ($query) => $query->where('business_id', $business->id)
                ),
            ],
        ]);

        $this->jobTitleService->create($business, $validated['name']);

        return redirect()->route('hr.job-titles.index')->with('status', __('Designation saved.'));
    }

    public function show(Request $request, JobTitle $jobTitle): RedirectResponse|View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);
        abort_unless((int) $jobTitle->business_id === (int) $business->id, 403);

        $activeTab = (string) $request->query('tab', 'overview');
        if (! in_array($activeTab, ['overview', 'employees'], true)) {
            $activeTab = 'overview';
        }

        $employees = $jobTitle->employees()
            ->with(['department'])
            ->orderBy('full_name')
            ->get();

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('hrmanagement::job-titles.show', [
            'business'   => $business,
            'jobTitle'   => $jobTitle,
            'employees'  => $employees,
            'currency'   => $currency,
            'activeTab'  => $activeTab,
        ]);
    }

    public function updatePortalFeatures(Request $request, JobTitle $jobTitle): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);
        abort_unless((int) $jobTitle->business_id === (int) $business->id, 403);

        $selected = array_values(array_intersect(
            (array) $request->input('portal_features', []),
            JobTitle::PORTAL_FEATURES
        ));

        // Store null when all features are selected (= default "all enabled" state)
        $jobTitle->update([
            'portal_features' => count($selected) === count(JobTitle::PORTAL_FEATURES) ? null : $selected,
        ]);

        return redirect()->route('hr.job-titles.show', $jobTitle)
            ->with('status', __('Portal access updated.'));
    }

    public function update(Request $request, JobTitle $jobTitle): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);
        abort_unless((int) $jobTitle->business_id === (int) $business->id, 403);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('hr_job_titles', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id))
                    ->ignore($jobTitle->id),
            ],
        ]);

        $jobTitle->update(['name' => trim($validated['name'])]);

        return redirect()->route('hr.job-titles.show', $jobTitle)->with('status', 'Designation renamed.');
    }

    public function destroy(Request $request, JobTitle $jobTitle): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);
        abort_unless((int) $jobTitle->business_id === (int) $business->id, 403);

        if ($jobTitle->employees()->exists()) {
            return redirect()->route('hr.job-titles.index')->withErrors([
                'designation' => __('Cannot delete a designation that still has employees assigned.'),
            ]);
        }

        $jobTitle->delete();

        return redirect()->route('hr.job-titles.index')->with('status', __('Designation deleted.'));
    }
}
