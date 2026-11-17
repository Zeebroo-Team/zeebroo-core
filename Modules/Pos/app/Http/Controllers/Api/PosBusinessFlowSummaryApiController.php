<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\BusinessFlowSummaryService;

class PosBusinessFlowSummaryApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    /** Home → Business Flow node summaries. Logic lives in BusinessFlowSummaryService. */
    public function show(Request $request, BusinessFlowSummaryService $summary): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        return response()->json(['data' => $summary->build($business)]);
    }
}
