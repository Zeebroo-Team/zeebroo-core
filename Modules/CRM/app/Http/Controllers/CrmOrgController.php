<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\CrmPermissionService;

/** CRM settings: departments → divisions, department pipeline and department settings. Admin only. */
class CrmOrgController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly CrmOrgService $org,
        private readonly CrmPermissionService $permissions,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireAdmin($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('crm::settings.org', [
            'business'    => $business,
            'departments' => $this->org->departments($business),
            'pipelines'   => Project::query()->where('business_id', $business->id)->orderBy('name')->get(['id', 'name', 'department_id']),
            'users'       => $this->org->businessUsers($business),
        ]);
    }

    public function storeDivision(Request $request, int $department): RedirectResponse
    {
        $business = $this->requireAdmin($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $dept = $this->org->departmentForBusiness($business, $department);

        return $this->attempt(fn () => $this->org->createDivision($business, $dept, $this->validatedDivision($request)), 'Division added.');
    }

    public function updateDivision(Request $request, int $division): RedirectResponse
    {
        $business = $this->requireAdmin($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $row = $this->org->divisionForBusiness($business, $division);

        return $this->attempt(fn () => $this->org->updateDivision($business, $row, $this->validatedDivision($request)), 'Division updated.');
    }

    public function destroyDivision(Request $request, int $division): RedirectResponse
    {
        $business = $this->requireAdmin($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->org->deleteDivision($this->org->divisionForBusiness($business, $division));

        return redirect()->route('crm.org.index')->with('status', 'Division deleted.');
    }

    public function updateSettings(Request $request, int $department): RedirectResponse
    {
        $business = $this->requireAdmin($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $dept      = $this->org->departmentForBusiness($business, $department);
        $validated = $request->validate([
            'is_marketing_intake'             => ['nullable', 'boolean'],
            'discount_approval_limit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Comma-separated in the form, e.g. "2, 5"
            'followup_days'                   => ['nullable', 'string', 'max:100', 'regex:/^[\d\s,]*$/'],
        ]);

        $this->org->saveSettings($business, $dept, $validated);

        return redirect()->route('crm.org.index')->with('status', 'Settings saved for ' . $dept->name . '.');
    }

    public function linkPipeline(Request $request, int $department): RedirectResponse
    {
        $business = $this->requireAdmin($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $dept      = $this->org->departmentForBusiness($business, $department);
        $validated = $request->validate(['project_id' => ['nullable', 'integer']]);

        return $this->attempt(
            fn () => $this->org->linkPipeline($business, $dept, isset($validated['project_id']) ? (int) $validated['project_id'] : null),
            'Pipeline linked to ' . $dept->name . '.',
        );
    }

    public function unlinkPipeline(Request $request, int $department): RedirectResponse
    {
        $business = $this->requireAdmin($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $dept = $this->org->departmentForBusiness($business, $department);
        $this->org->unlinkPipeline($business, $dept);

        return redirect()->route('crm.org.index')->with('status', 'Pipeline unlinked from ' . $dept->name . '.');
    }

    private function requireAdmin(Request $request): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->permissions->isAdmin($business, (int) $request->user()->id), 403);

        return $business;
    }

    private function validatedDivision(Request $request): array
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'head_user_id' => ['nullable', 'integer'],
            'is_active'    => ['nullable', 'boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
        ]);
        // Unticked checkbox sends nothing
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    /** Run a service call, sending its validation errors back to the settings page. */
    private function attempt(callable $action, string $status): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return redirect()->route('crm.org.index')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('crm.org.index')->with('status', $status);
    }
}
