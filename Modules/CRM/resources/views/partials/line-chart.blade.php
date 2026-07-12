{{-- Requires: $canvasId, $chartLabels, $chartDatasets — optional $chartAriaLabel, $chartWrapStyle --}}
@php
    $__wrap = $chartWrapStyle ?? 'position:relative;height:min(360px,55vh);width:100%;';
    $__aria = $chartAriaLabel ?? 'Trend chart';
    $__showLegend = isset($chartDatasets) ? count($chartDatasets) > 1 : true;
@endphp
<div style="{{ $__wrap }}">
    <canvas id="{{ $canvasId }}" aria-label="{{ $__aria }}" role="img"></canvas>
</div>
@once
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" crossorigin="anonymous"></script>
@endonce
<script>
(function () {
    var cid = @json($canvasId);
    var labels = @json($chartLabels);
    var datasets = @json($chartDatasets);
    var canvas = document.getElementById(cid);
    if (!canvas || typeof Chart === 'undefined') return;

    var cs = document.documentElement;
    function cssVar(name, fallback) {
        var raw = getComputedStyle(cs).getPropertyValue(name).trim();
        return raw !== '' ? raw : fallback;
    }
    var textColor = cssVar('--text', '#e5e7eb');
    var mutedColor = cssVar('--muted', '#9ca3af');
    var borderColor = cssVar('--border', '#334155');

    function softGrid(c) {
        if (typeof c === 'string' && /^#[0-9a-f]{6}$/i.test(c)) {
            var r = parseInt(c.slice(1, 3), 16);
            var g = parseInt(c.slice(3, 5), 16);
            var b = parseInt(c.slice(5, 7), 16);
            return 'rgba(' + r + ',' + g + ',' + b + ',0.25)';
        }
        return c;
    }

    new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: { labels: labels, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: @json($__showLegend),
                    position: 'bottom',
                    labels: { color: textColor, boxWidth: 12, padding: 16, font: { size: 12 } },
                },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            var n = ctx.parsed && typeof ctx.parsed.y === 'number' ? ctx.parsed.y : null;
                            if (n === null) return ctx.dataset.label || '';
                            var lbl = ctx.dataset.label ? ctx.dataset.label + ': ' : '';
                            return lbl + Math.round(n);
                        },
                    },
                },
            },
            scales: {
                x: {
                    ticks: { color: mutedColor, maxRotation: 45, autoSkip: true },
                    grid: { color: softGrid(borderColor) },
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, color: mutedColor },
                    grid: { color: softGrid(borderColor) },
                    border: { color: borderColor },
                },
            },
        },
    });
})();
</script>
