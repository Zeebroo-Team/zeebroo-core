<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Sale;
use Modules\Service\Models\ServiceRequest;

class PosTodaySummaryApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $today    = now()->startOfDay();

        // ── Sales ──────────────────────────────────────────────────────────────
        $sales = $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', $today)
            ->with('items')
            ->get();

        $byMethod = [];
        foreach ($sales->groupBy('payment_method') as $method => $group) {
            $byMethod[$method] = [
                'count' => $group->count(),
                'total' => round((float) $group->sum('total'), 2),
            ];
        }

        // Hourly breakdown: index 0-23, each slot has count + revenue
        $hourly = array_fill(0, 24, ['count' => 0, 'revenue' => 0.0]);
        foreach ($sales as $s) {
            if ($s->sold_at) {
                $h = (int) $s->sold_at->format('G');
                $hourly[$h]['count']++;
                $hourly[$h]['revenue'] = round($hourly[$h]['revenue'] + (float) $s->total, 2);
            }
        }

        $allItems      = $sales->flatMap->items;
        $productItems  = $allItems->filter(fn ($i) => $i->service_item_id === null);
        $serviceItems  = $allItems->filter(fn ($i) => $i->service_item_id !== null);

        $topProducts = $productItems
            ->groupBy('product_name')
            ->map(fn ($g) => [
                'name'    => $g->first()->product_name,
                'qty'     => round((float) $g->sum('quantity'), 2),
                'revenue' => round((float) $g->sum('line_total'), 2),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->take(5)
            ->all();

        $topServices = $serviceItems
            ->groupBy('product_name')
            ->map(fn ($g) => [
                'name'    => $g->first()->product_name,
                'qty'     => round((float) $g->sum('quantity'), 2),
                'revenue' => round((float) $g->sum('line_total'), 2),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->take(5)
            ->all();

        $recentSales = $sales
            ->sortByDesc('sold_at')
            ->take(15)
            ->values()
            ->map(fn ($s) => [
                'id'             => $s->id,
                'sale_number'    => $s->sale_number,
                'total'          => round((float) $s->total, 2),
                'payment_method' => $s->payment_method,
                'sold_at'        => $s->sold_at?->toIso8601String(),
                'items_count'    => $s->items->count(),
            ])
            ->values()
            ->all();

        // ── Service requests ───────────────────────────────────────────────────
        $svcRequests = ServiceRequest::query()
            ->where('business_id', $business->id)
            ->where(function ($q) use ($today) {
                $q->whereIn('status', [ServiceRequest::STATUS_PENDING, ServiceRequest::STATUS_IN_PROGRESS])
                  ->orWhere(function ($q2) use ($today) {
                      $q2->where('status', ServiceRequest::STATUS_COMPLETED)
                         ->where('updated_at', '>=', $today);
                  });
            })
            ->with(['serviceItem', 'customer'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->limit(30)
            ->get();

        $svcList = $svcRequests->map(fn ($r) => [
            'id'             => $r->id,
            'request_number' => $r->request_number,
            'title'          => $r->title,
            'status'         => $r->status,
            'status_label'   => $r->statusLabel(),
            'customer_name'  => $r->customer?->name,
            'scheduled_at'   => $r->scheduled_at?->toIso8601String(),
            'total_price'    => $r->total_price !== null ? (float) $r->total_price : null,
        ])->values()->all();

        return response()->json([
            'data' => [
                'sales' => [
                    'count'      => $sales->count(),
                    'revenue'    => round((float) $sales->sum('total'), 2),
                    'items_sold' => (int) $allItems->sum('quantity'),
                    'by_method'  => $byMethod,
                    'hourly'     => $hourly,
                ],
                'service_requests' => [
                    'pending'     => $svcRequests->where('status', ServiceRequest::STATUS_PENDING)->count(),
                    'in_progress' => $svcRequests->where('status', ServiceRequest::STATUS_IN_PROGRESS)->count(),
                    'completed'   => $svcRequests->where('status', ServiceRequest::STATUS_COMPLETED)->count(),
                    'list'        => $svcList,
                ],
                'top_products' => $topProducts,
                'top_services' => $topServices,
                'recent_sales' => $recentSales,
            ],
        ]);
    }
}
