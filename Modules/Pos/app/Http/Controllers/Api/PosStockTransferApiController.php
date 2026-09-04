<?php

namespace Modules\Pos\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\StockTransfer;
use Modules\Pos\Services\StockTransferService;

class PosStockTransferApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly StockTransferService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $transfers = $this->service->listForBusiness($business, $request->string('q')->trim()->value() ?: null);

        return response()->json([
            'data' => $transfers->items(),
            'meta' => [
                'current_page' => $transfers->currentPage(),
                'last_page'    => $transfers->lastPage(),
                'total'        => $transfers->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_transfer');

        $data = $request->validate([
            'from_branch_id'        => ['required', 'integer'],
            'to_branch_id'          => ['required', 'integer'],
            'notes'                 => ['nullable', 'string', 'max:2000'],
            'lines'                 => ['required', 'array', 'min:1'],
            'lines.*.product_id'    => ['required', 'integer'],
            'lines.*.quantity'      => ['required', 'numeric', 'min:0.001'],
        ]);

        $transfer = $this->service->create($business, $data, $request->user());
        $transfer->load(['lines', 'fromBranch', 'toBranch', 'transferredBy']);

        return response()->json(['data' => $transfer], 201);
    }

    public function show(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $transfer = $this->service->transferForBusiness($business, $stockTransfer);
        $transfer->load(['lines', 'fromBranch', 'toBranch', 'transferredBy', 'receivedBy', 'cancelledBy']);

        return response()->json(['data' => $transfer]);
    }

    public function receive(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_transfer');

        $transfer = $this->service->transferForBusiness($business, $stockTransfer);
        $this->service->receive($transfer, $request->user());

        return response()->json(['data' => $transfer->fresh(['lines', 'fromBranch', 'toBranch', 'transferredBy', 'receivedBy'])]);
    }

    public function cancel(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_transfer');

        $transfer = $this->service->transferForBusiness($business, $stockTransfer);
        $this->service->cancel($transfer, $request->user());

        return response()->json(['data' => $transfer->fresh(['lines', 'fromBranch', 'toBranch', 'transferredBy', 'cancelledBy'])]);
    }
}
