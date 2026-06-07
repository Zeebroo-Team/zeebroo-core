<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Pos\Services\SaleReturnService;

class PosSaleReturnApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly SaleReturnService $returnService,
        private readonly PosOnlineApiService $api,
    ) {
    }

    public function store(Request $request, Sale $sale): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $sale->business_id === (int) $business->id, 404);

        $validated = $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.sale_item_id'   => 'required|integer|min:1',
            'items.*.quantity'       => 'required|numeric|min:0.001',
            'refund_method'          => 'required|string|in:cash,card,credit',
            'credit_account_id'      => 'nullable|integer|min:1',
            'notes'                  => 'nullable|string|max:1000',
            'refund_reason'          => 'nullable|string|max:500',
        ]);

        try {
            $saleReturn = $this->returnService->processReturn(
                $sale,
                $business,
                $request->user() ?? abort(401),
                $validated['items'],
                $validated['refund_method'],
                $validated['credit_account_id'] ?? null,
                $validated['notes'] ?? null,
                $validated['refund_reason'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not process return.',
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Return processed successfully.',
            'data'    => [
                'return_number' => $saleReturn->return_number,
                'total'         => round((float) $saleReturn->total, 2),
                'sale'          => $this->api->formatSale($sale->fresh()),
            ],
        ], 201);
    }
}
