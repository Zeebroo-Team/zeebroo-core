<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Modification\Models\Modification;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosExpenseModificationListApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('modifications')) {
            return response()->json(['data' => []]);
        }

        $mods = Modification::where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name', 'estimated_cost']);

        return response()->json([
            'data' => $mods->map(fn (Modification $m) => [
                'id'     => $m->id,
                'name'   => $m->name,
                'amount' => (float) ($m->estimated_cost ?? 0),
            ])->values(),
        ]);
    }
}
