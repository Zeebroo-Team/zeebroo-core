@extends('theme::layouts.app', ['title' => 'Projects', 'heading' => 'Projects'])

@php
    $statusColor = [
        'active'    => '#16a34a',
        'on_hold'   => '#f59e0b',
        'completed' => '#2563eb',
        'archived'  => '#6b7280',
    ];
    $priorityColor = [
        'high'   => '#dc2626',
        'normal' => '#f59e0b',
        'low'    => '#6b7280',
    ];
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pm-ov-welcome{border:1px solid var(--border);border-radius:12px;padding:18px 20px;background:color-mix(in srgb,var(--primary) 6%,var(--card));margin-bottom:16px;}
.pm-ov-welcome h2{margin:0 0 6px;font-size:18px;color:var(--text);}
.pm-ov-welcome p{margin:0 0 14px;font-size:13px;color:var(--muted);line-height:1.5;max-width:680px;}
.pm-ov-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:16px;}
.pm-ov-stat{border:1px solid var(--border);border-radius:11px;padding:14px 16px;background:var(--card);border-top:3px solid var(--pm-ov-color,var(--primary));}
.pm-ov-stat strong{display:block;font-size:24px;color:var(--text);}
.pm-ov-stat span{font-size:12px;color:var(--muted);}
.pm-ov-tips{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px;}
.pm-ov-tip{border:1px solid var(--border);border-radius:11px;padding:14px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.pm-ov-tip .fa{color:var(--primary);margin-bottom:8px;font-size:16px;}
.pm-ov-tip strong{display:block;font-size:13px;color:var(--text);margin-bottom:4px;}
.pm-ov-tip p{margin:0;font-size:12px;color:var(--muted);line-height:1.45;}
.pm-ov-recent{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;}
.pm-ov-card{border:1px solid var(--border);border-radius:11px;padding:14px;background:var(--card);text-decoration:none;display:block;}
.pm-ov-card strong{display:block;font-size:13px;color:var(--text);margin-bottom:4px;}
.pm-ov-card span{font-size:12px;color:var(--muted);}
.pm-ov-card .pm-ov-meta{margin-top:8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:11px;color:var(--muted);}
.pm-ov-card .pm-ov-priority{display:inline-flex;align-items:center;gap:5px;font-weight:600;}
.pm-ov-card .pm-ov-dot{width:7px;height:7px;border-radius:50%;display:inline-block;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('projectmanage::partials.pm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div class="pm-ov-welcome">
        <h2><i class="fa fa-diagram-project"></i> Welcome to Projects</h2>
        <p>Plan delivery, track tasks and manage boards for every project — in-house or for a customer. Assign work to a branch, department, employee, property or customer, follow progress on a kanban board, and keep on top of what's due.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('pm.projects.index', ['new' => 1]) }}" class="linkbtn"
               style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> New Project
            </a>
            <a href="{{ route('pm.projects.index') }}" class="linkbtn"
               style="padding:9px 18px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-folder-open"></i> View All Projects
            </a>
        </div>
    </div>

    <div class="pm-ov-stats">
        <div class="pm-ov-stat" style="--pm-ov-color:#2563eb;"><strong>{{ $totalCount }}</strong><span>Total Projects</span></div>
        <div class="pm-ov-stat" style="--pm-ov-color:#16a34a;"><strong>{{ $activeCount }}</strong><span>Active</span></div>
        <div class="pm-ov-stat" style="--pm-ov-color:#f59e0b;"><strong>{{ $onHoldCount }}</strong><span>On Hold</span></div>
        <div class="pm-ov-stat" style="--pm-ov-color:#7c3aed;"><strong>{{ $completedCount }}</strong><span>Completed</span></div>
    </div>

    <h3 style="font-size:13px;color:var(--text);margin:0 0 10px;"><i class="fa fa-lightbulb" style="color:var(--primary);"></i> Quick Tips</h3>
    <div class="pm-ov-tips">
        <div class="pm-ov-tip">
            <i class="fa fa-users"></i>
            <strong>Assign work anywhere</strong>
            <p>Hand tasks to a branch, department, employee, property or customer.</p>
        </div>
        <div class="pm-ov-tip">
            <i class="fa fa-table-columns"></i>
            <strong>Track on a kanban board</strong>
            <p>Drag tasks across stages and watch delivery progress in real time.</p>
        </div>
        <div class="pm-ov-tip">
            <i class="fa fa-flag"></i>
            <strong>Set priority &amp; due dates</strong>
            <p>Flag high-priority work and keep every deadline in view.</p>
        </div>
        <div class="pm-ov-tip">
            <i class="fa fa-bell"></i>
            <strong>Stay notified</strong>
            <p>Get notified the moment a task is assigned to you or its status changes.</p>
        </div>
    </div>

    @if($hasProjects)
        <div class="pcat-toolbar" style="margin-top:16px;">
            <h3 style="font-size:13px;color:var(--text);margin:0;"><i class="fa fa-clock-rotate-left" style="color:var(--primary);"></i> Recent Projects</h3>
            <a href="{{ route('pm.projects.index') }}" class="pcat-link">View all <i class="fa fa-arrow-right"></i></a>
        </div>
        <div class="pm-ov-recent">
            @foreach($recentProjects as $p)
                <a href="{{ route('pm.projects.show', $p) }}" class="pm-ov-card">
                    <strong>{{ $p->name }}</strong>
                    <span>{{ $p->client_name ?? \Illuminate\Support\Str::limit($p->description ?? 'No description', 50) }}</span>
                    <div class="pm-ov-meta">
                        <span class="pm-ov-priority" style="color:{{ $priorityColor[$p->priority] ?? '#6b7280' }};">
                            <span class="pm-ov-dot" style="background:{{ $statusColor[$p->status] ?? '#6b7280' }};"></span>
                            {{ ucfirst(str_replace('_', ' ', $p->status)) }}
                        </span>
                        <span><i class="fa fa-list-check"></i> {{ $p->tasks_count ?? 0 }} task{{ ($p->tasks_count ?? 0) === 1 ? '' : 's' }}</span>
                        @if($p->due_date)
                            <span><i class="fa fa-calendar"></i> {{ $p->due_date->format('d M Y') }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <section class="pcat-inline" style="margin-top:16px;">
            <h2>No projects yet</h2>
            <p class="pcat-muted">Create your first project to start tracking tasks and delivery.</p>
            <a href="{{ route('pm.projects.index', ['new' => 1]) }}" class="linkbtn" style="padding:9px 20px;font-size:13px;display:inline-flex;align-items:center;gap:6px;margin-top:10px;">
                <i class="fa fa-plus"></i> Create project
            </a>
        </section>
    @endif
</div>
@endsection
