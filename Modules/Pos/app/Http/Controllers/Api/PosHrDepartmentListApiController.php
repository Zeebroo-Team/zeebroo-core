<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Services\DepartmentService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrDepartmentListApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly DepartmentService $departmentService) {}

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
