@extends('theme::layouts.app', ['title' => 'Import Leads', 'heading' => 'Import Leads'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('crm::imports.partials.styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Upload leads in bulk from our CSV template, a Google Forms responses export or a Facebook Lead Ads export.
        You check the columns and preview every row before anything is saved. Leads go to
        @if($intakeProject)<strong style="color:var(--text);">{{ $intakeProject->name }}</strong> (the marketing inbox)@else the pipeline you choose@endif,
        and repeat contacts follow the duplicate rule of the inbox.
        Files over {{ number_format($options['queue_above']) }} rows are imported in the background.
    </p>

    <div class="crm-imp-box" style="margin-bottom:16px;">
        <h4>Upload a file</h4>
        <form method="POST" action="{{ route('crm.imports.store') }}" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            @csrf
            <input type="file" name="file" required accept="{{ collect($extensions)->map(fn ($e) => '.' . $e)->implode(',') }}">
            <button class="crm-imp-btn crm-imp-btn--primary" type="submit"><i class="fa fa-upload"></i> Upload and check columns</button>
            <a href="{{ route('crm.imports.template') }}" class="pcat-link" style="font-size:12px;margin-left:auto;"><i class="fa fa-download"></i> Download the CSV template</a>
        </form>
        <p class="muted" style="font-size:12px;margin:8px 0 0;">.csv, .xlsx or .xls, up to 10 MB and {{ number_format($options['max_rows']) }} rows.</p>
    </div>

    @if($pending->isNotEmpty())
        <h4 class="crm-imp-title">Not run yet</h4>
        <table class="crm-imp-table" style="margin-bottom:16px;">
            <thead><tr><th>File</th><th>Rows</th><th>Uploaded</th><th></th></tr></thead>
            <tbody>
            @foreach($pending as $p)
                <tr>
                    <td>{{ $p->file_name }} <span class="crm-imp-tag">{{ \Modules\CRM\Models\LeadImport::formatLabel($p->format) }}</span></td>
                    <td>{{ number_format($p->total_rows) }}</td>
                    <td>{{ $p->created_at?->diffForHumans() }}</td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a href="{{ route('crm.imports.show', $p->id) }}" class="pcat-link">Continue</a>
                        <form method="POST" action="{{ route('crm.imports.destroy', $p->id) }}" style="display:inline;" onsubmit="return confirm('Discard this upload?');">
                            @csrf @method('DELETE')
                            <button class="crm-imp-btn" type="submit" style="margin:0 0 0 8px;">Discard</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h4 class="crm-imp-title">History</h4>
    @if($imports->isEmpty())
        <p class="muted" style="font-size:13px;">No imports yet.</p>
    @else
        <table class="crm-imp-table">
            <thead><tr><th>File</th><th>Pipeline</th><th>Status</th><th>New</th><th>Duplicates</th><th>Skipped</th><th>Failed</th><th>By</th><th>When</th><th></th></tr></thead>
            <tbody>
            @foreach($imports as $i)
                <tr>
                    <td><a href="{{ route('crm.imports.show', $i['id']) }}" class="pcat-link">{{ $i['file_name'] }}</a></td>
                    <td>{{ $i['project']['name'] ?? '—' }}</td>
                    <td><span class="crm-imp-status crm-imp-status--{{ $i['status'] }}">{{ ucfirst($i['status']) }}</span></td>
                    <td>{{ $i['created_count'] }}</td>
                    <td>{{ $i['duplicate_count'] }}</td>
                    <td>{{ $i['skipped_count'] }}</td>
                    <td>{{ $i['failed_count'] }}</td>
                    <td>{{ $i['user_name'] ?? '—' }}</td>
                    <td>{{ $i['created_at'] ? \Illuminate\Support\Carbon::parse($i['created_at'])->diffForHumans() : '' }}</td>
                    <td style="text-align:right;">
                        @if($i['has_error_rows'])
                            <a href="{{ route('crm.imports.errors', $i['id']) }}" class="pcat-link" style="font-size:12px;"><i class="fa fa-download"></i> Error rows</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
