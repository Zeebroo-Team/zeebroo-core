<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\HRManagement\Models\AllowanceType;
use Modules\HRManagement\Services\AllowanceTypeService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrAllowanceTypeApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly AllowanceTypeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_allowance_types')) {
            return response()->json(['data' => []]);
        }

        $types = $business->allowanceTypes()
            ->withCount('employeeAllowances')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $types->map(fn (AllowanceType $t) => $this->format($t))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_allowance_types')) {
            return response()->json(['message' => 'HR module is not set up yet.'], 422);
        }

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('hr_allowance_types', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
        ]);

        $type = $this->service->create($business, $validated['name']);
        $type->loadCount('employeeAllowances');

        return response()->json(['data' => $this->format($type)], 201);
    }

    public function destroy(Request $request, AllowanceType $allowanceType): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $allowanceType->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($allowanceType->employeeAllowances()->exists()) {
            return response()->json([
                'message' => 'Cannot delete an allowance type that is still assigned to employees.',
            ], 422);
        }

        $allowanceType->delete();

        return response()->json(['message' => 'Allowance type deleted.']);
    }

    private function format(AllowanceType $t): array
    {
        return [
            'id'             => $t->id,
            'name'           => $t->name,
            'sort_order'     => (int) $t->sort_order,
            'employees_count'=> (int) ($t->employee_allowances_count ?? 0),
        ];
    }
}
