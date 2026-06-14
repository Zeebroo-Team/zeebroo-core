<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Account\Models\Account;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosAccountApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $accounts = Account::where('business_id', $business->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'bank_name', 'current_balance']);

        return response()->json([
            'data' => $accounts->map(fn (Account $a) => [
                'id'              => $a->id,
                'account_name'    => $a->account_name,
                'bank_name'       => $a->bank_name,
                'current_balance' => (float) $a->current_balance,
            ])->values(),
        ]);
    }
}
