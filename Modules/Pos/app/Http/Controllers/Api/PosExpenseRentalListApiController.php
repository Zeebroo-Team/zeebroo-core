<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Account\Models\Rental;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosExpenseRentalListApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('rentals')) {
            return response()->json(['data' => []]);
        }

        $rentals = Rental::where('business_id', $business->id)
            ->orderBy('property_type')
            ->get(['id', 'property_type', 'purpose', 'recurring_cost']);

        return response()->json([
            'data' => $rentals->map(fn (Rental $r) => [
                'id'     => $r->id,
                'name'   => $r->property_type . ($r->purpose ? ' · ' . $r->purpose : ''),
                'amount' => (float) $r->recurring_cost,
            ])->values(),
        ]);
    }
}
