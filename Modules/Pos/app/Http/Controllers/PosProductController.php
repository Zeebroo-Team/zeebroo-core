<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Services\PosProductQuickCreateService;

class PosProductController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly PosProductQuickCreateService $quickCreate,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof \Illuminate\Http\RedirectResponse) {
            return response()->json(['message' => 'No business selected.'], 403);
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        try {
            $product = $this->quickCreate->create($business, $request->all());

            return response()->json([
                'message' => 'Product added.',
                'product' => $product,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not save product.',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
