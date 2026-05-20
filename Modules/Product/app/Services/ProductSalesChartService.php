<?php

namespace Modules\Product\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleItem;
use Modules\Product\Models\Product;

class ProductSalesChartService
{
    private const DAILY_BUCKETS = 30;

    private const WEEKLY_BUCKETS = 12;

    private const MONTHLY_BUCKETS = 12;

    /**
     * @return array{
     *     labels: list<string>,
     *     datasets: list<array<string, mixed>>,
     *     hasData: bool,
     *     period: string,
     *     totalUnits: float,
     *     rangeLabel: string,
     * }
     */
    public function build(Product $product, string $period): array
    {
        $period = in_array($period, ['daily', 'weekly', 'monthly'], true) ? $period : 'weekly';
        $grid = $this->bucketGrid($period);
        $rangeStart = $grid['rangeStart'];

        $rows = $this->saleRows($product, $rangeStart);

        $buckets = $grid['buckets'];
        foreach ($rows as $row) {
            $soldAt = $row->sold_at instanceof Carbon
                ? $row->sold_at
                : Carbon::parse($row->sold_at);
            $key = $this->bucketKey($soldAt, $period);
            if (array_key_exists($key, $buckets)) {
                $buckets[$key] = round($buckets[$key] + (float) $row->quantity, 3);
            }
        }

        $data = array_values($buckets);
        $totalUnits = round(array_sum($data), 3);
        $hasData = $totalUnits > 0.0001;

        $color = $this->chartColor();

        return [
            'labels' => $grid['labels'],
            'datasets' => [
                array_merge([
                    'label' => 'Units sold',
                    'data' => $data,
                    'backgroundColor' => $color['fill'],
                    'borderColor' => $color['border'],
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ]),
            ],
            'hasData' => $hasData,
            'period' => $period,
            'totalUnits' => $totalUnits,
            'rangeLabel' => $grid['rangeLabel'],
        ];
    }

    /**
     * @return Collection<int, object{quantity: string|float, sold_at: Carbon|string}>
     */
    private function saleRows(Product $product, Carbon $rangeStart): Collection
    {
        return SaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->where('pos_sale_items.product_id', $product->id)
            ->where('pos_sales.business_id', $product->business_id)
            ->where('pos_sales.status', Sale::STATUS_COMPLETED)
            ->where('pos_sales.sold_at', '>=', $rangeStart)
            ->orderBy('pos_sales.sold_at')
            ->get([
                'pos_sale_items.quantity',
                'pos_sales.sold_at',
            ]);
    }

    /**
     * @return array{
     *     buckets: array<string, float>,
     *     labels: list<string>,
     *     rangeStart: Carbon,
     *     rangeLabel: string,
     * }
     */
    private function bucketGrid(string $period): array
    {
        return match ($period) {
            'daily' => $this->dailyGrid(),
            'monthly' => $this->monthlyGrid(),
            default => $this->weeklyGrid(),
        };
    }

    /**
     * @return array{buckets: array<string, float>, labels: list<string>, rangeStart: Carbon, rangeLabel: string}
     */
    private function dailyGrid(): array
    {
        $end = Carbon::now()->endOfDay();
        $start = $end->copy()->subDays(self::DAILY_BUCKETS - 1)->startOfDay();
        $buckets = [];
        $labels = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $buckets[$key] = 0.0;
            $labels[] = $cursor->format('M j');
            $cursor->addDay();
        }

        return [
            'buckets' => $buckets,
            'labels' => $labels,
            'rangeStart' => $start,
            'rangeLabel' => 'Last '.self::DAILY_BUCKETS.' days',
        ];
    }

    /**
     * @return array{buckets: array<string, float>, labels: list<string>, rangeStart: Carbon, rangeLabel: string}
     */
    private function weeklyGrid(): array
    {
        $end = Carbon::now()->endOfWeek(Carbon::SUNDAY);
        $start = $end->copy()->subWeeks(self::WEEKLY_BUCKETS - 1)->startOfWeek(Carbon::MONDAY);
        $buckets = [];
        $labels = [];
        $cursor = $start->copy();

        for ($i = 0; $i < self::WEEKLY_BUCKETS; $i++) {
            $weekStart = $cursor->copy();
            $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
            $key = $weekStart->format('o-\WW');
            $buckets[$key] = 0.0;
            $labels[] = $weekStart->format('M j').' – '.$weekEnd->format('M j');
            $cursor->addWeek();
        }

        return [
            'buckets' => $buckets,
            'labels' => $labels,
            'rangeStart' => $start,
            'rangeLabel' => 'Last '.self::WEEKLY_BUCKETS.' weeks',
        ];
    }

    /**
     * @return array{buckets: array<string, float>, labels: list<string>, rangeStart: Carbon, rangeLabel: string}
     */
    private function monthlyGrid(): array
    {
        $end = Carbon::now()->endOfMonth();
        $start = $end->copy()->subMonths(self::MONTHLY_BUCKETS - 1)->startOfMonth();
        $buckets = [];
        $labels = [];
        $cursor = $start->copy();

        for ($i = 0; $i < self::MONTHLY_BUCKETS; $i++) {
            $key = $cursor->format('Y-m');
            $buckets[$key] = 0.0;
            $labels[] = $cursor->format('M Y');
            $cursor->addMonthNoOverflow();
        }

        return [
            'buckets' => $buckets,
            'labels' => $labels,
            'rangeStart' => $start,
            'rangeLabel' => 'Last '.self::MONTHLY_BUCKETS.' months',
        ];
    }

    private function bucketKey(Carbon $soldAt, string $period): string
    {
        return match ($period) {
            'daily' => $soldAt->format('Y-m-d'),
            'monthly' => $soldAt->format('Y-m'),
            default => $soldAt->copy()->startOfWeek(Carbon::MONDAY)->format('o-\WW'),
        };
    }

    /**
     * @return array{fill: string, border: string}
     */
    private function chartColor(): array
    {
        return [
            'fill' => 'rgba(99, 102, 241, 0.55)',
            'border' => 'rgba(99, 102, 241, 1)',
        ];
    }
}
