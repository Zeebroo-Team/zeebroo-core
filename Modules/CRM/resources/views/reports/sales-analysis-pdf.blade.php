{{-- The CRM sales analysis report as exported from the desktop (CrmReportExportService::salesAnalysisPdf). dompdf: tables and inline styles only. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>CRM sales analysis</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    .muted { color: #64748b; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th { background: #f1f5f9; text-align: left; font-weight: bold; }
    th, td { padding: 4px 5px; border-bottom: 1px solid #e2e8f0; }
    td.r, th.r { text-align: right; }
    tr.total td { font-weight: bold; border-top: 2px solid #cbd5e1; background: #f8fafc; }
    tr.group td { padding-top: 8px; }
</style>
</head>
<body>
    <h1>{{ $business->name }} — Sales analysis by {{ strtolower($report['group_label']) }}</h1>
    <div class="muted">{{ $filters }} · {{ count($report['rows']) }} {{ count($report['rows']) === 1 ? 'row' : 'rows' }} · generated {{ now()->format('M j, Y g:i A') }}</div>

    <table>
        <tr>
            @foreach ($report['columns'] as $column)
                <th class="{{ $column['type'] === 'text' ? '' : 'r' }}">{{ $column['label'] }}</th>
            @endforeach
        </tr>
        @php $previousCustomer = null; @endphp
        @forelse ($report['rows'] as $row)
            {{-- Customer preferences: name the customer once per block --}}
            @php
                $newCustomer = isset($row['customer']) && $row['customer'] !== $previousCustomer;
                $previousCustomer = $row['customer'] ?? null;
            @endphp
            <tr class="{{ $newCustomer ? 'group' : '' }}">
                @foreach ($report['columns'] as $column)
                    <td class="{{ $column['type'] === 'text' ? '' : 'r' }}">
                        @if ($column['key'] === 'customer' && ! $newCustomer)
                        @else
                            {{ $cell($column, $row[$column['key']] ?? null) }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($report['columns']) }}" class="muted">Nothing matches these filters.</td></tr>
        @endforelse
        @if (count($report['rows']))
            <tr class="total">
                @foreach ($report['columns'] as $i => $column)
                    <td class="{{ $column['type'] === 'text' ? '' : 'r' }}">{{ $i === 0 ? 'Total' : (array_key_exists($column['key'], $report['totals']) ? $cell($column, $report['totals'][$column['key']]) : '') }}</td>
                @endforeach
            </tr>
        @endif
    </table>
</body>
</html>
