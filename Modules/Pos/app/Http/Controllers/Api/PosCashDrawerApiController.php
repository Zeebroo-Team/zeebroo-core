<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosCashDrawerService;

class PosCashDrawerApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PosCashDrawerService $drawer,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        return response()->json(['data' => $this->drawer->todayStatus($business)]);
    }

    public function open(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $opening = $this->drawer->setOpening(
            $business,
            (float) $validated['opening_float'],
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'Opening cash recorded.',
            'data'    => $this->drawer->todayStatus($business),
        ], 200);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'note'   => ['nullable', 'string', 'max:255'],
        ]);

        $this->drawer->addWithdrawal(
            $business,
            (float) $validated['amount'],
            $validated['note'] ?? null,
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'Withdrawal recorded.',
            'data'    => $this->drawer->todayStatus($business),
        ]);
    }
}
