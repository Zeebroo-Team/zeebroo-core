<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleRevert;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Pos\Services\SaleRevertService;

class PosSaleRevertApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly SaleRevertService $reverts,
        private readonly PosOnlineApiService $api,
    ) {
    }

    /** Sales → Reverted Bills tab: paginated list plus totals for the same filters. */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $filters = [
            'q'         => (string) $request->query('q', ''),
            'reason'    => (string) $request->query('reason', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to'   => (string) $request->query('date_to', ''),
        ];

        $paginator = $this->reverts->indexForBusiness($business, $filters);

        return response()->json([
            'data'    => $paginator->getCollection()->map(fn (SaleRevert $r) => $this->api->formatSaleRevert($r, false))->values()->all(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
            'summary' => $this->reverts->summaryForBusiness($business, $filters),
            'reasons' => SaleRevert::REASONS,
        ]);
    }

    public function show(Request $request, SaleRevert $saleRevert): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $saleRevert->business_id === (int) $business->id, 404);

        return response()->json([
            'data' => $this->api->formatSaleRevert($saleRevert),
        ]);
    }

    public function reasons(): JsonResponse
    {
        return response()->json(['data' => SaleRevert::REASONS]);
    }

    public function store(Request $request, Sale $sale): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $sale->business_id === (int) $business->id, 404);
        $this->abortUnlessPerm($request, $business, 'pos_returns');

        $validated = $request->validate([
            'reason' => 'required|string|max:40',
            'notes'  => 'nullable|string|max:1000',
        ]);

        try {
            $revert = $this->reverts->revert(
                $sale,
                $business,
                $request->user() ?? abort(401),
                $validated['reason'],
                $validated['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not revert this bill.',
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Bill '.$revert->sale_number.' has been reverted.',
            'data'    => [
                'revert' => $this->api->formatSaleRevert($revert),
                'sale'   => $this->api->formatSale($sale->fresh()),
            ],
        ], 201);
    }
}
