@extends('theme::layouts.app', [
    'title' => __('Designation · :name', ['name' => $jobTitle->name]),
    'heading' => __('Designation'),
])

@section('content')
@php
    $tab = $activeTab ?? 'overview';
@endphp
<style>
    .jt-show{max-width:100%;margin:0;}
    .jt-show__head{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;margin-bottom:14px;}
    .jt-show__title{margin:0;font-size:clamp(1.15rem,2.2vw,1.35rem);font-weight:800;}
    .jt-show__muted{margin:6px 0 0;font-size:13px;line-height:1.45;color:var(--muted);max-width:56ch;}
    .jt-tabs{display:flex;gap:6px;margin:0 0 16px;flex-wrap:wrap;border-bottom:1px solid var(--border);padding-bottom:10px;}
    .jt-tabs a{padding:9px 14px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600;color:var(--muted);border:1px solid transparent;}
    .jt-tabs a:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary) 28%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
    .jt-tabs a.active{color:var(--text);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);}
    .jt-panel{display:none;}
    .jt-panel.is-active{display:block;}
    .jt-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:14px;}
    .jt-stat{border-radius:14px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 96%,transparent);padding:14px 16px;}
    .jt-stat dt{margin:0;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:700;}
    .jt-stat dd{margin:6px 0 0;font-size:22px;font-weight:820;color:var(--text);}
    .jt-sub{font-size:15px;font-weight:750;margin:16px 0 8px;}
    .jt-table-wrap{border:1px solid var(--border);border-radius:11px;overflow:auto;}
    .jt-table{width:100%;border-collapse:collapse;font-size:13px;min-width:400px;}
    .jt-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);}
    .jt-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 82%,transparent);vertical-align:top;}
    .jt-table tr:last-child td{border-bottom:none;}
    .jt-mgmt-card{border-radius:14px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 98%,transparent);padding:16px 18px;margin-bottom:16px;}
    .jt-back{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--primary);font-weight:600;text-decoration:none;margin-bottom:12px;}
    .jt-back:hover{text-decoration:underline;}
    .jt-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px;}
    .jt-field input{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
    .jt-banner{margin:0 0 12px;padding:10px 12px;border-radius:10px;font-size:13px;}
    .jt-banner--ok{border:1px solid color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);font-weight:600;}
    .jt-banner--err{border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);}
    .jt-banner--warn{border:1px solid color-mix(in srgb,#f59e0b 40%,var(--border));background:color-mix(in srgb,#f59e0b 8%,transparent);}
    .jt-dept-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:650;background:color-mix(in srgb,var(--primary) 11%,transparent);color:var(--primary);border:1px solid color-mix(in srgb,var(--primary) 24%,var(--border));white-space:nowrap;}
    .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
    .jt-del-section{border-radius:12px;border:1px solid color-mix(in srgb,#f87171 35%,var(--border));background:color-mix(in srgb,#f87171 5%,transparent);padding:14px 16px;}
    .jt-feature-row:has(input:checked){border-color:color-mix(in srgb,var(--primary) 35%,var(--border));background:color-mix(in srgb,var(--primary) 6%,transparent);}
    .jt-feature-row:hover{border-color:color-mix(in srgb,var(--primary) 28%,var(--border));}
</style>

<div class="jt-show card" style="max-width:100%;padding:14px 16px;">
    <a href="{{ route('hr.job-titles.index') }}" class="jt-back"><i class="fa fa-arrow-left" aria-hidden="true"></i>{{ __('Designations catalogue') }}</a>

    @if(session('status'))
        <div class="jt-banner jt-banner--ok">{{ session('status') }}</div>
    @endif

    <div class="jt-show__head">
        <div>
            <h1 class="jt-show__title">{{ $jobTitle->name }}</h1>
            <p class="jt-show__muted">{{ __(':business · Employees holding this designation and management options.', ['business' => $business->name]) }}</p>
        </div>
        <span class="muted" style="font-size:13px;line-height:1.4;">
            <i class="fa fa-users" aria-hidden="true"></i>
            @if($employees->isEmpty())
                {{ __('No employees yet') }}
            @else
                {{ trans_choice(':count employee|:count employees', $employees->count(), ['count' => $employees->count()]) }}
            @endif
        </span>
    </div>

    @php
        $overviewUrl  = route('hr.job-titles.show', $jobTitle);
        $employeesUrl = route('hr.job-titles.show', ['jobTitle' => $jobTitle, 'tab' => 'employees']);
    @endphp

    <nav class="jt-tabs" aria-label="{{ __('Designation sections') }}">
        <a href="{{ $overviewUrl }}" @class(['active' => $tab === 'overview'])>{{ __('Overview') }}</a>
        <a href="{{ $employeesUrl }}" @class(['active' => $tab === 'employees'])>
            {{ __('Employees') }}
            @if($employees->isNotEmpty())
                <span style="margin-left:5px;padding:1px 7px;border-radius:20px;font-size:10px;font-weight:700;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);">{{ $employees->count() }}</span>
            @endif
        </a>
    </nav>

    {{-- OVERVIEW TAB --}}
    <div id="jt-tab-overview" class="jt-panel @if($tab === 'overview') is-active @endif">

        <div class="jt-stat-grid" role="region" aria-labelledby="jt-overview-heading">
            <h2 id="jt-overview-heading" class="sr-only">{{ __('Summary') }}</h2>
            <dl class="jt-stat">
                <dt>{{ __('Employees') }}</dt>
                <dd>{{ $employees->count() }}</dd>
            </dl>
            <dl class="jt-stat">
                <dt>{{ __('Departments') }}</dt>
                <dd>{{ $employees->pluck('department.id')->filter()->unique()->count() }}</dd>
            </dl>
        </div>

        @php
            $deptGroups = $employees->groupBy(fn ($e) => $e->department?->name ?? __('No department'));
        @endphp
        @if($deptGroups->isNotEmpty())
            <h2 class="jt-sub">{{ __('Department breakdown') }}</h2>
            <div class="jt-stat-grid">
                @foreach($deptGroups->sortKeys() as $deptName => $deptEmployees)
                    <dl class="jt-stat">
                        <dt>{{ $deptName }}</dt>
                        <dd style="font-size:18px;">{{ $deptEmployees->count() }}</dd>
                    </dl>
                @endforeach
            </div>
        @endif

        <h2 class="jt-sub">{{ __('Recent employees') }}</h2>
        @if($employees->isEmpty())
            <p class="muted" style="margin:0 0 16px;line-height:1.5;font-size:13px;">{{ __('No employees hold this designation yet.') }}</p>
        @else
            @php
                $recentEmployees = $employees->sortByDesc('date_of_joining')->take(5);
            @endphp
            <div class="jt-table-wrap">
                <table class="jt-table">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Department') }}</th>
                            <th>{{ __('Joined') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentEmployees as $emp)
                            <tr>
                                <td>
                                    <a href="{{ route('hr.employees.show', $emp) }}" style="color:var(--primary);font-weight:650;text-decoration:none;">{{ $emp->full_name }}</a>
                                </td>
                                <td>
                                    @if($emp->department)
                                        <span class="jt-dept-pill">{{ $emp->department->name }}</span>
                                    @else
                                        <span class="muted" style="font-size:12px;">—</span>
                                    @endif
                                </td>
                                <td class="muted" style="white-space:nowrap;">{{ $emp->date_of_joining?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($employees->count() > 5)
                <p style="margin:10px 0 0;font-size:12px;">
                    <a href="{{ $employeesUrl }}" style="color:var(--primary);font-weight:600;text-decoration:none;">{{ __('View all :count employees →', ['count' => $employees->count()]) }}</a>
                </p>
            @endif
        @endif

        <h2 class="jt-sub" style="margin-top:20px;">{{ __('Portal access') }}</h2>
        <div class="jt-mgmt-card">
            <p style="margin:0 0 12px;font-size:13px;line-height:1.5;color:var(--muted);">{{ __('Choose which HR portal features employees with this designation can access. All features are enabled by default.') }}</p>
            @if(session('status') === 'Portal access updated.')
                {{-- status banner already shown above --}}
            @endif
            <form method="post" action="{{ route('hr.job-titles.portal-features.update', $jobTitle) }}">
                @csrf
                @php
                    $allFeatures = [
                        'leaves'     => ['label' => __('Leave requests'), 'desc' => __('Employees can view and submit their leave requests.'), 'icon' => 'fa-calendar-days'],
                        'complaints' => ['label' => __('Complaints'),     'desc' => __('Employees can file and track their complaints.'),       'icon' => 'fa-comment-dots'],
                        'salary'     => ['label' => __('Salary & payslip'), 'desc' => __('Employees can view their salary and allowances.'),    'icon' => 'fa-money-bill-wave'],
                        'pos_online' => ['label' => __('POS Online'),     'desc' => __('Employees can process sales via the online POS terminal.'), 'icon' => 'fa-store'],
                    ];
                @endphp
                <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:16px;">
                    @foreach($allFeatures as $featureKey => $meta)
                        @php
                            $isEnabled = $jobTitle->hasPortalFeature($featureKey);
                        @endphp
                        <label style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);cursor:pointer;" class="jt-feature-row">
                            <input type="checkbox" name="portal_features[]" value="{{ $featureKey }}"
                                @checked($isEnabled)
                                style="width:16px;height:16px;margin-top:2px;flex-shrink:0;accent-color:var(--primary);">
                            <span style="display:flex;align-items:flex-start;gap:10px;flex:1;min-width:0;">
                                <span style="width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:color-mix(in srgb,var(--primary) 11%,transparent);color:var(--primary);font-size:14px;">
                                    <i class="fa {{ $meta['icon'] }}" aria-hidden="true"></i>
                                </span>
                                <span>
                                    <span style="display:block;font-size:13px;font-weight:700;color:var(--text);">{{ $meta['label'] }}</span>
                                    <span style="display:block;font-size:12px;line-height:1.45;color:var(--muted);margin-top:2px;">{{ $meta['desc'] }}</span>
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div style="display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">{{ __('Save access') }}</button>
                </div>
            </form>
        </div>

        <h2 class="jt-sub" style="margin-top:4px;">{{ __('Rename designation') }}</h2>
        <div class="jt-mgmt-card">
            @if($errors->has('name'))
                <p class="jt-banner jt-banner--err" role="alert" style="margin-bottom:12px;">{{ $errors->first('name') }}</p>
            @endif
            <form method="post" action="{{ route('hr.job-titles.update', $jobTitle) }}">
                @csrf
                @method('PUT')
                <div class="jt-field">
                    <label for="jt-rename">{{ __('Designation name') }}</label>
                    <input type="text" name="name" id="jt-rename" value="{{ old('name', $jobTitle->name) }}" required maxlength="255" autocomplete="organization-title">
                </div>
                <div style="margin-top:14px;display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">{{ __('Save name') }}</button>
                </div>
            </form>
        </div>

        @if($employees->isEmpty())
            <h2 class="jt-sub">{{ __('Delete designation') }}</h2>
            <div class="jt-del-section">
                <p style="margin:0 0 12px;font-size:13px;line-height:1.5;">{{ __('This designation has no employees assigned and can be permanently deleted.') }}</p>
                <form method="post" action="{{ route('hr.job-titles.destroy', $jobTitle) }}" onsubmit="return confirm('{{ __('Delete this designation? This cannot be undone.') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="padding:8px 14px;font-size:13px;font-weight:600;border-radius:8px;border:1px solid color-mix(in srgb,#ef4444 42%,var(--border));background:transparent;color:#f97373;cursor:pointer;">
                        <i class="fa fa-trash-can" aria-hidden="true" style="margin-right:5px;"></i>{{ __('Delete designation') }}
                    </button>
                </form>
            </div>
        @else
            <p class="muted" style="margin:16px 0 0;font-size:12px;line-height:1.5;">
                <i class="fa fa-circle-info" aria-hidden="true"></i>
                {{ __('Deletion is disabled while employees are assigned to this designation.') }}
            </p>
        @endif
    </div>

    {{-- EMPLOYEES TAB --}}
    <div id="jt-tab-employees" class="jt-panel @if($tab === 'employees') is-active @endif">
        @if($employees->isEmpty())
            <p class="muted" style="margin:0;line-height:1.5;font-size:13px;">{{ __('No employees hold this designation yet. Assign the designation from an employee\'s profile.') }}</p>
            <p style="margin:12px 0 0;">
                <a href="{{ route('hr.employees.index') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:7px;">
                    <i class="fa fa-users" aria-hidden="true"></i>{{ __('Go to Employees') }}
                </a>
            </p>
        @else
            <div class="jt-table-wrap">
                <table class="jt-table">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Department') }}</th>
                            <th>{{ __('Joined') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($employees as $emp)
                            <tr>
                                <td>
                                    <a href="{{ route('hr.employees.show', $emp) }}" style="color:var(--primary);font-weight:650;text-decoration:none;">{{ $emp->full_name }}</a>
                                </td>
                                <td>
                                    @if($emp->department)
                                        <span class="jt-dept-pill">{{ $emp->department->name }}</span>
                                    @else
                                        <span class="muted" style="font-size:12px;">—</span>
                                    @endif
                                </td>
                                <td class="muted" style="white-space:nowrap;">{{ $emp->date_of_joining?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="muted" style="margin:12px 0 0;font-size:12px;line-height:1.45;">
                {{ trans_choice(':count employee holds this designation.|:count employees hold this designation.', $employees->count(), ['count' => $employees->count()]) }}
            </p>
        @endif
    </div>
</div>
@endsection
