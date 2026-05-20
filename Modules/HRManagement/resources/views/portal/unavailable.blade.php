@extends('theme::layouts.app', [
    'title' => __('HR portal'),
    'heading' => $heading ?? __('HR portal'),
    'employeePortal' => true,
    'portalEmployerBusiness' => $employee->business ?? null,
    'portalEmployee' => $employee,
    'portalEmployeeChoices' => $portalEmployeeChoices,
])

@section('content')
    <div class="card" style="max-width:560px;">
        <h2 style="margin-top:0;font-size:1.1rem;">{{ __('HR portal is not active') }}</h2>
        <p class="muted" style="margin-bottom:0;line-height:1.55;">
            {{ __('Human Resources self-service is not enabled for your employer yet. Please contact your HR or administrator.') }}
        </p>
    </div>
@endsection
