@extends('theme::layouts.app', ['title' => 'Import Customers', 'heading' => 'Import Customers'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('crm::imports.partials.styles')

@php
    $statusClass = ['create' => 'ok', 'update' => 'duplicate', 'skip' => 'queued', 'error' => 'error'];
    $statusLabel = ['create' => 'New', 'update' => 'Update', 'skip' => 'Skip', 'error' => 'Error'];
@endphp

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    @if($step === 'upload')
        <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
            Add customers in bulk from a CSV or Excel file. Every row is checked before anything is saved:
            a row whose phone number or email already belongs to a customer is a duplicate, and you choose
            whether duplicates are skipped or update the existing customer.
        </p>

        <div class="crm-imp-box">
            <h4>1. Upload a file</h4>
            <form method="POST" action="{{ route('crm.contacts.import.store') }}" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                @csrf
                <input type="file" name="file" required accept="{{ collect($extensions)->map(fn ($e) => '.' . $e)->implode(',') }}">
                <button class="crm-imp-btn crm-imp-btn--primary" type="submit"><i class="fa fa-upload"></i> Upload and preview</button>
                <a href="{{ route('crm.contacts.import.template') }}" class="pcat-link" style="font-size:12px;margin-left:auto;"><i class="fa fa-download"></i> Download the CSV template</a>
            </form>
            <p class="muted" style="font-size:12px;margin:8px 0 0;">
                Columns: <strong>name</strong> (required), {{ implode(', ', array_slice($options['columns'], 1)) }}.
                .csv, .xlsx or .xls, up to 10 MB and {{ number_format($options['max_rows']) }} rows.
            </p>
        </div>
    @elseif($step === 'preview')
        @php $t = $preview['totals']; @endphp
        <div class="crm-imp-box" style="margin-bottom:14px;">
            <h4>2. Preview — nothing is saved yet</h4>
            <p class="muted" style="font-size:12px;margin:0 0 10px;">{{ $upload['file_name'] }} · {{ number_format($upload['rows']) }} rows</p>

            <form method="GET" action="{{ route('crm.contacts.import.preview', $token) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px;">
                <div>
                    <label>When the phone or email is already a customer</label>
                    <select name="duplicates" onchange="this.form.submit()">
                        @foreach($options['duplicate_modes'] as $m)
                            <option value="{{ $m['key'] }}" @selected($mode === $m['key'])>{{ $m['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button class="crm-imp-btn" type="submit">Check again</button></noscript>
            </form>

            <div class="crm-imp-totals">
                <span>{{ $t['total'] }} rows</span>
                <span class="crm-imp-status--ok">{{ $t['create'] }} new</span>
                <span class="crm-imp-status--duplicate">{{ $t['update'] }} updates</span>
                <span>{{ $t['skip'] }} skipped</span>
                <span class="crm-imp-status--error">{{ $t['error'] }} errors</span>
            </div>
            <div class="crm-imp-wrap" style="max-height:420px;overflow-y:auto;">
                <table class="crm-imp-table">
                    <thead><tr><th>Row</th><th>Name</th><th>Phone</th><th>Email</th><th>Status</th><th>Details</th></tr></thead>
                    <tbody>
                    @foreach(array_slice($preview['rows'], 0, 1000) as $r)
                        <tr>
                            <td>{{ $r['row'] }}</td>
                            <td>{{ $r['name'] ?? '—' }}</td>
                            <td>{{ $r['phone'] ?? '' }}</td>
                            <td>{{ $r['email'] ?? '' }}</td>
                            <td><span class="crm-imp-status crm-imp-status--{{ $statusClass[$r['status']] }}">{{ $statusLabel[$r['status']] }}</span></td>
                            <td style="font-size:12px;">
                                {{ $r['reason'] }}
                                @if($r['customer_id'])
                                    <a href="{{ route('crm.contacts.show', $r['customer_id']) }}" class="pcat-link" target="_blank">Open</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if(count($preview['rows']) > 1000)
                <p class="muted" style="font-size:12px;">Showing the first 1,000 rows; all {{ number_format(count($preview['rows'])) }} are imported.</p>
            @endif

            <form method="POST" action="{{ route('crm.contacts.import.run', $token) }}"
                  onsubmit="return confirm('Import {{ $t['create'] }} new and update {{ $t['update'] }} existing customers? Skipped rows and rows with errors are left out.');">
                @csrf
                <input type="hidden" name="duplicates" value="{{ $mode }}">
                <button class="crm-imp-btn crm-imp-btn--primary" type="submit" @disabled($t['create'] + $t['update'] === 0)>
                    <i class="fa fa-file-import"></i> 3. Run import
                </button>
            </form>
        </div>

        <form method="POST" action="{{ route('crm.contacts.import.destroy', $token) }}" onsubmit="return confirm('Discard this upload?');">
            @csrf @method('DELETE')
            <button class="crm-imp-btn" type="submit"><i class="fa fa-trash"></i> Discard upload</button>
        </form>
    @else
        @php $t = $upload['result']['totals']; @endphp
        <div class="crm-imp-box">
            <h4>Result</h4>
            <p class="muted" style="font-size:12px;margin:0 0 8px;">{{ $upload['file_name'] }} · duplicates {{ $upload['result']['mode'] === 'update' ? 'updated the existing customers' : 'skipped' }}</p>
            <div class="crm-imp-totals">
                <span class="crm-imp-status--ok">{{ $t['create'] }} new customers</span>
                <span class="crm-imp-status--duplicate">{{ $t['update'] }} updated</span>
                <span>{{ $t['skip'] }} skipped</span>
                <span class="crm-imp-status--error">{{ $t['error'] }} failed</span>
            </div>
            @if($upload['result']['errors_path'])
                <a href="{{ route('crm.contacts.import.errors', $token) }}" class="crm-imp-btn" style="display:inline-block;text-decoration:none;">
                    <i class="fa fa-download"></i> Download the rows not imported (CSV)
                </a>
            @endif
            <a href="{{ route('crm.contacts.index') }}" class="pcat-link" style="margin-left:10px;font-size:12px;">Open contacts <i class="fa fa-arrow-right"></i></a>
            <a href="{{ route('crm.contacts.import.index') }}" class="pcat-link" style="margin-left:10px;font-size:12px;">Import another file</a>
        </div>
    @endif
</div>
@endsection
