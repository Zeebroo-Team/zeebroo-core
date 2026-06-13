<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Account\Models\Loan;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosLoanApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $loans = Loan::where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name', 'borrowed_amount']);

        return response()->json([
            'data' => $loans->map(fn (Loan $loan) => [
                'id'              => $loan->id,
                'name'            => $loan->name,
                'borrowed_amount' => (float) $loan->borrowed_amount,
            ])->values(),
        ]);
    }
}
