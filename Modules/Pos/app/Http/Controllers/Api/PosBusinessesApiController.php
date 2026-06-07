<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;

class PosBusinessesApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Businesses owned by this user + businesses where they are an employee
        $ownedIds = Business::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        $employeeBusinessIds = Employee::query()
            ->where('user_id', $user->id)
            ->whereNotNull('user_id')
            ->pluck('business_id');

        $allIds = $ownedIds->merge($employeeBusinessIds)->unique()->values();

        $businesses = Business::query()
            ->whereIn('id', $allIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $businesses->map(static fn (Business $business) => [
                'id' => (int) $business->id,
                'name' => $business->name,
            ])->values()->all(),
        ]);
    }
}
