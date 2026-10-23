<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Services\DepartmentDetailService;
use Modules\HRManagement\Services\DepartmentService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrDepartmentListApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly DepartmentService $departmentService,
        private readonly DepartmentDetailService $detailService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_departments')) {
            return response()->json(['data' => []]);
        }

        $departments = Department::withCount('employees')
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $departments->map(fn (Department $d) => $this->format($d))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_departments')) {
            return response()->json(['message' => 'HR module is not set up.'], 422);
        }

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('hr_departments', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
        ]);

        $department = $this->departmentService->create($business, $validated['name']);
        $department->loadCount('employees');

        return response()->json(['data' => $this->format($department)], 201);
    }

    /** Department detail page: members, salary band fit and payroll totals. */
    public function show(Request $request, Department $department): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $department->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        return response()->json(['data' => $this->detailService->detail($business, $department)]);
    }

    public function updateSalaryRange(Request $request, Department $department): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $department->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        $validated = $request->validate([
            'salary_range_min' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'salary_range_max' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ]);

        if (isset($validated['salary_range_min'], $validated['salary_range_max'])
            && (float) $validated['salary_range_max'] < (float) $validated['salary_range_min']) {
            return response()->json(['message' => 'Maximum salary must be greater than or equal to the minimum.'], 422);
        }

        $this->departmentService->updateSalaryRange(
            $business,
            $department,
            isset($validated['salary_range_min']) ? (float) $validated['salary_range_min'] : null,
            isset($validated['salary_range_max']) ? (float) $validated['salary_range_max'] : null,
        );

        return response()->json(['data' => $this->detailService->detail($business, $department->refresh())]);
    }

    /** Head / co-head — both must belong to this department and be different people (same rules as the web app). */
    public function updateLeadership(Request $request, Department $department): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $department->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        $member = Rule::exists('hr_employees', 'id')->where(fn ($q) => $q
            ->where('business_id', $business->id)
            ->where('department_id', $department->id));

        $validated = $request->validate([
            'head_employee_id'    => ['nullable', 'integer', $member],
            'co_head_employee_id' => ['nullable', 'integer', $member],
        ], [
            'head_employee_id.exists'    => 'The head must be an employee of this department.',
            'co_head_employee_id.exists' => 'The co-head must be an employee of this department.',
        ]);

        $headId   = isset($validated['head_employee_id']) ? (int) $validated['head_employee_id'] : null;
        $coHeadId = isset($validated['co_head_employee_id']) ? (int) $validated['co_head_employee_id'] : null;

        if ($headId !== null && $headId === $coHeadId) {
            return response()->json(['message' => 'Department head and co-head must be different people.'], 422);
        }

        $this->departmentService->updateLeadership($business, $department, $headId, $coHeadId);

        return response()->json(['data' => $this->detailService->detail($business, $department->refresh())]);
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $department->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        if ($department->employees()->exists()) {
            return response()->json(['message' => 'Cannot delete a department that still has employees assigned.'], 422);
        }

        $department->delete();

        return response()->json(['message' => 'Department deleted.']);
    }

    private function format(Department $d): array
    {
        return [
            'id'               => $d->id,
            'name'             => $d->name,
            'employees_count'  => (int) ($d->employees_count ?? 0),
            'salary_range_min' => $d->salary_range_min !== null ? number_format((float) $d->salary_range_min, 2, '.', ',') : null,
            'salary_range_max' => $d->salary_range_max !== null ? number_format((float) $d->salary_range_max, 2, '.', ',') : null,
        ];
    }
}
