<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\ExpenseBreakdownService;

class PosExpensesBreakdownApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly ExpenseBreakdownService $breakdown) {}

    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $data = $request->validate([
            'period' => ['nullable', Rule::in(ExpenseBreakdownService::PERIODS)],
        ]);

        return response()->json([
            'data' => $this->breakdown->forBusiness($business, $data['period'] ?? ExpenseBreakdownService::PERIOD_MONTH),
        ]);
    }
}
