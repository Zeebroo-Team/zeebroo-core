<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Account\Models\Bank;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosBankApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $this->businessOrAbort($request);

        $banks = Bank::orderBy('name')->get(['id', 'name', 'code']);

        return response()->json([
            'data' => $banks->map(fn (Bank $bank) => [
                'id'   => $bank->id,
                'name' => $bank->name,
                'code' => $bank->code,
            ])->values(),
        ]);
    }
}
