<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Purchase\Models\ChequePayment;
use Modules\Purchase\Services\ChequePaymentService;

class PosGrnChequeApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly ChequePaymentService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $filter   = (string) $request->query('filter', 'all');

        $cheques = $this->service->listForBusiness($business, $filter === 'all' ? null : $filter);

        return response()->json([
            'data'    => $cheques->map(fn (ChequePayment $c) => $this->format($c))->values(),
            'summary' => $this->service->summaryForBusiness($business),
        ]);
    }

    public function clear(Request $request, ChequePayment $cheque): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $cheque->business_id !== (int) $business->id) {
            abort(404);
        }

        if ($cheque->isCleared()) {
            return response()->json(['message' => 'This cheque is already cleared.'], 422);
        }

        $validated = $request->validate([
            'deduct_account_id' => ['nullable', 'integer'],
        ]);

        try {
            $cheque = $this->service->deductFromAccount(
                $cheque,
                $business,
                $request->user() ?? abort(401),
                $validated['deduct_account_id'] ? (int) $validated['deduct_account_id'] : null,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Cheque cleared.',
            'data'    => $this->format($cheque->refresh()),
        ]);
    }

    private function format(ChequePayment $c): array
    {
        $grn      = $c->goodsReceiveNote;
        $purchase = $grn?->purchase;

        return [
            'id'             => $c->id,
            'cheque_number'  => $c->cheque_number,
            'due_date'       => $c->due_date?->format('Y-m-d'),
            'amount'         => round((float) $c->amount, 2),
            'status'         => $c->displayStatus(),
            'status_label'   => $c->displayStatusLabel(),
            'cleared_at'     => $c->cleared_at?->format('Y-m-d'),
            'paid_at'        => $c->paidAt()?->format('Y-m-d'),
            'grn_id'         => $grn?->id,
            'grn_number'     => $grn?->grn_number,
            'purchase_id'    => $purchase?->id,
            'po_number'      => $purchase?->po_number,
            'supplier_name'  => $purchase?->supplier?->name,
            'account'        => $c->deductAccount
                ? ($c->deductAccount->account_name . ($c->deductAccount->bank_name ? ' · ' . $c->deductAccount->bank_name : ''))
                : null,
        ];
    }
}
