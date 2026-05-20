<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Pos\Services\SaleService;

class PosSaleApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly SaleService $sales,
        private readonly PosOnlineApiService $api,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $search = (string) $request->query('q', '');
        $channel = $request->query('channel');

        $sales = $this->sales->listForBusiness($business, $search !== '' ? $search : null);

        if (is_string($channel) && in_array($channel, [Sale::CHANNEL_ONLINE, Sale::CHANNEL_RETAIL], true)) {
            $sales = $sales->where('channel', $channel)->values();
        }

        return response()->json([
            'data' => $this->api->formatSaleList($sales),
        ]);
    }

    public function show(Request $request, Sale $sale): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $sale->business_id === (int) $business->id, 404);

        $sale->load(['items.product', 'creditAccount', 'user']);

        return response()->json([
            'data' => $this->api->formatSale($sale),
        ]);
    }

    public function void(Request $request, Sale $sale): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $sale->business_id === (int) $business->id, 404);

        try {
            $sale = $this->sales->void($sale, $business);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not void sale.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Sale '.$sale->sale_number.' has been voided.',
            'data' => $this->api->formatSale($sale),
        ]);
    }
}
