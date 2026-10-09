@extends('theme::layouts.app', ['title' => 'Tasks — ' . $project->name, 'heading' => 'Tasks'])

@php
    $priorityColor = ['high'=>'#dc2626','normal'=>'#f59e0b','low'=>'#6b7280'];
    $statusColors  = $statusColors + ['todo'=>'#6b7280','in_progress'=>'#2563eb','review'=>'#7c3aed','done'=>'#16a34a'];
    $stateLabel    = ['completed'=>'Completed','overdue'=>'Overdue','active'=>'In Progress','upcoming'=>'Upcoming','open'=>'No dates'];

    // Which dialog an error belongs to: the milestone dialog posts a hidden _ms_form field.
    $msFormOld  = old('_ms_form');
    $taskErrors = $errors->any() && $msFormOld === null;
    $msErrors   = $errors->any() && $msFormOld !== null;

    $isEmpty  = $milestones->isEmpty() && $tasks->isEmpty();
    $viewUrl  = fn (string $v) => route('pm.projects.tasks.index', array_filter(['project' => $project->id, 'view' => $v === 'milestones' ? null : $v, 'status' => $statusFilter ?: null]));
    $today    = now()->startOfDay();

    // Timeline summary (Timeline tab + full timeline modal)
    $msDone   = $milestones->filter->isCompleted()->count();
    $tDone    = $tasks->filter->isCompleted()->count();
    $tOverdue = $tasks->filter->isOverdue()->count();
    // Cancelled tasks are left out of progress.
    $tCounted = $tasks->count() - $tasks->filter->isCancelled()->count();
    $pctAll   = $tCounted ? (int) round($tDone / $tCounted * 100) : 0;
    $starts   = $milestones->map(fn ($m) => $m->start_date ?? $m->due_date)->filter()->sort()->values();
    $ends     = $milestones->map(fn ($m) => $m->due_date ?? $m->start_date)->filter()->sort()->values();
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pm-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px;}
.pm-subtabs{display:inline-flex;border:1px solid var(--border);border-radius:9px;padding:2px;gap:2px;}
.pm-subtab{padding:5px 12px;border-radius:7px;font-size:12px;font-weight:600;color:var(--muted);text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.pm-subtab.active{background:var(--primary);color:#fff;}
.pm-chips{display:flex;flex-wrap:wrap;gap:6px;}
.pm-chip{padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;}
.pm-chip.active{background:var(--primary);color:#fff;border-color:var(--primary);}
.pm-toolbar__right{margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;}
.pm-btn-ghost{background:transparent !important;border:1px solid var(--border) !important;color:var(--text) !important;}

/* Milestone groups */
.pm-ms-group{border:1px solid var(--border);border-left:4px solid var(--pm-state,#6b7280);border-radius:11px;background:var(--card);margin-bottom:12px;transition:box-shadow .15s;}
.pm-ms-head{display:flex;align-items:center;gap:10px;padding:10px 12px;flex-wrap:wrap;}
.pm-ms-grip{cursor:grab;color:var(--muted);width:14px;text-align:center;flex-shrink:0;}
.pm-ms-grip--none{cursor:default;}
.pm-ms-toggle{background:none;border:none;cursor:pointer;color:var(--muted);padding:2px 4px;width:22px;}
.pm-ms-num{min-width:24px;height:24px;border-radius:7px;background:color-mix(in srgb,var(--pm-state,#6b7280) 14%,transparent);color:var(--pm-state,#6b7280);font-size:12px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;padding:0 5px;}
.pm-ms-titlewrap{flex:1 1 180px;min-width:0;}
.pm-ms-name{font-size:14px;font-weight:800;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pm-ms-period{font-size:11px;color:var(--muted);margin-top:1px;}
.pm-ms-state{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;padding:3px 8px;border-radius:999px;color:var(--pm-state,#6b7280);background:color-mix(in srgb,var(--pm-state,#6b7280) 12%,transparent);white-space:nowrap;}
.pm-ms-prog{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:var(--muted);min-width:120px;}
.pm-bar{flex:1;height:6px;border-radius:999px;background:color-mix(in srgb,var(--muted) 20%,transparent);overflow:hidden;min-width:60px;}
.pm-bar > span{display:block;height:100%;background:#16a34a;border-radius:999px;transition:width .2s;}
.pm-ms-actions{display:flex;align-items:center;gap:2px;}
.pm-ms-actions form{display:inline;margin:0;}
.pm-ms-actions button{background:none;border:1px solid transparent;border-radius:6px;cursor:pointer;color:var(--muted);padding:4px 6px;font-size:12px;}
.pm-ms-actions button:hover:not(:disabled){color:var(--text);border-color:var(--border);}
.pm-ms-actions button.danger:hover{color:#dc2626;}
.pm-ms-actions button:disabled{opacity:.35;cursor:default;}
.pm-ms-body{border-top:1px solid var(--border);}
.pm-ms-group.collapsed .pm-ms-body{display:none;}
.pm-ms-group .pcat-table-wrap{margin:0;border:0;border-radius:0 0 11px 11px;}
.pm-ms-task{cursor:grab;}
.pm-drag-handle{color:var(--muted);width:18px;}
.pm-ms-empty-row td{text-align:center;color:var(--muted);font-size:12px;padding:14px !important;}
.pm-ms-select{font-size:12px;padding:4px 6px;border:1px solid var(--border);border-radius:7px;background:var(--card);color:var(--text);max-width:160px;}
.pm-task-title{color:var(--text);font-weight:600;text-decoration:none;font-size:13px;}
.pm-task-title:hover{text-decoration:underline;}
.pm-muted-cell{font-size:12px;color:var(--muted);}
.pm-prio{display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;white-space:nowrap;}
.pm-prio > span{width:7px;height:7px;border-radius:50%;display:inline-block;}
.pm-done-form{display:inline;margin:0;}
.pm-done-toggle{background:none;border:none;cursor:pointer;padding:0;font-size:15px;color:#d1d5db;}
.pm-done-toggle.is-done{color:#16a34a;}
.pm-dragging{opacity:.4;}
.pm-dragover{box-shadow:0 0 0 2px var(--primary);}
.pm-drop-before{box-shadow:0 -3px 0 0 var(--primary);}
.pm-drop-after{box-shadow:0 3px 0 0 var(--primary);}

.pm-state--completed{--pm-state:#16a34a;}
.pm-state--overdue{--pm-state:#dc2626;}
.pm-state--active{--pm-state:#2563eb;}
.pm-state--upcoming{--pm-state:#7c3aed;}
.pm-state--open,.pm-state--none{--pm-state:#6b7280;}

/* Timeline */
.pm-tl-summary{display:flex;flex-wrap:wrap;align-items:center;gap:8px 16px;padding:10px 14px;border:1px solid var(--border);border-radius:10px;margin-bottom:14px;font-size:12px;color:var(--muted);}
.pm-tl-summary b{color:var(--text);}
.pm-tl-summary .warn b,.pm-tl-summary .warn{color:#dc2626;}
.pm-tl-summary .pm-ms-prog{min-width:160px;}
.pm-tl{position:relative;}
.pm-tl-item,.pm-tl-today{display:grid;grid-template-columns:110px 34px minmax(0,1fr);gap:0 8px;}
.pm-tl-date{text-align:right;padding-top:12px;font-size:12px;font-weight:700;color:var(--text);}
.pm-tl-date small{display:block;font-size:10px;font-weight:600;color:var(--muted);}
.pm-tl-rail{position:relative;display:flex;justify-content:center;}
.pm-tl-rail::before{content:"";position:absolute;top:0;bottom:0;left:50%;width:2px;transform:translateX(-50%);background:var(--border);}
.pm-tl-node{position:relative;margin-top:8px;width:28px;height:28px;border-radius:50%;background:var(--card);border:2px solid var(--pm-state,#6b7280);color:var(--pm-state,#6b7280);display:flex;align-items:center;justify-content:center;font-size:11px;}
.pm-tl-item.pm-state--completed .pm-tl-node,.pm-tl-item.pm-state--active .pm-tl-node,.pm-tl-item.pm-state--upcoming .pm-tl-node,.pm-tl-item.pm-state--overdue .pm-tl-node{background:var(--pm-state);color:#fff;}
.pm-tl-card{border:1px solid var(--border);border-left:4px solid var(--pm-state,#6b7280);border-radius:11px;background:var(--card);margin-bottom:14px;transition:box-shadow .3s;}
.pm-tl-card .pm-ms-head{padding:10px 12px 6px;}
.pm-tl-card > .pm-ms-prog{padding:0 12px 8px;}
.pm-tl-tasks{list-style:none;margin:0;padding:6px 12px 10px;border-top:1px solid var(--border);}
.pm-tl-task{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;padding:6px 0;border-bottom:1px dashed var(--border);cursor:grab;font-size:12px;}
.pm-tl-task:last-child{border-bottom:0;}
.pm-tl-task.is-done .pm-tl-task-title{text-decoration:line-through;color:var(--muted);}
.pm-tl-task-title{flex:1 1 160px;min-width:0;}
.pm-tl-meta{color:var(--muted);font-size:11px;white-space:nowrap;}
.pm-tl-empty{color:var(--muted);font-size:12px;padding:8px 0;text-align:center;}
.pm-tl-today .pm-tl-date{color:#dc2626;padding-top:4px;}
.pm-tl-today-dot{position:relative;margin-top:6px;width:12px;height:12px;border-radius:50%;background:#dc2626;box-shadow:0 0 0 4px color-mix(in srgb,#dc2626 20%,transparent);}
.pm-tl-today-label{padding:4px 0 14px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#dc2626;border-top:2px dashed color-mix(in srgb,#dc2626 50%,transparent);margin-top:10px;}
.pm-tl.compact .pm-tl-tasks{display:none;}
.pm-tl-flash .pm-tl-card{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 45%,transparent);}
@media(max-width:640px){
  .pm-tl-item,.pm-tl-today{grid-template-columns:0 26px minmax(0,1fr);}
  .pm-tl-date{display:none;}
}

.pm-empty{text-align:center;color:var(--muted);font-size:13px;padding:30px 10px;border:1px dashed var(--border);border-radius:11px;}

/* Full timeline modal */
.pm-tlm-overlay{position:fixed;inset:0;z-index:1050;background:rgba(15,23,42,.5);display:flex;align-items:center;justify-content:center;padding:24px;}
.pm-tlm-overlay[hidden]{display:none;}
.pm-tlm{width:min(1280px,100%);height:min(900px,100%);display:flex;flex-direction:column;background:var(--card);color:var(--text);border:1px solid var(--border);border-radius:16px;box-shadow:0 24px 60px rgba(0,0,0,.35);overflow:hidden;}
.pm-tlm--max .pm-tlm{width:100%;height:100%;border-radius:10px;}
.pm-tlm-overlay.pm-tlm--max{padding:8px;}
.pm-tlm-hdr{display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--border);flex-wrap:wrap;}
.pm-tlm-icon{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);font-size:17px;flex-shrink:0;}
.pm-tlm-titlewrap{flex:1 1 260px;min-width:0;}
.pm-tlm-title{font-size:17px;font-weight:800;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pm-tlm-sub{font-size:12px;color:var(--muted);margin-top:2px;}
.pm-tlm-actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.pm-tlm-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;font-size:13px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--muted) 8%,var(--card));color:var(--text);cursor:pointer;}
.pm-tlm-btn:hover{border-color:var(--primary);}
.pm-tlm-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.pm-tlm-icon-btn{background:none;border:none;color:var(--muted);cursor:pointer;font-size:17px;padding:6px 8px;border-radius:7px;}
.pm-tlm-icon-btn:hover{color:var(--text);background:color-mix(in srgb,var(--muted) 12%,transparent);}
.pm-tlm-bar{display:flex;align-items:center;justify-content:space-between;gap:10px 16px;flex-wrap:wrap;padding:10px 18px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--muted) 5%,var(--card));}
.pm-tlm-stats{display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex:1 1 auto;}
.pm-tlm-stat{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border:1px solid var(--border);border-radius:999px;font-size:12px;color:var(--muted);background:var(--card);}
.pm-tlm-stat i{color:var(--primary);}
.pm-tlm-stat b{color:var(--text);}
.pm-tlm-stat--warn,.pm-tlm-stat--warn b,.pm-tlm-stat--warn i{color:#dc2626;border-color:color-mix(in srgb,#dc2626 40%,transparent);}
.pm-tlm-overall{flex:1 1 180px;max-width:300px;}
.pm-tlm-flash{padding:7px 18px;font-size:12px;font-weight:600;color:#16a34a;border-bottom:1px solid var(--border);background:color-mix(in srgb,#16a34a 8%,var(--card));}
.pm-tlm-main{flex:1;min-height:0;display:grid;grid-template-columns:270px minmax(0,1fr);}
.pm-tlm-nav{border-right:1px solid var(--border);overflow-y:auto;padding:12px;}
.pm-tlm-nav-title{font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin:2px 4px 10px;}
.pm-tlm-nav-item{display:flex;align-items:center;gap:10px;width:100%;text-align:left;padding:9px 10px;margin-bottom:4px;border:1px solid transparent;border-radius:10px;background:none;color:var(--text);cursor:pointer;}
.pm-tlm-nav-item:hover{background:color-mix(in srgb,var(--muted) 8%,transparent);}
.pm-tlm-nav-item.active{background:color-mix(in srgb,var(--primary) 10%,transparent);border-color:color-mix(in srgb,var(--primary) 30%,transparent);}
.pm-tlm-nav-dot{width:26px;height:26px;border-radius:50%;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;background:var(--pm-state,#6b7280);}
.pm-tlm-nav-text{display:flex;flex-direction:column;min-width:0;}
.pm-tlm-nav-name{font-size:13px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pm-tlm-nav-meta{font-size:11px;color:var(--muted);}
.pm-tlm-nav-empty{font-size:12px;color:var(--muted);padding:6px 4px;}
.pm-tlm-scroll{overflow-y:auto;padding:20px 22px 10px 10px;}
.pm-tlm-scroll .pm-tl-item,.pm-tlm-scroll .pm-tl-today{grid-template-columns:130px 40px minmax(0,1fr);}
@media(max-width:860px){
  .pm-tlm-overlay{padding:0;}
  .pm-tlm{width:100%;height:100%;border-radius:0;}
  .pm-tlm-main{grid-template-columns:minmax(0,1fr);}
  .pm-tlm-nav{display:none;}
  .pm-tlm-scroll{padding:14px 12px;}
  .pm-tlm-scroll .pm-tl-item,.pm-tlm-scroll .pm-tl-today{grid-template-columns:0 26px minmax(0,1fr);}
  .pm-tlm-actions .pm-tlm-btn{padding:6px 9px;font-size:12px;}
}

/* Dialogs (milestone + task) */
.pm-dialog{border:1px solid var(--border);border-radius:14px;padding:0;background:var(--card);color:var(--text);width:min(460px,calc(100vw - 32px));}
.pm-dialog--wide{width:min(560px,calc(100vw - 32px));}
.pm-dialog::backdrop{background:rgba(0,0,0,.45);}
.pm-dialog__head{display:flex;align-items:center;gap:8px;padding:14px 16px;border-bottom:1px solid var(--border);font-weight:800;}
.pm-dialog__head button{margin-left:auto;background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;}
.pm-dialog__body{padding:14px 16px;display:flex;flex-direction:column;gap:10px;}
.pm-dialog__row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.pm-dialog__hint{font-size:11px;color:var(--muted);}
.pm-dialog__foot{display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--border);}
.pm-dialog .pcat-field textarea{min-height:70px;}

/* Assignees (chips button + assign dialog) */
.pm-assignees-btn{display:inline-flex;align-items:center;gap:0;background:none;border:1px solid transparent;border-radius:999px;padding:2px 6px 2px 2px;cursor:pointer;color:var(--text);max-width:220px;}
.pm-assignees-btn:hover{border-color:var(--border);background:color-mix(in srgb,var(--muted) 8%,transparent);}
.pm-assignees-btn:disabled{opacity:.5;cursor:wait;}
.pm-assignee-chip{width:24px;height:24px;border-radius:50%;background:var(--primary);color:#fff;font-size:11px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;border:2px solid var(--card);flex-shrink:0;}
.pm-assignees-btn .pm-assignee-chip + .pm-assignee-chip{margin-left:-7px;}
.pm-assignee-chip--more{background:color-mix(in srgb,var(--muted) 35%,var(--card));color:var(--text);}
.pm-assignees-name{font-size:12px;font-weight:600;margin-left:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pm-assignees-empty{font-size:11px;font-weight:600;color:var(--muted);padding:3px 4px;white-space:nowrap;}
.pm-assign-list{max-height:300px;overflow:auto;border:1px solid var(--border);border-radius:9px;}
.pm-assign-item{display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid var(--border);cursor:pointer;font-size:13px;font-weight:600;}
.pm-assign-item:last-child{border-bottom:0;}
.pm-assign-item:hover{background:color-mix(in srgb,var(--muted) 6%,transparent);}
.pm-assign-empty{font-size:12px;color:var(--muted);padding:16px;text-align:center;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('projectmanage::partials.pm-hub-nav')

    {{-- Breadcrumb --}}
    <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">
        <a href="{{ route('pm.projects.show', $project) }}" class="pcat-link">{{ $project->name }}</a>
        <span style="margin:0 4px;">/</span>
        <span>Tasks</span>
    </div>

    @include('projectmanage::partials.pm-detail-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    {{-- Toolbar: view switch · full timeline · status filter · create buttons --}}
    <div class="pm-toolbar">
        <div class="pm-subtabs">
            <a href="{{ $viewUrl('milestones') }}" class="pm-subtab {{ $activeView === 'milestones' ? 'active' : '' }}"><i class="fa fa-layer-group"></i> By Milestone</a>
            <a href="{{ $viewUrl('timeline') }}" class="pm-subtab {{ $activeView === 'timeline' ? 'active' : '' }}"><i class="fa fa-timeline"></i> Timeline</a>
        </div>
        <button type="button" class="linkbtn pm-btn-ghost" data-tlm-open title="Open the full timeline in a larger view" style="padding:6px 12px;font-size:12px;">
            <i class="fa fa-up-right-and-down-left-from-center"></i> View Timeline
        </button>
        <div class="pm-chips">
            @foreach($statusTabs as $key => $label)
                <button type="button" class="pm-chip {{ $statusFilter === $key ? 'active' : '' }}" data-status-filter="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="pm-toolbar__right">
            @if($activeView === 'timeline')
                <button type="button" class="linkbtn pm-btn-ghost" id="pm-tl-today-btn" style="padding:6px 12px;font-size:12px;"><i class="fa fa-location-crosshairs"></i> Today</button>
                <button type="button" class="linkbtn pm-btn-ghost" id="pm-tl-compact-btn" style="padding:6px 12px;font-size:12px;"><i class="fa fa-compress"></i> <span>Hide Tasks</span></button>
            @endif
            <button type="button" class="linkbtn pm-btn-ghost" data-ms-new style="padding:6px 12px;font-size:12px;"><i class="fa fa-flag"></i> New Milestone</button>
            <button type="button" class="linkbtn" data-task-new style="padding:6px 12px;font-size:12px;"><i class="fa fa-plus"></i> New Task</button>
        </div>
    </div>

    @if($isEmpty)
        <div class="pm-empty">
            <i class="fa fa-flag" style="font-size:22px;opacity:.4;display:block;margin-bottom:8px;"></i>
            No milestones or tasks yet.<br>Add a milestone to split this project into phases.
        </div>
    @elseif($activeView === 'milestones')
        {{-- ── By Milestone ── --}}
        <div id="pm-ms-groups" data-view-root>
            @foreach($groups as $g)
                @php
                    $ms    = $g['milestone'];
                    $state = $ms ? $ms->timelineState() : 'none';
                    $key   = $ms ? (string) $ms->id : '';
                @endphp
                <div class="pm-ms-group pm-state--{{ $state }}" data-group="{{ $key }}">
                    <div class="pm-ms-head">
                        @if($ms)
                            <span class="pm-ms-grip" draggable="true" title="Drag to reorder"><i class="fa fa-grip-vertical"></i></span>
                        @else
                            <span class="pm-ms-grip pm-ms-grip--none"></span>
                        @endif
                        <button type="button" class="pm-ms-toggle" data-ms-toggle title="Collapse / expand"><i class="fa fa-chevron-down"></i></button>
                        @if($ms)
                            <span class="pm-ms-num" data-ms-num title="Sort number">{{ $loop->iteration }}</span>
                            <div class="pm-ms-titlewrap">
                                <div class="pm-ms-name">{{ $ms->name }}</div>
                                <div class="pm-ms-period"><i class="fa fa-calendar"></i> {{ $ms->periodLabel() }}</div>
                            </div>
                            <span class="pm-ms-state">{{ $stateLabel[$state] }}</span>
                        @else
                            <span class="pm-ms-num"><i class="fa fa-inbox"></i></span>
                            <div class="pm-ms-titlewrap">
                                <div class="pm-ms-name">No Milestone</div>
                                <div class="pm-ms-period">Tasks not assigned to a milestone</div>
                            </div>
                        @endif
                        <div class="pm-ms-prog" data-prog="{{ $ms ? 'bar' : 'count' }}">
                            @if($ms)<div class="pm-bar"><span style="width:0"></span></div>@endif
                            <span data-prog-text></span>
                        </div>
                        @include('projectmanage::tasks.partials.ms-actions', ['ms' => $ms, 'reorder' => true])
                    </div>
                    <div class="pm-ms-body">
                        <div class="pcat-table-wrap">
                            <table class="pcat-table">
                                <thead>
                                    <tr>
                                        <th style="width:18px;"></th><th style="width:26px;"></th><th>Task</th><th>Priority</th>
                                        <th>Assigned</th><th>Due</th><th>Stage</th><th style="width:160px;">Milestone</th><th style="text-align:right;width:70px;"></th>
                                    </tr>
                                </thead>
                                <tbody data-task-list>
                                    @foreach($g['tasks'] as $t)
                                        @include('projectmanage::tasks.partials.task-row')
                                    @endforeach
                                    <tr class="pm-ms-empty-row" data-empty><td colspan="9"></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- ── Timeline ── --}}
        <div class="pm-tl-summary">
            <span><i class="fa fa-calendar"></i>
                @if($starts->isNotEmpty())
                    {{ $starts->first()->format('d M Y') }} → {{ $ends->last()->format('d M Y') }}
                @else
                    No milestone dates yet
                @endif
                · Today {{ $today->format('d M Y') }}
            </span>
            <span><i class="fa fa-flag"></i> <b>{{ $msDone }}/{{ $milestones->count() }}</b> milestones done</span>
            <span><i class="fa fa-list-check"></i> <b>{{ $tDone }}/{{ $tCounted }}</b> tasks done</span>
            <span class="{{ $tOverdue ? 'warn' : '' }}"><i class="fa fa-triangle-exclamation"></i> <b>{{ $tOverdue }}</b> overdue</span>
            <span class="pm-ms-prog" title="{{ $pctAll }}% of tasks done"><span class="pm-bar"><span style="width:{{ $pctAll }}%"></span></span> {{ $pctAll }}%</span>
        </div>
        @include('projectmanage::tasks.partials.timeline', ['tlId' => 'pm-timeline'])
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('pm.projects.show', $project) }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Back to project
    </a>
</div>

@include('projectmanage::tasks.partials.timeline-modal')

{{-- New task dialog (toolbar, a milestone's + button, or the timeline modal) --}}
<dialog class="pm-dialog pm-dialog--wide" id="pm-task-dialog">
    <form method="POST" action="{{ route('pm.projects.tasks.store', $project) }}" id="pm-task-form">
        @csrf
        <div class="pm-dialog__head">
            <i class="fa fa-list-check" style="color:var(--primary);"></i>
            <span>New Task</span>
            <button type="button" data-dialog-close title="Close"><i class="fa fa-xmark"></i></button>
        </div>
        <div class="pm-dialog__body">
            @if($taskErrors)
                <div class="pcat-banner pcat-banner--err" style="margin:0;">
                    @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                </div>
            @endif
            <div class="pcat-field">
                <label>Task title *</label>
                <input type="text" name="title" value="{{ old('title') }}" placeholder="What needs to be done?" maxlength="200" required>
            </div>
            <div class="pcat-field">
                <label>Milestone</label>
                <select name="milestone_id">
                    <option value="">No milestone</option>
                    @foreach($milestones as $ms)
                        <option value="{{ $ms->id }}" @selected(old('milestone_id')==(string)$ms->id)>{{ $ms->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pm-dialog__row">
                <div class="pcat-field">
                    <label>Stage</label>
                    <select name="status">
                        @foreach($statusTabs as $key => $label)
                            @continue($key === '')
                            <option value="{{ $key }}" @selected(old('status','todo')===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pcat-field">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="normal" @selected(old('priority','normal')==='normal')>Normal</option>
                        <option value="high"   @selected(old('priority')==='high')>High</option>
                        <option value="low"    @selected(old('priority')==='low')>Low</option>
                    </select>
                </div>
            </div>
            <div class="pm-dialog__row">
                <div class="pcat-field">
                    <label>Assigned to <span style="font-weight:400;color:var(--muted);">(project team)</span></label>
                    <div style="max-height:120px;overflow:auto;border:1px solid var(--border);border-radius:8px;padding:4px 8px;">
                        @forelse($assignableUsers as $u)
                            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:500;padding:3px 0;cursor:pointer;">
                                <input type="checkbox" name="assignee_ids[]" value="{{ $u->id }}" @checked(in_array((string) $u->id, (array) old('assignee_ids', []), true))>
                                {{ $u->name }}
                            </label>
                        @empty
                            <div style="font-size:11px;color:var(--muted);padding:3px 0;">No team members — add them on the Assignment tab.</div>
                        @endforelse
                    </div>
                </div>
                <div class="pcat-field">
                    <label>Due date</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}">
                </div>
            </div>
        </div>
        <div class="pm-dialog__foot">
            <button type="button" class="linkbtn pm-btn-ghost" data-dialog-close style="padding:8px 14px;font-size:13px;">Cancel</button>
            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;"><i class="fa fa-floppy-disk"></i> Add Task</button>
        </div>
    </form>
</dialog>

{{-- Assign dialog (a task's assignee chips) — project team members only --}}
<dialog class="pm-dialog" id="pm-assign-dialog">
    <div class="pm-dialog__head">
        <i class="fa fa-users" style="color:var(--primary);"></i>
        <span data-assign-head style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Assign</span>
        <button type="button" data-dialog-close title="Close"><i class="fa fa-xmark"></i></button>
    </div>
    <div class="pm-dialog__body">
        @if($assignableUsers->count() > 5)
            <div class="pcat-field"><input type="search" data-assign-search placeholder="Search members…"></div>
        @endif
        <div class="pm-assign-list">
            @forelse($assignableUsers as $u)
                <label class="pm-assign-item" data-q="{{ mb_strtolower($u->name . ' ' . $u->email) }}">
                    <input type="checkbox" value="{{ $u->id }}">
                    <span class="pm-assignee-chip">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                    <span>{{ $u->name }}</span>
                </label>
            @empty
                <div class="pm-assign-empty">
                    No team members yet.<br>
                    <a href="{{ route('pm.projects.edit', $project) }}" class="pcat-link">Add them on the Assignment tab</a>
                </div>
            @endforelse
        </div>
    </div>
    <div class="pm-dialog__foot">
        <button type="button" class="linkbtn pm-btn-ghost" data-dialog-close style="padding:8px 14px;font-size:13px;">Cancel</button>
        <button type="button" class="linkbtn" data-assign-save style="padding:8px 16px;font-size:13px;" @disabled($assignableUsers->isEmpty())><i class="fa fa-floppy-disk"></i> Save</button>
    </div>
</dialog>

{{-- Milestone add / edit dialog --}}
<dialog class="pm-dialog" id="pm-ms-dialog">
    <form method="POST" action="{{ route('pm.projects.milestones.store', $project) }}" id="pm-ms-form">
        @csrf
        <input type="hidden" name="_method" value="PUT" disabled>
        <input type="hidden" name="_ms_form" value="new">
        <div class="pm-dialog__head">
            <i class="fa fa-flag" style="color:var(--primary);"></i>
            <span data-ms-title>New Milestone</span>
            <button type="button" data-dialog-close title="Close"><i class="fa fa-xmark"></i></button>
        </div>
        <div class="pm-dialog__body">
            @if($msErrors)
                <div class="pcat-banner pcat-banner--err" style="margin:0;">
                    @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                </div>
            @endif
            <div class="pcat-field">
                <label>Milestone name *</label>
                <input type="text" name="name" maxlength="150" required placeholder="e.g. Phase 1 — Design">
            </div>
            <div class="pm-dialog__row">
                <div class="pcat-field"><label>Start date</label><input type="date" name="start_date"></div>
                <div class="pcat-field"><label>End date</label><input type="date" name="due_date"></div>
            </div>
            <div class="pm-dialog__row">
                <div class="pcat-field"><label>Sort number</label><input type="number" name="sort_order" min="0" max="9999" placeholder="Auto ({{ $milestones->count() + 1 }})"></div>
                <div class="pm-dialog__hint" style="align-self:end;padding-bottom:6px;">Lower numbers come first. You can also drag milestones to reorder.</div>
            </div>
            <div class="pcat-field">
                <label>Description</label>
                <textarea name="description" maxlength="2000" placeholder="Optional…"></textarea>
            </div>
        </div>
        <div class="pm-dialog__foot">
            <button type="button" class="linkbtn pm-btn-ghost" data-dialog-close style="padding:8px 14px;font-size:13px;">Cancel</button>
            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;"><i class="fa fa-floppy-disk"></i> <span data-ms-submit>Add Milestone</span></button>
        </div>
    </form>
</dialog>

<script>
(function () {
    var csrf       = @js(csrf_token());
    var projectId  = @js($project->id);
    var reorderUrl = @js(route('pm.projects.milestones.reorder', $project));
    var storeUrl   = @js(route('pm.projects.milestones.store', $project));
    var updateUrl  = @js(route('pm.projects.milestones.update', [$project, '__ID__']));
    var statusFilter = @js($statusFilter);
    var orderChanged = false;   // milestones reordered since load — timeline copies are stale

    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function sendJson(method, url, body) {
        return fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) throw new Error(data.message || 'Request failed');
                return data;
            });
        });
    }
    function setUrlParam(name, value) {
        var url = new URL(window.location.href);
        if (value) url.searchParams.set(name, value); else url.searchParams.delete(name);
        history.replaceState(null, '', url);
    }

    // The same task can be on screen more than once (page view + timeline modal);
    // each copy lives in its own [data-view-root].
    var modalRoot = document.getElementById('pm-tlm-timeline');

    // ── Progress, empty placeholders, jump-list counts and status filter (client-side) ──
    function refresh() {
        $all('[data-group]').forEach(function (g) {
            var all = $all('[data-tid]', g), done = 0, cancelled = 0, visible = 0;
            all.forEach(function (t) {
                var show = !statusFilter || t.getAttribute('data-status') === statusFilter;
                t.style.display = show ? '' : 'none';
                if (show) visible++;
                if (t.getAttribute('data-done') === '1') done++;
                if (t.getAttribute('data-cancelled') === '1') cancelled++;
            });
            var prog = g.querySelector('[data-prog]');
            if (prog) {
                var text = prog.querySelector('[data-prog-text]');
                var fill = prog.querySelector('.pm-bar > span');
                if (prog.getAttribute('data-prog') === 'bar') {
                    // Cancelled tasks don't count towards progress.
                    var counted = all.length - cancelled;
                    if (fill) fill.style.width = (counted ? Math.round(done / counted * 100) : 0) + '%';
                    text.textContent = done + '/' + counted + ' done';
                    prog.title = done + ' of ' + counted + ' tasks done' + (cancelled ? ' (' + cancelled + ' cancelled)' : '');
                } else {
                    text.textContent = all.length + ' task' + (all.length === 1 ? '' : 's');
                }
            }
            var empty = g.querySelector('[data-empty]');
            if (empty) {
                empty.style.display = visible ? 'none' : '';
                (empty.querySelector('td') || empty).textContent = statusFilter && all.length
                    ? 'No tasks match this filter.'
                    : 'No tasks — drag a task here or click + to add one.';
            }
            if (modalRoot && modalRoot.contains(g)) {
                var nav = document.querySelector('[data-nav-group="' + g.getAttribute('data-group') + '"] [data-nav-count]');
                if (nav) nav.textContent = done + '/' + all.length;
            }
        });
    }

    $all('[data-status-filter]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            statusFilter = chip.getAttribute('data-status-filter');
            // Toolbar and modal chips stay in sync
            $all('[data-status-filter]').forEach(function (c) { c.classList.toggle('active', c.getAttribute('data-status-filter') === statusFilter); });
            setUrlParam('status', statusFilter);
            $all('.pm-subtab').forEach(function (a) {
                var u = new URL(a.href);
                if (statusFilter) u.searchParams.set('status', statusFilter); else u.searchParams.delete('status');
                a.href = u;
            });
            refresh();
        });
    });

    // ── Collapse / expand milestone groups (remembered per project in this browser) ──
    var collapseKey = 'pm-ms-collapsed-' + projectId;
    var collapsed = [];
    try { collapsed = JSON.parse(localStorage.getItem(collapseKey) || '[]'); } catch (e) {}
    function setCollapsed(g, on) {
        g.classList.toggle('collapsed', on);
        var icon = g.querySelector('[data-ms-toggle] i');
        if (icon) icon.className = 'fa fa-chevron-' + (on ? 'right' : 'down');
    }
    $all('.pm-ms-group').forEach(function (g) {
        if (collapsed.indexOf(g.getAttribute('data-group')) !== -1) setCollapsed(g, true);
        g.querySelector('[data-ms-toggle]').addEventListener('click', function () {
            var key = g.getAttribute('data-group');
            var on = !g.classList.contains('collapsed');
            setCollapsed(g, on);
            collapsed = collapsed.filter(function (k) { return k !== key; });
            if (on) collapsed.push(key);
            try { localStorage.setItem(collapseKey, JSON.stringify(collapsed)); } catch (e) {}
        });
    });

    // ── Move a task to another milestone (drag-and-drop or the row select) ──
    // Every on-screen copy moves immediately; all go back if the server rejects the change.
    function placeIn(el, group, before) {
        var list = group.querySelector('[data-task-list]');
        if (!before || before.parentNode !== list) before = list.querySelector('[data-empty]');
        list.insertBefore(el, before);
        var sel = el.querySelector('[data-ms-select]');
        if (sel) sel.value = group.getAttribute('data-group');
        if (group.classList.contains('collapsed')) setCollapsed(group, false);
    }
    function moveTask(tid, toKey) {
        var copies = $all('[data-view-root]').map(function (root) {
            var el = root.querySelector('[data-tid="' + tid + '"]');
            return el && { root: root, el: el, from: el.closest('[data-group]'), next: el.nextSibling };
        }).filter(Boolean);
        if (!copies.length || copies[0].from.getAttribute('data-group') === toKey) return;
        copies.forEach(function (c) {
            var to = c.root.querySelector('[data-group="' + toKey + '"]');
            if (to) placeIn(c.el, to);
        });
        refresh();
        sendJson('PATCH', copies[0].el.getAttribute('data-milestone-url'), { milestone_id: toKey ? Number(toKey) : null })
            .catch(function (err) {
                copies.forEach(function (c) { placeIn(c.el, c.from, c.next); });
                refresh();
                alert('Could not move task: ' + err.message);
            });
    }

    $all('[data-ms-select]').forEach(function (sel) {
        sel.addEventListener('change', function () { moveTask(sel.closest('[data-tid]').getAttribute('data-tid'), sel.value); });
    });

    var dragTask = null, dragMs = null;
    function clearHints() {
        $all('.pm-dragover, .pm-drop-before, .pm-drop-after').forEach(function (el) {
            el.classList.remove('pm-dragover', 'pm-drop-before', 'pm-drop-after');
        });
    }

    $all('[data-tid][draggable="true"]').forEach(function (el) {
        // Links are natively draggable; disable that so grabbing the title drags the task
        $all('a', el).forEach(function (a) { a.setAttribute('draggable', 'false'); });
        el.addEventListener('dragstart', function (e) {
            if (e.target.closest && e.target.closest('select, button')) { e.preventDefault(); return; }
            dragTask = el;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', el.getAttribute('data-tid'));
            requestAnimationFrame(function () { el.classList.add('pm-dragging'); });
        });
        el.addEventListener('dragend', function () {
            dragTask = null;
            el.classList.remove('pm-dragging');
            clearHints();
        });
    });

    function isReorderTarget(g) {
        return dragMs && dragMs !== g && g.classList.contains('pm-ms-group') && g.getAttribute('data-group') !== '';
    }
    $all('[data-group]').forEach(function (g) {
        var target = g.querySelector('.pm-tl-card') || g;
        g.addEventListener('dragover', function (e) {
            if (dragTask) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                target.classList.add('pm-dragover');
            } else if (isReorderTarget(g)) {
                e.preventDefault();
                var r = g.getBoundingClientRect();
                var before = e.clientY < r.top + r.height / 2;
                g.classList.toggle('pm-drop-before', before);
                g.classList.toggle('pm-drop-after', !before);
            }
        });
        g.addEventListener('dragleave', function (e) {
            if (!g.contains(e.relatedTarget)) { target.classList.remove('pm-dragover'); g.classList.remove('pm-drop-before', 'pm-drop-after'); }
        });
        g.addEventListener('drop', function (e) {
            if (dragTask) {
                e.preventDefault();
                var tid = dragTask.getAttribute('data-tid'); dragTask = null;
                clearHints();
                moveTask(tid, g.getAttribute('data-group'));
            } else if (isReorderTarget(g)) {
                e.preventDefault();
                var before = g.classList.contains('pm-drop-before');
                var moved = dragMs; dragMs = null;
                clearHints();
                g.parentNode.insertBefore(moved, before ? g : g.nextSibling);
                saveMilestoneOrder();
            }
        });
    });

    // ── Reorder milestones (By Milestone view): drag the grip, or the arrow buttons ──
    function milestoneGroups() {
        return $all('.pm-ms-group[data-group]').filter(function (g) { return g.getAttribute('data-group') !== ''; });
    }
    function renumber() {
        var list = milestoneGroups();
        list.forEach(function (g, i) {
            var num = g.querySelector('[data-ms-num]');
            if (num) num.textContent = i + 1;
            var up = g.querySelector('[data-ms-move="up"]'), down = g.querySelector('[data-ms-move="down"]');
            if (up)   up.disabled   = i === 0;
            if (down) down.disabled = i === list.length - 1;
        });
    }
    function saveMilestoneOrder() {
        renumber();
        orderChanged = true;
        var ids = milestoneGroups().map(function (g) { return Number(g.getAttribute('data-group')); });
        sendJson('POST', reorderUrl, { ids: ids }).catch(function (err) {
            alert('Could not reorder milestones: ' + err.message);
            window.location.reload();
        });
    }
    milestoneGroups().forEach(function (g) {
        var grip = g.querySelector('.pm-ms-grip[draggable="true"]');
        if (grip) {
            grip.addEventListener('dragstart', function (e) {
                dragMs = g;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', 'milestone:' + g.getAttribute('data-group'));
                e.dataTransfer.setDragImage(g.querySelector('.pm-ms-head'), 16, 16);
                requestAnimationFrame(function () { g.classList.add('pm-dragging'); });
            });
            grip.addEventListener('dragend', function () {
                dragMs = null;
                g.classList.remove('pm-dragging');
                clearHints();
            });
        }
        $all('[data-ms-move]', g).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var list = milestoneGroups(), i = list.indexOf(g);
                if (btn.getAttribute('data-ms-move') === 'up' && i > 0) g.parentNode.insertBefore(g, list[i - 1]);
                else if (btn.getAttribute('data-ms-move') === 'down' && i < list.length - 1) g.parentNode.insertBefore(g, list[i + 1].nextSibling);
                else return;
                saveMilestoneOrder();
            });
        });
    });
    renumber();

    // ── Dialogs ──
    $all('dialog.pm-dialog').forEach(function (d) {
        $all('[data-dialog-close]', d).forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
        d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
    });

    // New task (toolbar, timeline modal, or a milestone's + which preselects that milestone)
    var taskDialog = document.getElementById('pm-task-dialog');
    var taskForm   = document.getElementById('pm-task-form');
    function openTaskDialog(milestoneId, keepValues) {
        if (!keepValues) {
            taskForm.reset();
            taskForm.elements.namedItem('milestone_id').value = milestoneId || '';
        }
        taskDialog.showModal();
        taskForm.elements.namedItem('title').focus();
    }
    $all('[data-task-new]').forEach(function (b) { b.addEventListener('click', function () { openTaskDialog(''); }); });
    $all('[data-ms-add]').forEach(function (b) { b.addEventListener('click', function () { openTaskDialog(b.getAttribute('data-ms-add')); }); });

    // ── Assign task to project members (assignee chips → dialog → PATCH) ──
    // Every on-screen copy of the task (page view + timeline modal) is updated from the response.
    var assignDialog = document.getElementById('pm-assign-dialog');
    var assignSearch = assignDialog.querySelector('[data-assign-search]');
    var assignBtn    = null;
    function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function renderChips(btn, list) {
        var chip = function (u) { return '<span class="pm-assignee-chip" title="' + escHtml(u.name) + '">' + escHtml((u.name || '?').charAt(0).toUpperCase()) + '</span>'; };
        btn.innerHTML = !list.length
            ? '<span class="pm-assignees-empty"><i class="fa fa-user-plus"></i> Assign</span>'
            : list.slice(0, 3).map(chip).join('')
              + (list.length > 3 ? '<span class="pm-assignee-chip pm-assignee-chip--more">+' + (list.length - 3) + '</span>' : '')
              + (list.length === 1 ? '<span class="pm-assignees-name">' + escHtml(list[0].name) + '</span>' : '');
        btn.setAttribute('data-assignees', JSON.stringify(list));
    }
    $all('[data-assign-url]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            assignBtn = btn;
            var ids = JSON.parse(btn.getAttribute('data-assignees') || '[]').map(function (u) { return String(u.id); });
            assignDialog.querySelector('[data-assign-head]').textContent = 'Assign — ' + btn.getAttribute('data-assign-title');
            $all('.pm-assign-item', assignDialog).forEach(function (l) {
                l.style.display = '';
                l.querySelector('input').checked = ids.indexOf(l.querySelector('input').value) !== -1;
            });
            if (assignSearch) assignSearch.value = '';
            assignDialog.showModal();
            if (assignSearch) assignSearch.focus();
        });
    });
    if (assignSearch) assignSearch.addEventListener('input', function () {
        var q = assignSearch.value.trim().toLowerCase();
        $all('.pm-assign-item', assignDialog).forEach(function (l) { l.style.display = !q || l.getAttribute('data-q').indexOf(q) !== -1 ? '' : 'none'; });
    });
    assignDialog.querySelector('[data-assign-save]').addEventListener('click', function () {
        if (!assignBtn) return;
        var url  = assignBtn.getAttribute('data-assign-url');
        var ids  = $all('.pm-assign-item input:checked', assignDialog).map(function (i) { return Number(i.value); });
        var btns = $all('[data-assign-url]').filter(function (b) { return b.getAttribute('data-assign-url') === url; });
        assignDialog.close();
        btns.forEach(function (b) { b.disabled = true; });
        sendJson('PATCH', url, { assignee_ids: ids })
            .then(function (res) { btns.forEach(function (b) { renderChips(b, res.data.assignees); }); })
            .catch(function (err) { alert('Could not assign task: ' + err.message); })
            .then(function () { btns.forEach(function (b) { b.disabled = false; }); });
    });

    // Milestone (new / edit)
    var msDialog = document.getElementById('pm-ms-dialog');
    var msForm   = document.getElementById('pm-ms-form');
    function openMilestoneDialog(ms) {
        var isEdit = !!(ms && ms.id);
        msForm.action = isEdit ? updateUrl.replace('__ID__', ms.id) : storeUrl;
        msForm.querySelector('[name="_method"]').disabled = !isEdit;
        msForm.querySelector('[name="_ms_form"]').value = isEdit ? ms.id : 'new';
        ['name', 'start_date', 'due_date', 'sort_order', 'description'].forEach(function (f) {
            var v = ms ? ms[f] : null;
            msForm.elements.namedItem(f).value = v === null || v === undefined ? '' : v;
        });
        msDialog.querySelector('[data-ms-title]').textContent  = isEdit ? 'Edit Milestone' : 'New Milestone';
        msDialog.querySelector('[data-ms-submit]').textContent = isEdit ? 'Save Changes' : 'Add Milestone';
        msDialog.showModal();
        msForm.elements.namedItem('name').focus();
    }
    msForm.addEventListener('submit', function (e) {
        var s = msForm.elements.namedItem('start_date').value, d = msForm.elements.namedItem('due_date').value;
        if (s && d && d < s) { e.preventDefault(); alert('End date must be on or after the start date.'); }
    });
    $all('[data-ms-new]').forEach(function (b) { b.addEventListener('click', function () { openMilestoneDialog(null); }); });
    $all('[data-ms-edit]').forEach(function (b) {
        b.addEventListener('click', function () { openMilestoneDialog(JSON.parse(b.getAttribute('data-ms-edit'))); });
    });

    // ── Timeline helpers (Timeline tab and modal) ──
    function scrollToToday(scroller, root) {
        var el = root && root.querySelector('.pm-tl-today');
        if (!el) return;
        if (!scroller) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
        var top = scroller.scrollTop + el.getBoundingClientRect().top - scroller.getBoundingClientRect().top - scroller.clientHeight / 2;
        scroller.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }
    function toggleCompact(root, btn) {
        var on = root.classList.toggle('compact');
        btn.querySelector('i').className = 'fa ' + (on ? 'fa-expand' : 'fa-compress');
        btn.querySelector('span').textContent = on ? 'Show Tasks' : 'Hide Tasks';
    }
    var pageTl = document.getElementById('pm-timeline');
    var todayBtn = document.getElementById('pm-tl-today-btn');
    if (todayBtn) todayBtn.addEventListener('click', function () { scrollToToday(null, pageTl); });
    var compactBtn = document.getElementById('pm-tl-compact-btn');
    if (compactBtn) compactBtn.addEventListener('click', function () { toggleCompact(pageTl, compactBtn); });

    // ── Full timeline modal ──
    var overlay = document.getElementById('pm-tlm');
    var scroller = document.getElementById('pm-tlm-scroll');
    function openTimelineModal(jumpToToday) {
        if (orderChanged) {   // reload so the modal shows the new milestone order
            var url = new URL(window.location.href);
            url.searchParams.set('tl', '1');
            window.location.href = url;
            return;
        }
        overlay.hidden = false;
        document.body.style.overflow = 'hidden';
        setUrlParam('tl', '1');   // form posts return here with the modal open
        spy();
        if (jumpToToday) scrollToToday(scroller, modalRoot);
    }
    function closeTimelineModal() {
        overlay.hidden = true;
        document.body.style.overflow = '';
        setUrlParam('tl', null);
    }
    $all('[data-tlm-open]').forEach(function (b) { b.addEventListener('click', function () { openTimelineModal(true); }); });
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeTimelineModal(); });
    document.addEventListener('keydown', function (e) {
        // Esc closes a stacked dialog first (natively); only then the modal
        if (e.key === 'Escape' && !overlay.hidden && !document.querySelector('dialog[open]')) closeTimelineModal();
    });
    $all('[data-tlm]', overlay).forEach(function (btn) {
        btn.addEventListener('click', function () {
            var act = btn.getAttribute('data-tlm');
            if (act === 'close') closeTimelineModal();
            if (act === 'today') scrollToToday(scroller, modalRoot);
            if (act === 'compact' && modalRoot) toggleCompact(modalRoot, btn);
            if (act === 'max') {
                var on = overlay.classList.toggle('pm-tlm--max');
                btn.title = on ? 'Restore size' : 'Maximize';
                btn.querySelector('i').className = 'fa ' + (on ? 'fa-down-left-and-up-right-to-center' : 'fa-up-right-and-down-left-from-center');
            }
        });
    });

    // Jump list: click to scroll to a milestone; highlight the one in view while scrolling
    function itemFor(key) { return modalRoot && modalRoot.querySelector('[data-group="' + key + '"]'); }
    $all('[data-nav-group]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = itemFor(btn.getAttribute('data-nav-group'));
            if (!item) return;
            var top = scroller.scrollTop + item.getBoundingClientRect().top - scroller.getBoundingClientRect().top - 8;
            scroller.scrollTo({ top: top, behavior: 'smooth' });
            item.classList.remove('pm-tl-flash'); void item.offsetWidth; item.classList.add('pm-tl-flash');
            setTimeout(function () { item.classList.remove('pm-tl-flash'); }, 1200);
        });
    });
    function spy() {
        if (!modalRoot || overlay.hidden) return;
        var top = scroller.getBoundingClientRect().top;
        var items = $all('.pm-tl-item[data-group]', modalRoot);
        var current = items.length ? items[0].getAttribute('data-group') : null;
        items.forEach(function (el) {
            if (el.getBoundingClientRect().top - top <= 60) current = el.getAttribute('data-group');
        });
        $all('[data-nav-group]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-nav-group') === current); });
    }
    scroller.addEventListener('scroll', spy, { passive: true });

    refresh();

    // Reopen whatever was open before a form post / validation error
    if (new URLSearchParams(window.location.search).get('tl') === '1') openTimelineModal(false);
    @if($taskErrors)
        openTaskDialog(null, true);
    @endif
    @if($msErrors)
        openMilestoneDialog({
            id: @js($msFormOld === 'new' ? null : (int) $msFormOld),
            name: @js(old('name')), start_date: @js(old('start_date')), due_date: @js(old('due_date')),
            sort_order: @js(old('sort_order')), description: @js(old('description'))
        });
    @endif
})();
</script>
@endsection
