@extends('theme::layouts.app', [
    'title' => __('HR portal'),
    'heading' => __('HR portal'),
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

@php
    $pf = $portalFeatures ?? ['leaves' => true, 'complaints' => true, 'salary' => true, 'pos_online' => true];
@endphp

@if(session('status'))
    <p class="emp-show__flash" role="status" style="max-width:920px;">{{ session('status') }}</p>
@endif
@if($errors->has('access'))
    <p class="emp-portal-access-err" role="alert" style="max-width:920px;">{{ $errors->first('access') }}</p>
@endif

<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;max-width:920px;">
    <a href="{{ route('hr.portal.profile') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-id-card"></i>{{ __('My profile') }}
    </a>
    @if($pf['leaves'] ?? true)
        <a href="{{ route('hr.portal.leaves') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-calendar-days"></i>{{ __('My leaves') }}
        </a>
    @endif
    @if($pf['complaints'] ?? true)
        <a href="{{ route('hr.portal.complaints') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-comment-dots"></i>{{ __('Complaints') }}
        </a>
    @endif
    @if($pf['salary'] ?? true)
        <a href="{{ route('hr.portal.salary') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-money-bill-wave"></i>{{ __('My salary') }}
        </a>
    @endif
    @if($pf['pos_online'] ?? false)
        <a href="{{ route('hr.portal.pos-online') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-store"></i>{{ __('POS Online') }}
        </a>
    @endif
</div>

@if($pf['leaves'] ?? true)
<div class="card" style="max-width:920px;">
    <h2 style="margin:0 0 14px;font-size:1rem;font-weight:700;">{{ __('Your recent leave requests') }}</h2>
    @if($employee->leaveRequests->isEmpty())
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
                    @foreach($employee->leaveRequests as $lr)
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
    @endif
</div>
@endif

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
    .emp-portal-access-err{margin:0 0 14px;padding:10px 12px;border-radius:10px;font-size:13px;border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);}
</style>
@endsection
