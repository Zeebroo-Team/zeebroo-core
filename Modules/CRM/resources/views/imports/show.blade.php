@extends('theme::layouts.app', ['title' => 'Import Leads', 'heading' => 'Import: ' . $import->file_name])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('crm::imports.partials.styles')

@if($import->isRunning())
    {{-- Refresh while the queued import runs --}}
    <meta http-equiv="refresh" content="4">
@endif

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif
    @if(! empty($previewError))
        <div class="pcat-banner pcat-banner--err">{{ $previewError }}</div>
    @endif

    <p style="margin:0 0 12px;font-size:13px;">
        <a href="{{ route('crm.imports.index') }}" class="pcat-link"><i class="fa fa-arrow-left"></i> All imports</a>
        <span class="crm-imp-tag" style="margin-left:8px;">{{ $row['format_label'] }}</span>
        <span class="crm-imp-status crm-imp-status--{{ $row['status'] }}" style="margin-left:4px;">{{ ucfirst($row['status']) }}</span>
        <span class="muted" style="margin-left:8px;">{{ number_format($row['total_rows']) }} rows</span>
    </p>

    @if($import->isPending())
        {{-- Step 1: columns → step 2: preview → step 3: run --}}
        @php
            $fields  = collect($options['fields']);
            $mapping = old('mapping', $row['mapping']);
        @endphp
        <form method="POST" action="{{ route('crm.imports.preview', $import->id) }}">
            @csrf
            <div class="crm-imp-box" style="margin-bottom:14px;">
                <h4>1. Columns</h4>
                <p class="muted" style="font-size:12px;margin:0 0 8px;">
                    Each column was given a suggested field. "Keep in notes" saves the answer on the lead as "Column: value".
                    A phone or email column is required.
                </p>
                <div class="crm-imp-wrap">
                    <table class="crm-imp-table">
                        <thead><tr><th style="width:28%;">Column in the file</th><th style="width:28%;">Import as</th><th>First values</th></tr></thead>
                        <tbody>
                        @foreach($headers as $i => $header)
                            <tr>
                                <td><strong>{{ $header !== '' ? $header : 'Column ' . ($i + 1) }}</strong></td>
                                <td>
                                    <select name="mapping[{{ $i }}]">
                                        <option value="" @selected(($mapping[$i] ?? '') === '')>— Leave out —</option>
                                        <option value="note" @selected(($mapping[$i] ?? '') === 'note')>Keep in notes</option>
                                        @foreach($fields as $f)
                                            <option value="{{ $f['key'] }}" @selected(($mapping[$i] ?? '') === $f['key'])>{{ $f['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="muted" style="font-size:12px;">
                                    {{ collect($sample)->map(fn ($cells) => trim((string) ($cells[$i] ?? '')))->filter()->take(3)->map(fn ($v) => \Illuminate\Support\Str::limit($v, 40))->implode(' · ') }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="crm-imp-box" style="margin-bottom:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <div>
                    <label>Lead source (when the file has no source column)</label>
                    <select name="source">
                        @foreach($options['sources'] as $s)
                            <option value="{{ $s['key'] }}" @selected(old('source', $row['source']) === $s['key'])>{{ $s['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Pipeline</label>
                    <select name="project_id">
                        @foreach($pipelines as $p)
                            <option value="{{ $p->id }}" @selected((int) old('project_id', $row['project']['id'] ?? 0) === $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>When the contact already has an open lead</label>
                    <select name="duplicates">
                        @foreach($options['duplicate_modes'] as $m)
                            <option value="{{ $m['key'] }}" @selected(old('duplicates', $row['duplicates']) === $m['key'])>{{ $m['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button class="crm-imp-btn crm-imp-btn--primary" type="submit"><i class="fa fa-magnifying-glass"></i> {{ $preview ? 'Check again' : '2. Preview rows' }}</button>
        </form>

        @if($preview)
            @php $t = $preview['totals']; @endphp
            <div class="crm-imp-box" style="margin-top:16px;">
                <h4>2. Preview — nothing is saved yet</h4>
                <div class="crm-imp-totals">
                    <span>{{ $t['total'] }} rows</span>
                    <span class="crm-imp-status--ok">{{ $t['ok'] }} ready</span>
                    <span class="crm-imp-status--duplicate">{{ $t['duplicate'] }} duplicates</span>
                    <span class="crm-imp-status--error">{{ $t['error'] }} errors</span>
                    <span>{{ $t['skipped'] }} skipped</span>
                </div>
                <div class="crm-imp-wrap" style="max-height:420px;overflow-y:auto;">
                    <table class="crm-imp-table">
                        <thead><tr><th>Row</th><th>Name</th><th>Phone</th><th>Email</th><th>Status</th><th>Details</th></tr></thead>
                        <tbody>
                        @foreach($preview['rows'] as $r)
                            <tr>
                                <td>{{ $r['row'] }}</td>
                                <td>{{ $r['name'] ?? '—' }}</td>
                                <td>{{ $r['phone'] ?? '' }}</td>
                                <td>{{ $r['email'] ?? '' }}</td>
                                <td><span class="crm-imp-status crm-imp-status--{{ $r['status'] }}">{{ ucfirst($r['status']) }}</span></td>
                                <td style="font-size:12px;">
                                    {{ $r['reason'] }}
                                    @foreach($r['warnings'] as $w)<div class="muted"><i class="fa fa-triangle-exclamation"></i> {{ $w }}</div>@endforeach
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <form method="POST" action="{{ route('crm.imports.run', $import->id) }}"
                          onsubmit="return confirm('Import {{ $t['ok'] + $t['duplicate'] }} rows? Rows with errors are left out.');">
                        @csrf
                        <button class="crm-imp-btn crm-imp-btn--primary" type="submit" @disabled($t['ok'] + $t['duplicate'] === 0)>
                            <i class="fa fa-file-import"></i> 3. Run import
                        </button>
                    </form>
                    @if($row['queued'])
                        <span class="muted" style="font-size:12px;margin-top:8px;">Over {{ number_format($options['queue_above']) }} rows — it will run in the background.</span>
                    @endif
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('crm.imports.destroy', $import->id) }}" style="margin-top:12px;" onsubmit="return confirm('Discard this upload?');">
            @csrf @method('DELETE')
            <button class="crm-imp-btn" type="submit"><i class="fa fa-trash"></i> Discard upload</button>
        </form>
    @else
        <div class="crm-imp-box">
            <h4>Result</h4>
            @if($import->isRunning())
                <p style="font-size:13px;margin:0 0 8px;">
                    <i class="fa fa-spinner fa-spin"></i> Importing… {{ number_format($row['processed_rows']) }} of {{ number_format($row['total_rows']) }} rows done. This page refreshes by itself.
                </p>
            @endif
            @if($row['status'] === 'failed')
                <div class="pcat-banner pcat-banner--err">Import failed: {{ $row['error'] ?: 'unknown error' }}</div>
            @endif
            <div class="crm-imp-totals">
                <span class="crm-imp-status--ok">{{ $row['created_count'] }} new leads</span>
                <span class="crm-imp-status--duplicate">{{ $row['duplicate_count'] }} added to existing leads</span>
                <span>{{ $row['skipped_count'] }} skipped</span>
                <span class="crm-imp-status--error">{{ $row['failed_count'] }} failed</span>
            </div>
            <p class="muted" style="font-size:12px;margin:0;">
                Pipeline: {{ $row['project']['name'] ?? '—' }} · Source: {{ $row['source_label'] }} · By {{ $row['user_name'] ?? '—' }}
                @if($import->finished_at) · Finished {{ $import->finished_at->diffForHumans() }}@endif
            </p>
            @if($row['has_error_rows'])
                <a href="{{ route('crm.imports.errors', $import->id) }}" class="crm-imp-btn" style="display:inline-block;text-decoration:none;">
                    <i class="fa fa-download"></i> Download error rows (CSV)
                </a>
            @endif
            @if($row['project'])
                <a href="{{ route('crm.projects.leads.index', $row['project']['id']) }}" class="pcat-link" style="margin-left:10px;font-size:12px;">Open {{ $row['project']['name'] }} leads <i class="fa fa-arrow-right"></i></a>
            @endif
        </div>
    @endif
</div>
@endsection
