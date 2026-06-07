<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosOnlineApiService;

class PosOnlineBootstrapApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PosOnlineApiService $api,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $search     = (string) $request->query('q', '');
        $categoryId = $request->query('category');
        $categoryId = is_numeric($categoryId) ? (int) $categoryId : null;
        $page       = max(1, (int) $request->query('page', 1));
        $perPage    = max(1, min(100, (int) $request->query('per_page', 40)));

        return response()->json([
            'data' => $this->api->bootstrap(
                $business,
                $request->user(),
                $search !== '' ? $search : null,
                $categoryId,
                $page,
                $perPage,
            ),
        ]);
    }
}
