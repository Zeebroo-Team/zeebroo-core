@php
    $__wrap = $chartWrapStyle ?? 'position:relative;height:min(280px,42vh);width:100%;';
    $__aria = $chartAriaLabel ?? 'Sales chart';
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
        type: 'bar',
        data: { labels: labels, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            var n = ctx.parsed && typeof ctx.parsed.y === 'number' ? ctx.parsed.y : 0;
                            return 'Units sold: ' + n.toLocaleString(undefined, { maximumFractionDigits: 3 });
                        },
                    },
                },
            },
            scales: {
                x: {
                    ticks: { color: mutedColor, maxRotation: 45, autoSkip: true, font: { size: 11 } },
                    grid: { display: false },
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
