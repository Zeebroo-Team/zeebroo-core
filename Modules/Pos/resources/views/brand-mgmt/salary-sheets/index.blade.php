@extends('theme::layouts.app', ['title' => 'Salary Sheets', 'heading' => 'Salary Sheets'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('pos::brand-mgmt.partials.crud-styles')
<style>
.sa-stats{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:18px;}
.sa-stat{flex:1;min-width:120px;border:1px solid var(--border);border-radius:12px;padding:12px 14px;background:var(--card);}
.sa-stat__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:0 0 3px;}
.sa-stat__value{font-size:20px;font-weight:800;color:var(--text);margin:0;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.brand-mgmt-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    @php
        $statusCounts = ['draft' => 0, 'completed' => 0, 'approved' => 0, 'rejected' => 0, 'paid' => 0];
        foreach ($sheets as $s) { if (isset($statusCounts[$s['status']])) $statusCounts[$s['status']]++; }
    @endphp
    <div class="sa-stats">
        <div class="sa-stat"><p class="sa-stat__label">Total</p><p class="sa-stat__value">{{ $sheets->count() }}</p></div>
        <div class="sa-stat"><p class="sa-stat__label">Draft</p><p class="sa-stat__value">{{ $statusCounts['draft'] }}</p></div>
        <div class="sa-stat"><p class="sa-stat__label">Awaiting approval</p><p class="sa-stat__value">{{ $statusCounts['completed'] }}</p></div>
        <div class="sa-stat"><p class="sa-stat__label">Approved</p><p class="sa-stat__value">{{ $statusCounts['approved'] }}</p></div>
        <div class="sa-stat"><p class="sa-stat__label">Paid</p><p class="sa-stat__value">{{ $statusCounts['paid'] }}</p></div>
    </div>

    <div class="bmg-toolbar">
        <form method="get" action="{{ route('pos.brand-mgmt.salary-sheets.index') }}" class="bmg-search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search ref, location, job…" autocomplete="off">
            <button type="submit" class="linkbtn" style="padding:8px 10px;font-size:13px;"><i class="fa fa-search"></i></button>
            @if($search)
                <a href="{{ route('pos.brand-mgmt.salary-sheets.index') }}" class="linkbtn" style="padding:8px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
            @endif
        </form>
        <a href="{{ route('pos.brand-mgmt.salary-sheets.create') }}" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
            <i class="fa fa-plus"></i> New salary sheet
        </a>
    </div>

    <div class="bmg-table-wrap">
        @if($sheets->isEmpty())
            <div class="bmg-empty">
                <i class="fa fa-money-check-dollar" aria-hidden="true"></i>
                <p>{{ $search ? 'No salary sheets matched "'.e($search).'".' : 'No salary sheets yet. Create your first one to get started.' }}</p>
            </div>
        @else
        <table class="bmg-table">
            <thead>
                <tr><th>Ref</th><th>Job</th><th>Location</th><th>Date range</th><th>Rows</th><th>Status</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @foreach($sheets as $sheet)
                <tr>
                    <td><span class="bmg-badge">{{ $sheet['sheet_ref'] }}</span></td>
                    <td style="font-size:12px;">{{ $sheet['job_name'] ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $sheet['location'] ?: '—' }}</td>
                    <td style="font-size:12px;">{{ $sheet['date_from'] ? $sheet['date_from'].' — '.$sheet['date_to'] : '—' }}</td>
                    <td>{{ $sheet['rows_count'] }}</td>
                    <td><span class="bmg-badge bmg-badge--{{ $sheet['status'] }}">{{ ucfirst($sheet['status']) }}</span></td>
                    <td style="text-align:right;">
                        <div class="bmg-actions">
                            <a href="{{ route('pos.brand-mgmt.salary-sheets.show', $sheet['id']) }}" class="bmg-action-btn bmg-action-btn--edit"><i class="fa fa-eye"></i> Open</a>
                            @if($sheet['status'] === 'draft')
                            <form method="post" action="{{ route('pos.brand-mgmt.salary-sheets.destroy', $sheet['id']) }}" onsubmit="return confirm('Delete salary sheet {{ $sheet['sheet_ref'] }}?');" style="margin:0;">
                                @csrf @method('DELETE')
                                <button type="submit" class="bmg-action-btn bmg-action-btn--del" title="Delete"><i class="fa fa-trash-can"></i></button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
