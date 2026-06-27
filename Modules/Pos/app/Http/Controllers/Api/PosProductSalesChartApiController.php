<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosProductSalesChartApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __invoke(Request $request, int $productId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $period   = in_array($request->query('period'), ['daily', 'weekly', 'monthly'], true)
            ? $request->query('period')
            : 'weekly';

        $now    = now();
        $driver = DB::getDriverName();

        [$points, $subtitle] = match ($period) {
            'daily'   => [30, 'Last 30 days'],
            'monthly' => [12, 'Last 12 months'],
            default   => [12, 'Last 12 weeks'],
        };

        $bucketExpr = match (true) {
            $period === 'daily'            => $driver === 'sqlite'
                ? "date(s.sold_at)"
                : "DATE(s.sold_at)",
            $period === 'monthly'          => $driver === 'sqlite'
                ? "strftime('%Y-%m-01', s.sold_at)"
                : "DATE_FORMAT(s.sold_at, '%Y-%m-01')",
            // weekly — floor to Monday
            $driver === 'sqlite'           =>
                "date(s.sold_at, '-' || ((strftime('%w', s.sold_at) + 6) % 7) || ' days')",
            default                        =>
                "DATE(s.sold_at - INTERVAL WEEKDAY(s.sold_at) DAY)",
        };

        $start = match ($period) {
            'daily'   => $now->copy()->subDays(29)->startOfDay(),
            'monthly' => $now->copy()->subMonths(11)->startOfMonth(),
            default   => $now->copy()->subWeeks(11)->startOfWeek(),
        };

        $rows = DB::table('pos_sale_items as si')
            ->join('pos_sales as s', 's.id', '=', 'si.pos_sale_id')
            ->where('s.business_id', $business->id)
            ->where('si.product_id', $productId)
            ->where('s.status', 'completed')
            ->where('s.sold_at', '>=', $start)
            ->selectRaw("{$bucketExpr} as bucket, SUM(si.quantity) as qty")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        $labels = [];
        $series = [];
        $cursor = $start->copy();

        for ($i = 0; $i < $points; $i++) {
            $key = match ($period) {
                'daily'   => $cursor->format('Y-m-d'),
                'monthly' => $cursor->format('Y-m-01'),
                default   => $cursor->copy()->startOfWeek()->format('Y-m-d'),
            };

            $labels[] = match ($period) {
                'daily'   => $cursor->format('M j'),
                'monthly' => $cursor->format('M Y'),
                default   => $cursor->copy()->startOfWeek()->format('M j').' – '.$cursor->copy()->endOfWeek()->format('M j'),
            };

            $series[] = (float) ($rows[$key]->qty ?? 0);

            match ($period) {
                'daily'   => $cursor->addDay(),
                'monthly' => $cursor->addMonth(),
                default   => $cursor->addWeek(),
            };
        }

        return response()->json([
            'data' => [
                'period'   => $period,
                'subtitle' => $subtitle,
                'total'    => array_sum($series),
                'labels'   => $labels,
                'series'   => $series,
            ],
        ]);
    }
}
