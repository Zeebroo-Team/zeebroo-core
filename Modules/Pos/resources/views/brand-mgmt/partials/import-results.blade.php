@php $results = session('import_results'); @endphp
@if($results)
<details class="bmg-import-results" open>
    <summary>
        Import finished — {{ $results['created'] }} created
        @if(($results['skipped'] ?? 0) > 0), {{ $results['skipped'] }} skipped @endif
        @if(($results['invalid'] ?? 0) > 0), {{ $results['invalid'] }} invalid @endif
        (of {{ $results['total'] }} row{{ $results['total'] === 1 ? '' : 's' }})
    </summary>
    <table>
        <thead>
            <tr><th>Row</th><th>Name</th><th>Reference</th><th>Status</th><th>Notes</th></tr>
        </thead>
        <tbody>
            @foreach($results['rows'] as $row)
            <tr>
                <td>{{ $row['row'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['short_code'] ?? $row['job_ref'] ?? '—' }}</td>
                <td>
                    <span class="bmg-badge bmg-badge--{{ $row['status'] === 'created' ? 'active' : ($row['status'] === 'invalid' ? 'cancelled' : 'inactive') }}">
                        {{ ucfirst($row['status']) }}
                    </span>
                </td>
                <td style="color:var(--muted);">
                    {{ $row['reason'] ?? (is_array($row['notes'] ?? null) ? implode(', ', $row['notes']) : '') ?: '—' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</details>
@endif
