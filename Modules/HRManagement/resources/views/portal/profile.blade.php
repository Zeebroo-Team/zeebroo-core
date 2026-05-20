@extends('theme::layouts.app', [
    'title' => __('My profile'),
    'heading' => __('My profile'),
    'employeePortal' => true,
    'portalEmployerBusiness' => $portalEmployerBusiness,
    'portalEmployee' => $portalEmployee,
    'portalEmployeeChoices' => $portalEmployeeChoices,
])

@section('content')
<div style="margin-bottom:16px;">
    <a href="{{ route('hr.portal.dashboard') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i>{{ __('Back to HR portal') }}
    </a>
</div>

<div class="card" style="max-width:800px;">
    <p style="margin:0 0 16px;font-size:12px;color:var(--muted);">{{ __('Read-only summary from your employer’s HR records.') }}</p>
    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
        <div style="padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ __('Employer') }}</span>
            <p style="margin:6px 0 0;font-size:14px;font-weight:600;">{{ $employee->business?->name ?? '—' }}</p>
        </div>
        <div style="padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ __('Employee ID') }}</span>
            <p style="margin:6px 0 0;font-size:14px;font-weight:600;">{{ $employee->employee_id }}</p>
        </div>
        <div style="padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ __('Name') }}</span>
            <p style="margin:6px 0 0;font-size:14px;font-weight:600;">{{ $employee->full_name }}</p>
        </div>
        <div style="padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ __('Job title') }}</span>
            <p style="margin:6px 0 0;font-size:14px;font-weight:600;">{{ $employee->jobTitle?->name ?? '—' }}</p>
        </div>
        <div style="padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ __('Department') }}</span>
            <p style="margin:6px 0 0;font-size:14px;font-weight:600;">{{ $employee->department?->name ?? '—' }}</p>
        </div>
        <div style="padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ __('Work email on file') }}</span>
            <p style="margin:6px 0 0;font-size:14px;font-weight:600;"><a href="mailto:{{ $employee->personal_email }}">{{ $employee->personal_email }}</a></p>
        </div>
        <div style="padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ __('Phone') }}</span>
            <p style="margin:6px 0 0;font-size:14px;font-weight:600;">{{ $employee->phone_number }}</p>
        </div>
        <div style="padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);grid-column:1/-1;">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ __('Date of joining') }}</span>
            <p style="margin:6px 0 0;font-size:14px;font-weight:600;">{{ $employee->date_of_joining?->format('Y-m-d') ?? '—' }}</p>
        </div>
    </div>
</div>
@endsection
