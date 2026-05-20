@extends('theme::layouts.app', [
    'title' => __('My leaves'),
    'heading' => __('My leaves'),
    'employeePortal' => true,
    'portalEmployerBusiness' => $portalEmployerBusiness,
    'portalEmployee' => $portalEmployee,
    'portalEmployeeChoices' => $portalEmployeeChoices,
])

@section('content')
@php
    $leaveTypeLabels = [
        'annual' => __('Annual'),
        'casual' => __('Casual'),
        'sick' => __('Sick'),
        'unpaid' => __('Unpaid'),
        'other' => __('Other'),
    ];
    $leaveStatusLabels = [
        \Modules\HRManagement\Models\LeaveRequest::STATUS_PENDING => __('Pending'),
        \Modules\HRManagement\Models\LeaveRequest::STATUS_APPROVED => __('Approved'),
        \Modules\HRManagement\Models\LeaveRequest::STATUS_REJECTED => __('Rejected'),
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

<div class="card" style="max-width:920px;">
    <h2 style="margin:0 0 14px;font-size:1rem;font-weight:700;">{{ __('Leave requests') }}</h2>
    @if($leaveRequests->isEmpty())
        <p class="muted" style="margin:0;">{{ __('No leave requests yet.') }}</p>
    @else
        <div style="overflow:auto;border:1px solid var(--border);border-radius:10px;">
            <table class="emp-docs-table" style="min-width:520px;">
                <thead>
                    <tr>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('From') }}</th>
                        <th>{{ __('To') }}</th>
                        <th>{{ __('Submitted') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaveRequests as $lr)
                        <tr>
                            <td>
                                @php
                                    $pill = match ($lr->status) {
                                        \Modules\HRManagement\Models\LeaveRequest::STATUS_PENDING => 'emp-docs-pill--pending',
                                        \Modules\HRManagement\Models\LeaveRequest::STATUS_APPROVED => 'emp-docs-pill--approved',
                                        default => 'emp-docs-pill--rejected',
                                    };
                                @endphp
                                <span class="emp-docs-pill {{ $pill }}">{{ $leaveStatusLabels[$lr->status] ?? $lr->status }}</span>
                            </td>
                            <td>{{ $leaveTypeLabels[$lr->leave_type] ?? $lr->leave_type }}</td>
                            <td><span class="emp-docs-table__meta">{{ $lr->starts_on?->format('Y-m-d') ?? '—' }}</span></td>
                            <td><span class="emp-docs-table__meta">{{ $lr->ends_on?->format('Y-m-d') ?? '—' }}</span></td>
                            <td><span class="emp-docs-table__meta">{{ $lr->created_at?->format('Y-m-d H:i') ?? '—' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="emp-portal-pagination" style="margin-top:14px;">{{ $leaveRequests->links() }}</div>
    @endif
</div>

<style>
    .emp-docs-pill{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:2px 8px;border-radius:999px;white-space:nowrap;display:inline-block;}
    .emp-docs-pill--pending{color:#b45309;background:color-mix(in srgb,#b45309 12%,transparent);border:1px solid color-mix(in srgb,#b45309 28%,var(--border));}
    .emp-docs-pill--approved{color:#15803d;background:color-mix(in srgb,#22c55e 11%,transparent);border:1px solid color-mix(in srgb,#22c55e 30%,var(--border));}
    .emp-docs-pill--rejected{color:var(--muted);background:color-mix(in srgb,var(--card)92%,transparent);border:1px solid var(--border);}
    .emp-docs-table{width:100%;border-collapse:collapse;font-size:13px;}
    .emp-docs-table th{text-align:left;padding:8px 10px;background:color-mix(in srgb,var(--card)92%,transparent);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);border-bottom:1px solid var(--border);}
    .emp-docs-table td{padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border)82%,transparent);vertical-align:middle;}
    .emp-docs-table tr:last-child td{border-bottom:none;}
    .emp-docs-table__meta{font-size:11px;color:var(--muted);}
    .emp-portal-pagination nav{display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:13px;}
    .emp-portal-pagination a,.emp-portal-pagination span{padding:4px 8px;border-radius:8px;border:1px solid var(--border);text-decoration:none;color:var(--text);}
</style>
@endsection
