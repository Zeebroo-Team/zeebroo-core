<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\HRManagement\Models\Employee;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrEmployeeListApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_employees')) {
            return response()->json(['data' => []]);
        }

        $employees = Employee::where('business_id', $business->id)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employment_type']);

        return response()->json([
            'data' => $employees->map(fn (Employee $e) => [
                'id'   => $e->id,
                'name' => $e->full_name,
                'type' => $e->employment_type ?? '',
            ])->values(),
        ]);
    }
}
