<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Rental;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Sale;

class PosProfitReportApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $period = (int) $request->query('period', 30);
        if (! in_array($period, [7, 30, 90, 365])) {
            $period = 30;
        }

        $start = now()->subDays($period)->startOfDay();

        [$buckets, $getBucketKey] = $this->makeBuckets($period);

        // ── Revenue + COGS ────────────────────────────────────────────────────
        $totalRevenue = 0.0;
        $totalCogs    = 0.0;

        if (Schema::hasTable('pos_sales') && Schema::hasTable('pos_sale_items')) {
            $sales = DB::table('pos_sales')
                ->where('business_id', $business->id)
                ->where('status', Sale::STATUS_COMPLETED)
                ->where('sold_at', '>=', $start)
                ->select('id', 'total', 'sold_at')
                ->get();

            $saleIds = $sales->pluck('id');

            $cogsBySale = $saleIds->isNotEmpty()
                ? DB::table('pos_sale_items')
                    ->whereIn('pos_sale_id', $saleIds)
                    ->selectRaw('pos_sale_id, SUM(unit_cost * quantity) as cogs')
                    ->groupBy('pos_sale_id')
                    ->pluck('cogs', 'pos_sale_id')
                : collect();

            foreach ($sales as $s) {
                $rev  = (float) $s->total;
                $cogs = (float) ($cogsBySale[$s->id] ?? 0);
                $totalRevenue += $rev;
                $totalCogs    += $cogs;

                if ($s->sold_at) {
                    $key = $getBucketKey(Carbon::parse($s->sold_at));
                    if (isset($buckets[$key])) {
                        $buckets[$key]['revenue'] += $rev;
                        $buckets[$key]['cogs']    += $cogs;
                    }
                }
            }
        }

        // ── Returns ───────────────────────────────────────────────────────────
        $totalReturns = 0.0;
        if (Schema::hasTable('pos_sale_returns')) {
            $totalReturns = (float) DB::table('pos_sale_returns')
                ->where('business_id', $business->id)
                ->where('returned_at', '>=', $start)
                ->sum('total');
        }

        // ── Expense payments ──────────────────────────────────────────────────
        $totalExpenses = 0.0;
        if (Schema::hasTable('ledger_transactions')) {
            $expTxns = DB::table('ledger_transactions')
                ->where('business_id', $business->id)
                ->whereIn('transactionable_type', [Bill::class, Rental::class])
                ->where('occurrence_date', '>=', $start)
                ->select('amount', 'occurrence_date')
                ->get();

            foreach ($expTxns as $t) {
                $amt = (float) $t->amount;
                $totalExpenses += $amt;
                if ($t->occurrence_date) {
                    $key = $getBucketKey(Carbon::parse($t->occurrence_date));
                    if (isset($buckets[$key])) {
                        $buckets[$key]['expenses'] += $amt;
                    }
                }
            }
        }

        $grossProfit = $totalRevenue - $totalCogs;
        $netProfit   = $grossProfit - $totalExpenses;
        $grossMargin = $totalRevenue > 0
            ? round(($grossProfit / $totalRevenue) * 100, 1)
            : 0.0;

        // ── Top products by gross profit ──────────────────────────────────────
        $topProducts = [];
        if (Schema::hasTable('pos_sale_items') && Schema::hasTable('pos_sales')) {
            $rows = DB::table('pos_sale_items')
                ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
                ->where('pos_sales.business_id', $business->id)
                ->where('pos_sales.status', Sale::STATUS_COMPLETED)
                ->where('pos_sales.sold_at', '>=', $start)
                ->selectRaw('
                    pos_sale_items.product_name,
                    SUM(pos_sale_items.line_total) as revenue,
                    SUM(pos_sale_items.unit_cost * pos_sale_items.quantity) as cogs,
                    SUM(pos_sale_items.quantity) as qty
                ')
                ->groupBy('pos_sale_items.product_name')
                ->orderByRaw('SUM(pos_sale_items.line_total - pos_sale_items.unit_cost * pos_sale_items.quantity) DESC')
                ->limit(10)
                ->get();

            foreach ($rows as $r) {
                $rev    = (float) $r->revenue;
                $cogs   = (float) $r->cogs;
                $gp     = $rev - $cogs;
                $margin = $rev > 0 ? round(($gp / $rev) * 100, 1) : 0.0;
                $topProducts[] = [
                    'name'    => $r->product_name,
                    'revenue' => round($rev, 2),
                    'cogs'    => round($cogs, 2),
                    'gp'      => round($gp, 2),
                    'margin'  => $margin,
                    'qty'     => (int) $r->qty,
                ];
            }
        }

        // ── Trend ─────────────────────────────────────────────────────────────
        $bucketValues = array_values($buckets);
        $trend = [
            'labels'       => array_column($bucketValues, 'label'),
            'revenue'      => array_map(fn ($b) => round($b['revenue'], 2), $bucketValues),
            'gross_profit' => array_map(fn ($b) => round($b['revenue'] - $b['cogs'], 2), $bucketValues),
            'expenses'     => array_map(fn ($b) => round($b['expenses'], 2), $bucketValues),
        ];

        return response()->json([
            'data' => [
                'period'  => $period,
                'summary' => [
                    'revenue'      => round($totalRevenue, 2),
                    'cogs'         => round($totalCogs, 2),
                    'gross_profit' => round($grossProfit, 2),
                    'gross_margin' => $grossMargin,
                    'net_profit'   => round($netProfit, 2),
                    'returns'      => round($totalReturns, 2),
                    'expenses'     => round($totalExpenses, 2),
                ],
                'trend'        => $trend,
                'top_products' => $topProducts,
            ],
        ]);
    }

    private function makeBuckets(int $period): array
    {
        $buckets = [];
        $fn      = null;

        if ($period === 7) {
            for ($i = $period - 1; $i >= 0; $i--) {
                $d   = now()->subDays($i)->startOfDay();
                $key = $d->format('Y-m-d');
                $buckets[$key] = ['label' => $d->format('M j'), 'revenue' => 0.0, 'cogs' => 0.0, 'expenses' => 0.0];
            }
            $fn = fn (Carbon $date) => $date->format('Y-m-d');
        } elseif ($period === 365) {
            for ($i = 11; $i >= 0; $i--) {
                $m   = now()->startOfMonth()->subMonths($i);
                $key = $m->format('Y-m');
                $buckets[$key] = ['label' => $m->format("M 'y"), 'revenue' => 0.0, 'cogs' => 0.0, 'expenses' => 0.0];
            }
            $fn = fn (Carbon $date) => $date->format('Y-m');
        } else {
            $weekCount = (int) ceil($period / 7);
            for ($i = $weekCount - 1; $i >= 0; $i--) {
                $wStart = now()->copy()->startOfWeek()->subWeeks($i);
                $key    = $wStart->format('oW');
                $buckets[$key] = ['label' => $wStart->format('M j'), 'revenue' => 0.0, 'cogs' => 0.0, 'expenses' => 0.0];
            }
            $fn = fn (Carbon $date) => $date->copy()->startOfWeek()->format('oW');
        }

        return [$buckets, $fn];
    }
}
