<?php

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Account\Models\Bill;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Services\DepartmentCostCenterService;
use Modules\HRManagement\Services\DepartmentEmployeeGrowthChartService;
use Modules\HRManagement\Services\DepartmentService;
use Modules\HRManagement\Services\HrPayrollSettingsService;

class HrDepartmentController extends Controller
{
    public function __construct(
        private readonly HrPayrollSettingsService $hrPayrollSettings,
        private readonly DepartmentService $departmentService,
        private readonly DepartmentEmployeeGrowthChartService $departmentGrowthChartService,
        private readonly DepartmentCostCenterService $departmentCostCenterService,
    ) {}

    public function growthOverview(Request $request): RedirectResponse|View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        $chart = $this->departmentGrowthChartService->build($business);

        return view('hrmanagement::departments.growth-overview', [
            'business' => $business,
            'chart' => $chart,
        ]);
    }

    public function index(Request $request): RedirectResponse|View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        $costCenterReport = $this->departmentCostCenterService->build($business);

        return view('hrmanagement::departments.index', [
            'business' => $business,
            'departments' => $business->departments()->withCount('employees')->orderBy('name')->orderBy('id')->get(),
            'costCenterReport' => $costCenterReport,
        ]);
    }

    public function show(Request $request, Department $department): RedirectResponse|View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_unless((int) $department->business_id === (int) $business->id, 404);

        $members = $department->employees()->with('jobTitle')->orderBy('full_name')->orderBy('id')->get();
        $recentJoiners = $department->employees()->with('jobTitle')->orderByDesc('date_of_joining')->orderByDesc('id')->limit(10)->get();
        $employmentCounts = $members->countBy('employment_type');

        /** @var array<string, int> */
        $employmentBreakdown = [
            Employee::EMPLOYMENT_PART_TIME => (int) ($employmentCounts[Employee::EMPLOYMENT_PART_TIME] ?? 0),
            Employee::EMPLOYMENT_FULL_TIME => (int) ($employmentCounts[Employee::EMPLOYMENT_FULL_TIME] ?? 0),
            Employee::EMPLOYMENT_CONTRACT => (int) ($employmentCounts[Employee::EMPLOYMENT_CONTRACT] ?? 0),
        ];

        $assignable = Employee::query()
            ->where('business_id', $business->id)
            ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', '!=', $department->id))
            ->with(['jobTitle', 'department'])
            ->orderBy('full_name')
            ->orderBy('id')
            ->get();

        $employmentTypeLabels = [
            Employee::EMPLOYMENT_FULL_TIME => __('Full-time'),
            Employee::EMPLOYMENT_PART_TIME => __('Part-time'),
            Employee::EMPLOYMENT_CONTRACT => __('Contract'),
        ];

        /** @var array<string, array{icon_class: string, description: string}> */
        $employmentCardMeta = [
            Employee::EMPLOYMENT_PART_TIME => [
                'icon_class' => 'fa-solid fa-clock',
                'description' => __('Fewer weekly hours than a standard full-time role.'),
            ],
            Employee::EMPLOYMENT_FULL_TIME => [
                'icon_class' => 'fa-solid fa-briefcase',
                'description' => __('Regular ongoing employment at full-time hours.'),
            ],
            Employee::EMPLOYMENT_CONTRACT => [
                'icon_class' => 'fa-solid fa-file-contract',
                'description' => __('Fixed-term or agreement-based engagement.'),
            ],
        ];

        $showDepartmentBillsTab = Schema::hasTable('bills') && Schema::hasColumn('bills', 'department_id');

        $departmentBills = collect();
        if ($showDepartmentBillsTab) {
            $departmentBills = Bill::query()
                ->where('business_id', $business->id)
                ->where('department_id', $department->id)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();
        }

        $requestedTab = (string) $request->query('tab', '');
        $sessionErrors = $request->session()->get('errors');
        if ($sessionErrors !== null && $sessionErrors->any()) {
            $activeTab = 'management';
        } elseif ($requestedTab === 'management') {
            $activeTab = 'management';
        } elseif ($requestedTab === 'bills' && $showDepartmentBillsTab) {
            $activeTab = 'bills';
        } else {
            $activeTab = 'overview';
        }

        $departmentGrowthChart = $this->departmentGrowthChartService->buildForDepartment($business, $department);

        $costCenterReport = $this->departmentCostCenterService->build($business);
        $departmentCostCenterRow = null;
        if (($costCenterReport['available'] ?? false) === true) {
            foreach ($costCenterReport['rows'] as $row) {
                if ((int) $row['department']->id === (int) $department->id) {
                    $departmentCostCenterRow = $row;
                    break;
                }
            }
        }

        $department->loadMissing(['headEmployee.jobTitle', 'coHeadEmployee.jobTitle']);

        $headId = old('head_employee_id', $department->head_employee_id);
        $headEmployeeForField = null;
        if ($headId) {
            $hid = (int) $headId;
            $headEmployeeForField = (int) $department->head_employee_id === $hid
                ? $department->headEmployee
                : Employee::query()->where('business_id', $business->id)->with('jobTitle')->find($hid);
        }

        $coHeadId = old('co_head_employee_id', $department->co_head_employee_id);
        $coHeadEmployeeForField = null;
        if ($coHeadId) {
            $cid = (int) $coHeadId;
            $coHeadEmployeeForField = (int) $department->co_head_employee_id === $cid
                ? $department->coHeadEmployee
                : Employee::query()->where('business_id', $business->id)->with('jobTitle')->find($cid);
        }

        return view('hrmanagement::departments.show', [
            'business' => $business,
            'department' => $department,
            'departmentSalaryCurrency' => (string) (get_settings('business.currency', '', $business) ?: ''),
            'members' => $members,
            'recentJoiners' => $recentJoiners,
            'employmentBreakdown' => $employmentBreakdown,
            'assignableEmployees' => $assignable,
            'employmentTypeLabels' => $employmentTypeLabels,
            'employmentCardMeta' => $employmentCardMeta,
            'activeTab' => $activeTab,
            'showDepartmentBillsTab' => $showDepartmentBillsTab,
            'departmentBills' => $departmentBills,
            'departmentBillCurrency' => (string) (get_settings('business.currency', '', $business) ?: ''),
            'departmentGrowthChart' => $departmentGrowthChart,
            'costCenterReport' => $costCenterReport,
            'departmentCostCenterRow' => $departmentCostCenterRow,
            'leadershipHeadLabel' => $headEmployeeForField
                ? $headEmployeeForField->full_name.($headEmployeeForField->jobTitle ? ' · '.$headEmployeeForField->jobTitle->name : '')
                : '',
            'leadershipCoHeadLabel' => $coHeadEmployeeForField
                ? $coHeadEmployeeForField->full_name.($coHeadEmployeeForField->jobTitle ? ' · '.$coHeadEmployeeForField->jobTitle->name : '')
                : '',
        ]);
    }

    public function searchDepartmentEmployees(Request $request, Department $department): JsonResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            abort(403);
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_unless((int) $department->business_id === (int) $business->id, 404);

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $limit = min(max((int) $request->query('limit', 20), 1), 50);

        $employees = Employee::query()
            ->where('business_id', $business->id)
            ->where('department_id', $department->id)
            ->with('jobTitle')
            ->where(function ($q) use ($query): void {
                $q->where('full_name', 'like', '%'.$query.'%')
                    ->orWhere('employee_id', 'like', '%'.$query.'%');
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get();

        return response()->json([
            'results' => $employees->map(static fn (Employee $e): array => [
                'id' => $e->id,
                'text' => $e->full_name.($e->jobTitle ? ' · '.$e->jobTitle->name : ''),
            ])->values()->all(),
        ]);
    }

    public function updateLeadership(Request $request, Department $department): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_unless((int) $department->business_id === (int) $business->id, 404);

        $request->merge([
            'head_employee_id' => $request->filled('head_employee_id') ? (int) $request->input('head_employee_id') : null,
            'co_head_employee_id' => $request->filled('co_head_employee_id') ? (int) $request->input('co_head_employee_id') : null,
        ]);

        $validated = $request->validate([
            'head_employee_id' => [
                'nullable',
                'integer',
                Rule::exists('hr_employees', 'id')->where(static function ($q) use ($business, $department): void {
                    $q->where('business_id', $business->id)->where('department_id', $department->id);
                }),
            ],
            'co_head_employee_id' => [
                'nullable',
                'integer',
                Rule::exists('hr_employees', 'id')->where(static function ($q) use ($business, $department): void {
                    $q->where('business_id', $business->id)->where('department_id', $department->id);
                }),
            ],
        ]);

        if (
            isset($validated['head_employee_id'], $validated['co_head_employee_id'])
            && (int) $validated['head_employee_id'] === (int) $validated['co_head_employee_id']
        ) {
            return redirect()->route('hr.departments.show', ['department' => $department, 'tab' => 'management'])
                ->withErrors(['co_head_employee_id' => __('Department head and co-head must be different people.')])
                ->withInput();
        }

        $this->departmentService->updateLeadership(
            $business,
            $department,
            $validated['head_employee_id'] ?? null,
            $validated['co_head_employee_id'] ?? null,
        );

        return redirect()->route('hr.departments.show', ['department' => $department, 'tab' => 'management'])
            ->with('status', __('Department leadership updated.'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_unless((int) $department->business_id === (int) $business->id, 404);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('hr_departments', 'name')
                    ->where(fn ($query) => $query->where('business_id', $business->id))
                    ->ignore($department->id),
            ],
        ]);

        $this->departmentService->rename($business, $department, $validated['name']);

        return redirect()->route('hr.departments.show', ['department' => $department, 'tab' => 'management'])
            ->with('status', __('Department name updated.'));
    }

    public function updateDetails(Request $request, Department $department): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_unless((int) $department->business_id === (int) $business->id, 404);

        $request->merge([
            'salary_range_min' => $request->filled('salary_range_min') ? $request->input('salary_range_min') : null,
            'salary_range_max' => $request->filled('salary_range_max') ? $request->input('salary_range_max') : null,
        ]);

        $validated = $request->validate([
            'salary_range_min' => ['nullable', 'numeric', 'min:0'],
            'salary_range_max' => ['nullable', 'numeric', 'min:0'],
        ]);

        $min = $validated['salary_range_min'] !== null ? (float) $validated['salary_range_min'] : null;
        $max = $validated['salary_range_max'] !== null ? (float) $validated['salary_range_max'] : null;

        if ($min !== null && $max !== null && $max < $min) {
            throw ValidationException::withMessages([
                'salary_range_max' => __('Maximum must be greater than or equal to minimum.'),
            ]);
        }

        $this->departmentService->updateSalaryRange($business, $department, $min, $max);

        return redirect()->route('hr.departments.show', ['department' => $department, 'tab' => 'management'])
            ->with('status', __('Department details updated.'));
    }

    public function attachMembers(Request $request, Department $department): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_unless((int) $department->business_id === (int) $business->id, 404);

        $validated = $request->validate([
            'attach_employee_ids' => ['required', 'array', 'min:1'],
            'attach_employee_ids.*' => ['integer', 'distinct', Rule::exists('hr_employees', 'id')->where(static function ($q) use ($business, $department): void {
                $q->where('business_id', $business->id)
                    ->where(fn ($sq) => $sq->whereNull('department_id')->orWhere('department_id', '!=', $department->id));
            })],
        ]);

        $updated = $this->departmentService->attachEmployees($business, $department, $validated['attach_employee_ids']);

        if ($updated === 0) {
            return redirect()->route('hr.departments.show', ['department' => $department, 'tab' => 'management'])
                ->withErrors(['attach_employee_ids' => __('No eligible employees could be assigned. They may already be in this department.')]);
        }

        return redirect()->route('hr.departments.show', ['department' => $department, 'tab' => 'management'])
            ->with('status', __('Assigned :count employee(s).', ['count' => $updated]));
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
                Rule::unique('hr_departments', 'name')->where(
                    fn ($query) => $query->where('business_id', $business->id)
                ),
            ],
        ]);

        $this->departmentService->create($business, $validated['name']);

        return redirect()->route('hr.departments.index')->with('status', __('Department saved.'));
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_unless((int) $department->business_id === (int) $business->id, 403);

        if ($department->employees()->exists()) {
            return redirect()->route('hr.departments.index')->withErrors([
                'department' => __('Cannot delete a department that still has employees assigned.'),
            ]);
        }

        $department->delete();

        return redirect()->route('hr.departments.index')->with('status', __('Department deleted.'));
    }
}
