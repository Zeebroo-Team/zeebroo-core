<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\SalePaymentSettlementService;

class PosEndOfDayApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly SalePaymentSettlementService $payments,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        $unsettled = $business->sales()
            ->where('is_settled', false)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereIn('payment_method', [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD])
            ->with(['creditAccount'])
            ->orderByDesc('sold_at')
            ->get(['id', 'sale_number', 'payment_method', 'total', 'sold_at', 'credit_account_id']);

        $byMethod = [
            'cash' => ['count' => 0, 'total' => 0.0],
            'card' => ['count' => 0, 'total' => 0.0],
        ];
        foreach ($unsettled as $sale) {
            $m = $sale->payment_method;
            if (isset($byMethod[$m])) {
                $byMethod[$m]['count']++;
                $byMethod[$m]['total'] = round($byMethod[$m]['total'] + (float) $sale->total, 2);
            }
        }

        $history = $business->sales()
            ->where('is_settled', true)
            ->whereNotNull('settled_at')
            ->whereIn('payment_method', [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD])
            ->selectRaw('DATE(settled_at) as settle_date, COUNT(*) as sale_count, SUM(total) as total_amount')
            ->groupBy('settle_date')
            ->orderByDesc('settle_date')
            ->limit(7)
            ->get();

        return response()->json([
            'currency' => $currency,
            'unsettled' => $unsettled->map(fn ($s) => [
                'id'             => (int) $s->id,
                'sale_number'    => $s->sale_number,
                'payment_method' => $s->payment_method,
                'total'          => round((float) $s->total, 2),
                'sold_at'        => $s->sold_at?->toIso8601String(),
                'account_label'  => $s->creditAccount?->deductOptionLabel(),
            ])->values()->all(),
            'summary' => [
                'total_count'  => $unsettled->count(),
                'total_amount' => round($unsettled->sum(fn ($s) => (float) $s->total), 2),
                'by_method'    => $byMethod,
            ],
            'history' => $history->map(fn ($r) => [
                'date'       => (string) $r->settle_date,
                'sale_count' => (int) $r->sale_count,
                'total'      => round((float) $r->total_amount, 2),
            ])->values()->all(),
        ]);
    }

    public function settle(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $user     = $request->user();

        $unsettled = $business->sales()
            ->where('is_settled', false)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereIn('payment_method', [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD])
            ->whereNotNull('credit_account_id')
            ->get();

        if ($unsettled->isEmpty()) {
            return response()->json(['message' => 'No unsettled sales to process.', 'settled' => 0], 200);
        }

        $settled = 0;
        $errors  = [];

        foreach ($unsettled as $sale) {
            try {
                $this->payments->settle(
                    $sale,
                    $business,
                    $user,
                    (int) $sale->credit_account_id,
                    (float) $sale->total,
                    $sale->payment_method,
                );
                $sale->update(['is_settled' => true, 'settled_at' => now()]);
                $settled++;
            } catch (ValidationException $e) {
                $errors[] = $e->getMessage();
            } catch (\Throwable $e) {
                $errors[] = 'Sale ' . $sale->sale_number . ': ' . $e->getMessage();
            }
        }

        $message = $settled > 0
            ? "{$settled} sale" . ($settled > 1 ? 's' : '') . ' settled to bank.'
            : 'Settlement failed.';

        return response()->json([
            'message' => $message,
            'settled' => $settled,
            'errors'  => $errors,
        ], $settled > 0 ? 200 : 422);
    }
}
