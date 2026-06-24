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
        $limit = $request->query('limit') ? (int) $request->query('limit') : null;

        $sales = $this->sales->listForBusiness($business, $search !== '' ? $search : null, $limit);

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

    public function history(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $filters = [
            'q'         => (string) $request->query('q', ''),
            'status'    => (string) $request->query('status', 'all'),
            'channel'   => (string) $request->query('channel', 'all'),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to'   => (string) $request->query('date_to', ''),
        ];

        $paginator = $this->sales->indexForBusiness($business, $filters);
        $summary   = $this->sales->indexSummary($business, $filters);
        $chart     = $this->sales->dailyChartForBusiness($business, $filters);

        return response()->json([
            'data'    => $this->api->formatSaleList($paginator->getCollection()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
            'summary' => $summary,
            'chart'   => $chart,
        ]);
    }
}
