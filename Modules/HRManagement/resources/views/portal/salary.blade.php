@extends('theme::layouts.app', [
    'title' => __('My salary'),
    'heading' => __('My salary'),
    'employeePortal' => true,
    'portalEmployerBusiness' => $portalEmployerBusiness,
    'portalEmployee' => $portalEmployee,
    'portalEmployeeChoices' => $portalEmployeeChoices,
])

@section('content')
@php
    $fmt = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $allowancesTotal = $employee->employeeAllowances->sum(fn ($a) => (float) $a->amount);
@endphp

<div style="margin-bottom:16px;">
    <a href="{{ route('hr.portal.dashboard') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i>{{ __('Back to HR portal') }}
    </a>
</div>

<div class="card" style="max-width:800px;">
    <p style="margin:0 0 16px;font-size:12px;color:var(--muted);">{{ __('Summary from payroll records for your current employer. Contact HR for questions.') }}</p>

    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
        <div style="padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ __('Basic salary') }}</span>
            <p style="margin:6px 0 0;font-size:15px;font-weight:700;">{{ $fmt($employee->basic_salary) }}</p>
        </div>
        <div style="padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ __('Salary (total)') }}</span>
            <p style="margin:6px 0 0;font-size:15px;font-weight:700;">{{ $fmt($employee->salary) }}</p>
        </div>
        <div style="padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)92%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);grid-column:1/-1;">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ __('Allowances') }}</span>
            @if($employee->employeeAllowances->isEmpty())
                <p style="margin:8px 0 0;font-size:13px;color:var(--muted);">{{ __('No allowances on file.') }}</p>
            @else
                <ul style="margin:8px 0 0;padding:0;list-style:none;display:grid;gap:8px;">
                    @foreach($employee->employeeAllowances as $ea)
                        <li style="display:flex;justify-content:space-between;gap:12px;font-size:14px;border-bottom:1px dashed color-mix(in srgb,var(--border)70%,transparent);padding-bottom:8px;">
                            <span>{{ $ea->allowanceType?->name ?? __('Allowance') }}</span>
                            <strong>{{ $fmt($ea->amount) }}</strong>
                        </li>
                    @endforeach
                </ul>
                <p style="margin:12px 0 0;font-size:13px;color:var(--muted);">
                    {{ __('Allowances subtotal') }}: <strong style="color:var(--text);">{{ $fmt($allowancesTotal) }}</strong>
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
