<?php

namespace Modules\Pos\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\StockAudit;
use Modules\Pos\Services\StockAuditService;

class PosStockAuditApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly StockAuditService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $audits = $this->service->listForBusiness($business);

        return response()->json([
            'data' => $audits->items(),
            'meta' => [
                'current_page' => $audits->currentPage(),
                'last_page'    => $audits->lastPage(),
                'total'        => $audits->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_audit');

        $data = $request->validate([
            'audit_date' => ['required', 'date'],
            'notes'      => ['nullable', 'string', 'max:2000'],
        ]);

        $audit = $this->service->create($business, $data, $request->user());
        $audit->load('lines');

        return response()->json(['data' => $audit], 201);
    }

    public function show(Request $request, StockAudit $stockAudit): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $audit = $this->service->auditForBusiness($business, $stockAudit);
        $audit->load('lines');

        return response()->json(['data' => $audit]);
    }

    public function saveLines(Request $request, StockAudit $stockAudit): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_audit');

        $audit = $this->service->auditForBusiness($business, $stockAudit);

        $request->validate([
            'lines'               => ['required', 'array'],
            'lines.*.counted_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->saveLines($audit, $request->input('lines', []));
        $audit->load('lines');

        return response()->json(['data' => $audit]);
    }

    public function finalize(Request $request, StockAudit $stockAudit): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_audit');

        $audit = $this->service->auditForBusiness($business, $stockAudit);
        $this->service->finalize($audit, $request->user());

        return response()->json(['data' => $audit->fresh()]);
    }

    public function destroy(Request $request, StockAudit $stockAudit): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_audit');

        $audit = $this->service->auditForBusiness($business, $stockAudit);
        $this->service->delete($audit);

        return response()->json(['message' => 'Deleted.']);
    }
}
