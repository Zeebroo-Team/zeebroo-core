<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosProductQuickCreateService;

class PosProductApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PosProductQuickCreateService $quickCreate,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        try {
            $product = $this->quickCreate->create($business, $request->all());

            return response()->json([
                'message' => 'Product added.',
                'data' => $product,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not save product.',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
