@extends('theme::layouts.app', [
    'title' => __('Complaints'),
    'heading' => __('Complaints'),
    'employeePortal' => true,
    'portalEmployerBusiness' => $portalEmployerBusiness,
    'portalEmployee' => $portalEmployee,
    'portalEmployeeChoices' => $portalEmployeeChoices,
])

@section('content')
@php
    $complaintStatusLabels = [
        \Modules\HRManagement\Models\HrComplaint::STATUS_OPEN => __('Open'),
        \Modules\HRManagement\Models\HrComplaint::STATUS_RESOLVED => __('Resolved'),
        \Modules\HRManagement\Models\HrComplaint::STATUS_DISMISSED => __('Dismissed'),
    ];
@endphp

@if(session('status'))
    <p class="emp-show__flash" role="status" style="max-width:920px;">{{ session('status') }}</p>
@endif

<div style="margin-bottom:16px;">
    <a href="{{ route('hr.portal.dashboard') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i>{{ __('Back to HR portal') }}
    </a>
</div>

<div class="card" style="max-width:920px;margin-bottom:18px;">
    <h2 style="margin:0 0 10px;font-size:1rem;font-weight:700;">{{ __('Log a complaint') }}</h2>
    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">{{ __('Describe your concern. HR will see this under your current employer.') }}</p>
    <form method="post" action="{{ route('hr.portal.complaints.store') }}" style="display:grid;gap:12px;">
        @csrf
        <div>
            <label for="complaint_subject" style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;color:var(--muted);">{{ __('Subject') }}</label>
            <input type="text" name="subject" id="complaint_subject" value="{{ old('subject') }}" required maxlength="255"
                style="width:100%;max-width:480px;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:14px;">
            @error('subject')
                <p style="margin:6px 0 0;font-size:13px;color:#dc2626;">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="complaint_body" style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;color:var(--muted);">{{ __('Details') }}</label>
            <textarea name="body" id="complaint_body" rows="5" required maxlength="10000"
                style="width:100%;max-width:640px;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:14px;line-height:1.45;">{{ old('body') }}</textarea>
            @error('body')
                <p style="margin:6px 0 0;font-size:13px;color:#dc2626;">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <button type="submit" class="linkbtn" style="border:none;cursor:pointer;">{{ __('Submit complaint') }}</button>
        </div>
    </form>
</div>

<div class="card" style="max-width:920px;">
    <h2 style="margin:0 0 14px;font-size:1rem;font-weight:700;">{{ __('Your complaints') }}</h2>
    @if($complaints->isEmpty())
        <p class="muted" style="margin:0;">{{ __('No complaints logged yet.') }}</p>
    @else
        <div style="overflow:auto;border:1px solid var(--border);border-radius:10px;">
            <table class="emp-docs-table" style="min-width:520px;">
                <thead>
                    <tr>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Subject') }}</th>
                        <th>{{ __('Submitted') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($complaints as $c)
                        <tr>
                            <td>
                                @php
                                    $pill = match ($c->status) {
                                        \Modules\HRManagement\Models\HrComplaint::STATUS_OPEN => 'emp-docs-pill--pending',
                                        \Modules\HRManagement\Models\HrComplaint::STATUS_RESOLVED => 'emp-docs-pill--approved',
                                        default => 'emp-docs-pill--rejected',
                                    };
                                @endphp
                                <span class="emp-docs-pill {{ $pill }}">{{ $complaintStatusLabels[$c->status] ?? $c->status }}</span>
                            </td>
                            <td>
                                <strong style="font-size:13px;">{{ $c->subject }}</strong>
                                <p class="muted" style="margin:6px 0 0;font-size:12px;line-height:1.4;max-width:42ch;">{{ \Illuminate\Support\Str::limit(strip_tags($c->body), 140) }}</p>
                            </td>
                            <td><span class="emp-docs-table__meta">{{ $c->created_at?->format('Y-m-d H:i') ?? '—' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="emp-portal-pagination" style="margin-top:14px;">{{ $complaints->links() }}</div>
    @endif
</div>

<style>
    .emp-docs-pill{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:2px 8px;border-radius:999px;white-space:nowrap;display:inline-block;}
    .emp-docs-pill--pending{color:#b45309;background:color-mix(in srgb,#b45309 12%,transparent);border:1px solid color-mix(in srgb,#b45309 28%,var(--border));}
    .emp-docs-pill--approved{color:#15803d;background:color-mix(in srgb,#22c55e 11%,transparent);border:1px solid color-mix(in srgb,#22c55e 30%,var(--border));}
    .emp-docs-pill--rejected{color:var(--muted);background:color-mix(in srgb,var(--card)92%,transparent);border:1px solid var(--border);}
    .emp-docs-table{width:100%;border-collapse:collapse;font-size:13px;}
    .emp-docs-table th{text-align:left;padding:8px 10px;background:color-mix(in srgb,var(--card)92%,transparent);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);border-bottom:1px solid var(--border);}
    .emp-docs-table td{padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border)82%,transparent);vertical-align:top;}
    .emp-docs-table tr:last-child td{border-bottom:none;}
    .emp-docs-table__meta{font-size:11px;color:var(--muted);}
    .emp-portal-pagination nav{display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:13px;}
    .emp-portal-pagination a,.emp-portal-pagination span{padding:4px 8px;border-radius:8px;border:1px solid var(--border);text-decoration:none;color:var(--text);}
</style>
@endsection
