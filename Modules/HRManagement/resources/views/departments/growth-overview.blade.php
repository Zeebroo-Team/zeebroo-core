@extends('theme::layouts.app', [
    'title' => __('Department growth · :name', ['name' => $business->name]),
    'heading' => __('Department employee growth'),
])

@section('content')
<div class="card" style="max-width:none;padding:16px 18px;">
    <p style="margin:0 0 14px;font-size:13px;line-height:1.5;color:var(--muted);">
        <a href="{{ route('hr.departments.index') }}" style="color:var(--primary);font-weight:600;text-decoration:none;"><i class="fa fa-arrow-left" style="margin-right:6px;"></i>{{ __('Departments') }}</a>
        <span aria-hidden="true" style="opacity:.5;">·</span>
        <span>{{ __('Monthly cumulative headcount per team for :business.', ['business' => $business->name]) }}</span>
    </p>

    <div style="border-radius:14px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);padding:16px 14px 18px;">
        @if(! $chart['hasData'])
            <p class="muted" style="margin:0;line-height:1.55;font-size:14px;">
                {{ __('Once people are registered with hire dates, this chart shows how each department grows month by month.') }}
            </p>
            <div style="margin-top:16px;">
                <a href="{{ route('hr.employees.index') }}" class="linkbtn" style="padding:9px 18px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                    <i class="fa fa-user-plus"></i>{{ __('Go to Employees') }}
                </a>
            </div>
        @else
            <p class="muted" style="margin:0 0 12px;font-size:12px;line-height:1.45;max-width:72ch;">{{ $chart['note'] }}</p>
            @include('hrmanagement::departments.partials.hr-line-chart', [
                'canvasId' => 'dept-growth-chart',
                'chartAriaLabel' => __('Department headcount chart'),
                'chartLabels' => $chart['labels'],
                'chartDatasets' => $chart['datasets'],
                'chartWrapStyle' => 'position:relative;height:min(420px,62vh);width:100%;',
            ])
            @if(count($chart['labels']) > 0)
                <p class="muted" style="margin:14px 0 0;text-align:center;font-size:11px;">{{ __(':count months on the timeline.', ['count' => count($chart['labels'])]) }}</p>
            @endif
        @endif
    </div>

    <div style="margin-top:14px;">
        <a href="{{ route('hr.index') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-table-list"></i>{{ __('HR hub') }}
        </a>
    </div>
</div>

@endsection
