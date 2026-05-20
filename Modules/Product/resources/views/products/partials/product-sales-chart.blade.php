@php
    $salesChart = $salesChart ?? ['hasData' => false, 'period' => 'weekly', 'labels' => [], 'datasets' => []];
    $salesPeriod = $salesChart['period'] ?? 'weekly';
    $productOverviewUrl = $productOverviewUrl ?? fn (string $period) => '#';
@endphp

<div class="product-sales-chart" role="region" aria-label="Sales over time">
    <div class="product-sales-chart__head">
        <div>
            <h3 class="product-sales-chart__title">
                <i class="fa fa-chart-column" aria-hidden="true"></i> Units sold
            </h3>
            <p class="product-sales-chart__sub muted">{{ $salesChart['rangeLabel'] ?? '' }}</p>
        </div>
        <nav class="product-sales-chart__periods" aria-label="Chart period">
            @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label)
                <a
                    href="{{ $productOverviewUrl($key) }}"
                    class="product-sales-chart__period @if($salesPeriod === $key) is-active @endif"
                    @if($salesPeriod === $key) aria-current="true" @endif
                >{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @if($salesChart['hasData'] ?? false)
        <p class="product-sales-chart__total muted">
            <strong style="color:var(--text);font-weight:800;">{{ number_format((float) ($salesChart['totalUnits'] ?? 0), 3) }}</strong>
            units in this period
        </p>
        @include('product::products.partials.product-sales-chart-canvas', [
            'canvasId' => 'product-sales-chart-canvas',
            'chartLabels' => $salesChart['labels'],
            'chartDatasets' => $salesChart['datasets'],
            'chartAriaLabel' => 'Units sold over time',
        ])
    @else
        <p class="product-sales-chart__empty muted">
            No completed POS sales for this product in the selected period.
            Sales from the register and online POS appear here.
        </p>
    @endif
</div>
