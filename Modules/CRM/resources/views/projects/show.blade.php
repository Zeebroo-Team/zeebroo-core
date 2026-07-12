@extends('theme::layouts.app', ['title' => $project->name, 'heading' => $project->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pj-stats{display:flex;gap:8px;margin:0 0 16px;overflow-x:auto;padding-bottom:6px;}
.pj-stat{flex:0 0 104px;border:1px solid var(--border);border-radius:9px;padding:8px 10px;background:var(--card);}
.pj-stat__label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:0 0 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pj-stat__value{font-size:16px;font-weight:800;color:var(--text);margin:0;line-height:1.15;}
.pj-quick{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:16px;}
.pj-quick a{display:flex;align-items:center;gap:10px;padding:14px;border:1px solid var(--border);border-radius:11px;background:var(--card);text-decoration:none;color:var(--text);font-weight:700;font-size:13px;transition:border-color .15s;}
.pj-quick a:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));}
.pj-quick a i{font-size:16px;color:var(--primary);width:20px;text-align:center;flex-shrink:0;}
.pj-timeline-cols{display:grid;grid-template-columns:1fr;gap:16px;margin-bottom:16px;}
@media(min-width:820px){.pj-timeline-cols{grid-template-columns:1fr 1fr;}}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $project])

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    @if($project->description)
        <p class="muted" style="margin:0 0 16px;font-size:13px;line-height:1.5;">{{ $project->description }}</p>
    @endif

    <div class="pj-stats">
        <div class="pj-stat">
            <p class="pj-stat__label">Total leads</p>
            <p class="pj-stat__value">{{ collect($pipeline)->sum('count') }}</p>
        </div>
        <div class="pj-stat">
            <p class="pj-stat__label">Stages</p>
            <p class="pj-stat__value">{{ $stageCount }}</p>
        </div>
        <div class="pj-stat">
            <p class="pj-stat__label">Custom fields</p>
            <p class="pj-stat__value">{{ $fieldCount }}</p>
        </div>
    </div>

    @if($stageChart['hasData'])
        <h2 style="font-size:14px;font-weight:800;margin:0 0 4px;">Leads by stage over time</h2>
        <p class="muted" style="margin:0 0 10px;font-size:11px;">{{ $stageChart['note'] }}</p>
        <div class="pcat-inline" style="margin-bottom:16px;padding:14px;">
            @include('crm::partials.line-chart', [
                'canvasId' => 'pj-stage-trend-' . $project->id,
                'chartAriaLabel' => 'Leads by stage over time for ' . $project->name,
                'chartLabels' => $stageChart['labels'],
                'chartDatasets' => $stageChart['datasets'],
                'chartWrapStyle' => 'position:relative;height:min(320px,50vh);width:100%;',
            ])
        </div>
    @endif

    <div class="pj-quick">
        <a href="{{ route('crm.projects.leads.index', $project) }}">
            <i class="fa fa-filter"></i> View leads
        </a>
        <a href="{{ route('crm.projects.leads.board', $project) }}">
            <i class="fa fa-table-columns"></i> Open board
        </a>
        <a href="{{ route('crm.projects.stages.index', $project) }}">
            <i class="fa fa-sliders"></i> Manage stages
        </a>
        <a href="{{ route('crm.projects.custom-fields.index', $project) }}">
            <i class="fa fa-list-ul"></i> Manage custom fields
        </a>
    </div>

    <h2 style="font-size:14px;font-weight:800;margin:0 0 8px;">Recent leads</h2>
    @if($recentLeads->isEmpty())
        <p class="muted" style="font-size:13px;">
            No leads yet. <a href="{{ route('crm.projects.leads.index', $project) }}" class="pcat-link">Add your first lead</a>.
        </p>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Company</th>
                        <th>Stage</th>
                        <th style="text-align:right;">Value</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentLeads as $l)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $l->name }}</strong></td>
                            <td>{{ $l->company ?? '—' }}</td>
                            <td>
                                <span style="display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid {{ $l->stageColor() }};color:{{ $l->stageColor() }};">
                                    {{ $l->stageLabel() }}
                                </span>
                            </td>
                            <td style="text-align:right;font-weight:700;">{{ $l->estimated_value !== null ? number_format((float) $l->estimated_value, 2) : '—' }}</td>
                            <td style="text-align:right;">
                                <a href="{{ route('crm.leads.show', $l) }}" class="pcat-link">
                                    <i class="fa fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="pj-timeline-cols" style="margin-top:20px;">
        <div>
            <h2 style="font-size:14px;font-weight:800;margin:0 0 8px;">Stage activity</h2>
            @include('crm::partials.stage-log-timeline', ['stageLogs' => $stageLogs, 'showLeadName' => true])
        </div>

        <div>
            <h2 style="font-size:14px;font-weight:800;margin:0 0 8px;">Activity log</h2>
            @if($recentActivities->isEmpty())
                <p class="muted" style="font-size:13px;">No activity logged yet.</p>
            @else
                <div class="pj-timeline">
                    @foreach($recentActivities as $activity)
                        <div class="pj-timeline__item">
                            <span class="pj-timeline__dot" style="background:var(--primary);"></span>
                            <div class="pj-timeline__body">
                                <div class="pj-timeline__text">
                                    <strong>{{ $activity->typeLabel() }}</strong> on
                                    @if($activity->subject)
                                        <a href="{{ route('crm.leads.show', $activity->subject_id) }}" class="pcat-link">{{ $activity->subject->name }}</a>
                                    @else
                                        a deleted lead
                                    @endif
                                    @if($activity->body)
                                        <div class="muted" style="margin-top:2px;">{{ \Illuminate\Support\Str::limit($activity->body, 80) }}</div>
                                    @endif
                                </div>
                                <div class="pj-timeline__meta">
                                    {{ $activity->occurred_at->diffForHumans() }} · {{ $activity->createdBy?->name ?? 'System' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<div style="margin-top:14px;">
    <a href="{{ route('crm.projects.index') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All projects
    </a>
</div>
@endsection
