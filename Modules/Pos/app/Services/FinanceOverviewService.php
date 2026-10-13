<?php

namespace Modules\Pos\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Account\Models\Investment;
use Modules\Account\Models\Loan;
use Modules\Account\Models\Property;
use Modules\Account\Services\InvestmentService;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;

/**
 * Finance → Overview (desktop). Headline figures for every Finance section plus
 * month-to-date POS takings. Bills / loans / rentals monthly totals and overdue
 * items come from finance/flow; the rules here mirror each section's own index endpoint.
 */
class FinanceOverviewService
{
    /** Same "expiring soon" window as the Properties list. */
    private const PROPERTY_EXPIRY_DAYS = 60;

    /** Months shown in the Overview sales trend (current month included). */
    private const TREND_MONTHS = 6;

    public function __construct(private readonly InvestmentService $investments) {}

    public function build(Business $business, bool $withSales): array
    {
        return [
            'loans'         => $this->loans($business),
            'properties'    => $this->properties($business),
            'modifications' => $this->modifications($business),
            'investments'   => $this->investmentsSummary($business),
            'budgets'       => $this->budgets($business),
            'sales'         => $withSales ? $this->sales($business) : null,
        ];
    }

    private function loans(Business $business): array
    {
        $today = now()->toDateString();
        $loans = Loan::where('business_id', $business->id)->get(['id', 'borrowed_amount', 'loan_ending_date']);

        // Active = no ending date, or ending today or later (same as PosLoanApiController@index)
        $active = $loans->filter(fn (Loan $l) => ! $l->loan_ending_date || $l->loan_ending_date->toDateString() >= $today);

        return [
            'count'               => $loans->count(),
            'active_count'        => $active->count(),
            'total_principal_fmt' => $this->fmt($active->sum(fn (Loan $l) => (float) $l->borrowed_amount)),
        ];
    }

    private function properties(Business $business): array
    {
        $properties = Property::where('business_id', $business->id)->get();
        $threshold  = Carbon::today()->addDays(self::PROPERTY_EXPIRY_DAYS);

        $expiring = $properties
            ->filter(fn (Property $p) => $p->has_expiry && $p->expire_date !== null && $p->expire_date->lte($threshold))
            ->sortBy('expire_date')
            ->values();

        return [
            'count'          => $properties->count(),
            'total_cost_fmt' => $this->fmt($properties->sum(fn (Property $p) => (float) $p->cost)),
            'expiring_count' => $expiring->count(),
            'expiring'       => $expiring->take(5)->map(fn (Property $p) => [
                'id'          => $p->id,
                'name'        => $p->property_name,
                'expire_date' => $p->expire_date->toDateString(),
                'expired'     => $p->expire_date->lt(Carbon::today()),
            ])->values(),
        ];
    }

    private function modifications(Business $business): array
    {
        if (! Schema::hasTable('modifications')) {
            return ['count' => 0, 'total_cost_fmt' => $this->fmt(0)];
        }

        $row = DB::table('modifications')
            ->where('business_id', $business->id)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(estimated_cost), 0) as cost')
            ->first();

        return [
            'count'          => (int) $row->cnt,
            'total_cost_fmt' => $this->fmt((float) $row->cost),
        ];
    }

    private function investmentsSummary(Business $business): array
    {
        if (! Schema::hasTable('investments')) {
            return ['count' => 0, 'active_count' => 0, 'overdue_count' => 0, 'total_invested_fmt' => $this->fmt(0)];
        }

        $list      = $this->investments->listForBusiness($business);
        $summaries = $list->map(fn (Investment $i) => $this->investments->summary($i));

        return [
            'count'              => $list->count(),
            'active_count'       => $list->where('status', Investment::STATUS_ACTIVE)->count(),
            'overdue_count'      => $summaries->where('overdue_count', '>', 0)->count(),
            'total_invested_fmt' => $this->fmt($summaries->sum('total_invested')),
        ];
    }

    private function budgets(Business $business): array
    {
        if (! Schema::hasTable('budgets')) {
            return ['count' => 0, 'active_count' => 0];
        }

        $query = DB::table('budgets')->where('business_id', $business->id);

        return [
            'count'        => (clone $query)->count(),
            'active_count' => $query->where('is_active', true)->count(),
        ];
    }

    /** Month-to-date completed POS sales, compared with the same days of last month. */
    private function sales(Business $business): array
    {
        $now      = Carbon::now();
        $from     = $now->copy()->startOfMonth();
        $prevFrom = $from->copy()->subMonthNoOverflow();
        $prevTo   = $now->copy()->subMonthNoOverflow()->endOfDay();

        $current  = $this->posTotals($business, $from, $now->copy()->endOfDay());
        $previous = $this->posTotals($business, $prevFrom, $prevTo);
        $today    = $this->posTotals($business, $now->copy()->startOfDay(), $now->copy()->endOfDay());

        return [
            'from'           => $from->toDateString(),
            'to'             => $now->toDateString(),
            'count'          => $current['count'],
            'total'          => $current['total'],
            'total_fmt'      => $this->fmt($current['total']),
            'average_fmt'    => $this->fmt($current['count'] > 0 ? $current['total'] / $current['count'] : 0),
            'prev_total_fmt' => $this->fmt($previous['total']),
            'change_pct'     => $previous['total'] > 0
                ? round(($current['total'] - $previous['total']) / $previous['total'] * 100, 1)
                : null,
            'today_count'     => $today['count'],
            'today_total_fmt' => $this->fmt($today['total']),
            'trend'           => $this->salesTrend($business, $now),
        ];
    }

    /** Completed POS sales per calendar month, oldest first, ending with the current (partial) month. */
    private function salesTrend(Business $business, Carbon $now, int $months = self::TREND_MONTHS): array
    {
        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start  = $now->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $end    = $i === 0 ? $now->copy()->endOfDay() : $start->copy()->endOfMonth();
            $totals = $this->posTotals($business, $start, $end);

            $trend[] = [
                'month'     => $start->format('Y-m'),
                'label'     => $start->format('M'),
                'count'     => $totals['count'],
                'total'     => $totals['total'],
                'total_fmt' => $this->fmt($totals['total']),
                'partial'   => $i === 0,
            ];
        }

        return $trend;
    }

    /** @return array{count: int, total: float} */
    private function posTotals(Business $business, Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('pos_sales')) {
            return ['count' => 0, 'total' => 0.0];
        }

        $row = DB::table('pos_sales')
            ->where('business_id', $business->id)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as total')
            ->first();

        return ['count' => (int) $row->cnt, 'total' => round((float) $row->total, 2)];
    }

    private function fmt(float $value): string
    {
        return number_format($value, 2, '.', ',');
    }
}
