@php $project = $project ?? null; @endphp
@if($project)
@once('crm-project-tabs-style')
<style>
.ps-tabs{display:flex;flex-wrap:wrap;gap:4px;margin:0 0 14px;padding:4px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,var(--border) 8%);width:fit-content;}
.ps-tab{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:11.5px;font-weight:700;color:var(--muted);text-decoration:none;border-radius:8px;border:1px solid transparent;background:transparent;transition:all .15s;white-space:nowrap;}
.ps-tab:hover{color:var(--text);background:color-mix(in srgb,var(--card) 80%,transparent);}
.ps-tab.is-active{color:var(--text);background:var(--card);border-color:var(--border);box-shadow:0 1px 4px rgba(0,0,0,.1);}
</style>
@endonce

<div style="margin-bottom:14px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:12px;">
        <a href="{{ route('crm.projects.index') }}" class="pcat-link">
            <i class="fa fa-arrow-left"></i> Projects
        </a>
        <span class="muted">/</span>
        <strong style="font-size:13px;color:var(--text);">{{ $project->name }}</strong>
        @if($project->isArchived())
            <span class="pcat-badge pcat-badge--off">Archived</span>
        @endif
    </div>
    <nav class="ps-tabs">
        <a href="{{ route('crm.projects.show', $project) }}"
           @class(['ps-tab', 'is-active' => request()->routeIs('crm.projects.show')])>
            <i class="fa fa-house"></i> Overview
        </a>
        <a href="{{ route('crm.projects.leads.index', $project) }}"
           @class(['ps-tab', 'is-active' => request()->routeIs('crm.projects.leads.index') || request()->routeIs('crm.leads.show') || request()->routeIs('crm.leads.edit')])>
            <i class="fa fa-filter"></i> Leads
        </a>
        <a href="{{ route('crm.projects.leads.board', $project) }}"
           @class(['ps-tab', 'is-active' => request()->routeIs('crm.projects.leads.board')])>
            <i class="fa fa-table-columns"></i> Board
        </a>
        <a href="{{ route('crm.projects.stages.index', $project) }}"
           @class(['ps-tab', 'is-active' => request()->routeIs('crm.projects.stages.index')])>
            <i class="fa fa-sliders"></i> Stages
        </a>
        <a href="{{ route('crm.projects.forms.index', $project) }}"
           @class(['ps-tab', 'is-active' => request()->routeIs('crm.projects.forms.*')])>
            <i class="fa fa-window-restore"></i> Forms
        </a>
        <a href="{{ route('crm.projects.edit', $project) }}"
           @class(['ps-tab', 'is-active' => request()->routeIs('crm.projects.edit')])>
            <i class="fa fa-gear"></i> Settings
        </a>
    </nav>
</div>
@endif
