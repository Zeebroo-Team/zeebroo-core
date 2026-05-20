<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;

class PosBusinessesApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $businesses = Business::query()
            ->where('user_id', $user->id)
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
