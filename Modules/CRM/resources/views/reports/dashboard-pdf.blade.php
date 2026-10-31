{{-- The CRM dashboard as exported from the desktop (CrmReportExportService::pdf). dompdf: tables and inline styles only. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>CRM dashboard</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    h2 { font-size: 12px; margin: 16px 0 6px; color: #334155; }
    .muted { color: #64748b; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f1f5f9; text-align: left; font-weight: bold; }
    th, td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
    td.r, th.r { text-align: right; }
    .kpis td { width: 25%; vertical-align: top; border: 1px solid #e2e8f0; padding: 6px; }
    .kpi-v { font-size: 13px; font-weight: bold; }
    .bar { background: #6366f1; height: 7px; }
    .two td.col { width: 50%; vertical-align: top; padding: 0 8px 0 0; border: 0; }
</style>
</head>
<body>
    <h1>{{ $business->name }} — CRM dashboard</h1>
    <div class="muted">{{ $filters }} · generated {{ now()->format('M j, Y g:i A') }}</div>

    <h2>Key figures</h2>
    <table class="kpis">
        @foreach (array_chunk($kpis, 4) as $row)
            <tr>
                @foreach ($row as [$label, $value])
                    <td><div class="muted">{{ $label }}</div><div class="kpi-v">{{ $value }}</div></td>
                @endforeach
                @for ($i = count($row); $i < 4; $i++)<td></td>@endfor
            </tr>
        @endforeach
    </table>

    @php $maxFunnel = max(1, ...array_column($report['funnel'], 'leads')); @endphp
    <h2>Funnel (leads created in the period, by current status)</h2>
    <table>
        <tr><th>Status</th><th class="r">Leads</th><th style="width:45%"></th></tr>
        @foreach ($report['funnel'] as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="r">{{ $row['leads'] }}</td>
                <td><div class="bar" style="width: {{ round($row['leads'] / $maxFunnel * 100) }}%; background: {{ $row['color'] }}"></div></td>
            </tr>
        @endforeach
    </table>

    <table class="two"><tr>
        <td class="col">
            <h2>By source</h2>
            <table>
                <tr><th>Source</th><th class="r">Leads</th><th class="r">Won</th></tr>
                @forelse ($report['by_source'] as $row)
                    <tr><td>{{ $row['label'] }}</td><td class="r">{{ $row['leads'] }}</td><td class="r">{{ $row['won'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">No leads.</td></tr>
                @endforelse
            </table>
        </td>
        <td class="col">
            <h2>By department</h2>
            <table>
                <tr><th>Department</th><th class="r">Leads</th><th class="r">Won</th></tr>
                @forelse ($report['by_department'] as $row)
                    <tr><td>{{ $row['label'] }}</td><td class="r">{{ $row['leads'] }}</td><td class="r">{{ $row['won'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">No leads.</td></tr>
                @endforelse
            </table>
        </td>
    </tr></table>

    <h2>Staff ranking</h2>
    <table>
        <tr><th>#</th><th>Staff</th><th class="r">Leads</th><th class="r">Won</th><th class="r">Won value</th><th class="r">Conversion</th></tr>
        @forelse ($report['staff'] as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td><td>{{ $row['name'] }}</td><td class="r">{{ $row['leads'] }}</td><td class="r">{{ $row['won'] }}</td>
                <td class="r">{{ number_format($row['won_value'], 2) }}</td><td class="r">{{ $row['conversion_rate'] }}%</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No assigned leads.</td></tr>
        @endforelse
    </table>

    <h2>Top lost reasons</h2>
    <table>
        <tr><th>Reason</th><th class="r">Leads</th></tr>
        @forelse ($report['lost_reasons'] as $row)
            <tr><td>{{ $row['label'] }}</td><td class="r">{{ $row['leads'] }}</td></tr>
        @empty
            <tr><td colspan="2" class="muted">No lost leads in the period.</td></tr>
        @endforelse
    </table>
</body>
</html>
