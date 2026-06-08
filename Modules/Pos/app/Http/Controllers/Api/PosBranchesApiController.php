<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosBranchesApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __invoke(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $branchPosSeparate = (bool) get_settings('business.branch_pos_separate', false, $business);

        $branches = $branchPosSeparate
            ? $business->branches()->get()
                ->map(fn ($b) => ['id' => (int) $b->id, 'name' => $b->name])
                ->values()
                ->all()
            : [];

        return response()->json([
            'data' => [
                'branch_pos_separate' => $branchPosSeparate,
                'branches' => $branches,
            ],
        ]);
    }
}
