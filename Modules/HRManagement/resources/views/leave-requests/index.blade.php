@extends('theme::layouts.app', ['title' => __('Leave requests · :name', ['name' => $business->name]), 'heading' => __('Human resources')])

@section('content')
<style>
.lr-page{max-width:100%;margin:0;}
.lr-head{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;}
.lr-head__title{margin:0;font-size:clamp(1.05rem,2vw,1.2rem);font-weight:800;letter-spacing:-.02em;}
.lr-head__hint{margin:6px 0 0;font-size:13px;line-height:1.45;color:var(--muted);max-width:64ch;}
.lr-table-wrap{margin-top:12px;border:1px solid var(--border);border-radius:11px;overflow:auto;}
.lr-table{width:100%;border-collapse:collapse;font-size:13px;min-width:720px;}
.lr-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card)92%,transparent);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);}
.lr-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border)82%,transparent);vertical-align:middle;color:var(--text);}
.lr-table tr:last-child td{border-bottom:none;}
.lr-table__emp{font-weight:700;}
.lr-table__emp a{color:var(--primary);text-decoration:none;}
.lr-table__emp a:hover{text-decoration:underline;}
.lr-table__muted{font-size:12px;color:var(--muted);}
.lr-form-inline{display:flex;flex-wrap:wrap;gap:6px;margin:0;}
.lr-btn{padding:6px 12px;font-size:11px;font-weight:600;border-radius:8px;cursor:pointer;font-family:inherit;border:1px solid var(--border);background:color-mix(in srgb,var(--card)94%,transparent);color:var(--text);}
.lr-btn--ok{border-color:color-mix(in srgb,#22c55e 35%,var(--border));color:color-mix(in srgb,#15803d 92%,var(--text));}
.lr-btn--no{border-color:color-mix(in srgb,var(--border)90%,transparent);color:var(--muted);}
.lr-pagination{margin-top:14px;display:flex;justify-content:flex-end;font-size:13px;color:var(--muted);}
.lr-empty{margin:14px 0;padding:clamp(18px,3vmin,24px);border-radius:11px;border:1px dashed color-mix(in srgb,var(--border)82%,var(--muted));color:var(--muted);font-size:14px;line-height:1.5;text-align:center;}
</style>

@php
    $leaveTypeLabels = [
        'annual' => __('Annual'),
        'casual' => __('Casual'),
        'sick' => __('Sick'),
        'unpaid' => __('Unpaid'),
        'other' => __('Other'),
    ];
@endphp

<div class="lr-page card" style="max-width:100%;padding:14px;">
    @if(session('status'))
        <div style="margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);font-size:13px;font-weight:600;">{{ session('status') }}</div>
    @endif

    <div class="lr-head">
        <div>
            <h1 class="lr-head__title">{{ __('Leave requests') }}</h1>
            <p class="lr-head__hint">{{ __('Pending approvals for :biz. Employees submit time off from their profile · Leave.', ['biz' => $business->name]) }}</p>
            <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;">
                <a href="{{ route('hr.index') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-layer-group" aria-hidden="true"></i>{{ __('HR hub') }}</a>
                <a href="{{ route('hr.employees.index') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;background:color-mix(in srgb,var(--card)94%,transparent);border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-users" aria-hidden="true"></i>{{ __('Employees') }}</a>
            </div>
        </div>
    </div>

    @if($leaveRequests->isEmpty())
        <p class="lr-empty">{{ __('No pending leave requests. When someone submits time off from an employee Leave tab, it will appear here.') }}</p>
    @else
        <div class="lr-table-wrap">
            <table class="lr-table">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Dates') }}</th>
                        <th>{{ __('Note') }}</th>
                        <th>{{ __('Requested') }}</th>
                        <th style="text-align:end;width:1%;white-space:nowrap;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaveRequests as $lr)
                        @php $empLe = $lr->employee; @endphp
                        <tr>
                            <td>
                                <div class="lr-table__emp">
                                    @if($empLe)
                                        <a href="{{ route('hr.employees.show', $empLe) }}#leave">{{ $empLe->full_name }}</a>
                                        <div class="lr-table__muted">{{ $empLe->employee_id }}</div>
                                    @else
                                        <span class="lr-table__muted">—</span>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $leaveTypeLabels[$lr->leave_type] ?? $lr->leave_type }}</td>
                            <td>
                                <span class="lr-table__muted">{{ $lr->starts_on?->format('Y-m-d') ?? '—' }}</span>
                                →
                                <span class="lr-table__muted">{{ $lr->ends_on?->format('Y-m-d') ?? '—' }}</span>
                            </td>
                            <td>@if(filled($lr->note))<span>{{ \Illuminate\Support\Str::limit($lr->note, 120) }}</span>@else<span class="lr-table__muted">—</span>@endif</td>
                            <td><span class="lr-table__muted">{{ $lr->created_at?->format('Y-m-d H:i') ?? '—' }}</span></td>
                            <td style="text-align:end;white-space:nowrap;">
                                <form method="post" action="{{ route('hr.leave-requests.update', $lr) }}" class="lr-form-inline" style="display:inline-flex;justify-content:flex-end;flex-wrap:nowrap;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" name="leave_status" value="{{ \Modules\HRManagement\Models\LeaveRequest::STATUS_APPROVED }}" class="lr-btn lr-btn--ok">{{ __('Approve') }}</button>
                                    <button type="submit" name="leave_status" value="{{ \Modules\HRManagement\Models\LeaveRequest::STATUS_REJECTED }}" class="lr-btn lr-btn--no">{{ __('Reject') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($leaveRequests->hasPages())
            <div class="lr-pagination">{{ $leaveRequests->links() }}</div>
        @endif
    @endif
</div>
@endsection
