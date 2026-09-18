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

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $user     = $request->user();

        $data = $request->validate([
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'integer'],
            'items.*.qty'            => ['required', 'numeric', 'min:0.001'],
            'items.*.item_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_method'         => ['required', 'string'],
            'customer_id'            => ['nullable', 'integer'],
            'amount_paid'            => ['nullable', 'numeric', 'min:0'],
            'amount_tendered'        => ['nullable', 'numeric', 'min:0'],
            'discount_percent'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_flat'          => ['nullable', 'numeric', 'min:0'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
            'credit_due_date'        => ['nullable', 'date_format:Y-m-d'],
        ]);

        $items = array_map(fn ($i) => [
            'product_id'             => (int) $i['product_id'],
            'quantity'               => (float) $i['qty'],
            'item_discount_percent'  => isset($i['item_discount_percent']) ? (float) $i['item_discount_percent'] : null,
        ], $data['items']);

        $paymentMethod = match (strtolower((string) ($data['payment_method'] ?? 'cash'))) {
            'card', 'transfer' => Sale::PAYMENT_CARD,
            'credit'           => Sale::PAYMENT_CREDIT,
            default            => Sale::PAYMENT_CASH,
        };

        try {
            $sale = $this->sales->checkout(
                business:        $business,
                user:            $user,
                items:           $items,
                paymentMethod:   $paymentMethod,
                creditAccountId: null,
                amountPaid:      isset($data['amount_paid']) ? (float) $data['amount_paid'] : null,
                notes:           $data['notes'] ?? null,
                channel:         Sale::CHANNEL_RETAIL,
                discountPercent: isset($data['discount_percent']) ? (float) $data['discount_percent'] : null,
                amountTendered:  isset($data['amount_tendered']) ? (float) $data['amount_tendered'] : null,
                customerId:      isset($data['customer_id']) ? (int) $data['customer_id'] : null,
                creditDueDate:   $data['credit_due_date'] ?? null,
                discountFlat:    isset($data['discount_flat']) ? (float) $data['discount_flat'] : null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        $sale->load(['items.product', 'creditAccount', 'user']);

        return response()->json([
            'message' => 'Sale '.$sale->sale_number.' completed.',
            'data'    => $this->api->formatSale($sale),
        ], 201);
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

    public function pendingCredits(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $today    = now()->startOfDay();

        $sales = $business->sales()
            ->where('payment_method', Sale::PAYMENT_CREDIT)
            ->where('status', Sale::STATUS_COMPLETED)
            ->with('customer')
            ->orderBy('sold_at', 'desc')
            ->get();

        // Group by customer (null customer → walk-in group)
        $groups = $sales->groupBy(fn ($s) => $s->pos_customer_id ?? 0);

        $result = $groups->map(function ($group) use ($today) {
            $first       = $group->first();
            $totalOwed   = round((float) $group->sum('total'), 2);
            $overdueAmt  = 0.0;
            $hasOverdue  = false;

            $salesArr = $group->map(function ($s) use ($today, &$overdueAmt, &$hasOverdue) {
                $isOverdue = $s->credit_due_date !== null && $s->credit_due_date->lt($today);
                if ($isOverdue) {
                    $overdueAmt += (float) $s->total;
                    $hasOverdue  = true;
                }
                return [
                    'id'              => $s->id,
                    'sale_number'     => $s->sale_number,
                    'total'           => round((float) $s->total, 2),
                    'sold_at'         => $s->sold_at?->toIso8601String(),
                    'credit_due_date' => $s->credit_due_date?->format('Y-m-d'),
                    'is_overdue'      => $isOverdue,
                ];
            })->values()->all();

            return [
                'customer_id'    => $first->pos_customer_id,
                'customer_name'  => $first->customer?->name ?? 'Walk-in / Unknown',
                'customer_phone' => $first->customer?->phone,
                'total_owed'     => $totalOwed,
                'overdue_amount' => round($overdueAmt, 2),
                'has_overdue'    => $hasOverdue,
                'sale_count'     => count($salesArr),
                'sales'          => $salesArr,
            ];
        })
        ->sortByDesc('has_overdue')
        ->sortByDesc('overdue_amount')
        ->values()
        ->all();

        return response()->json(['data' => $result]);
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
