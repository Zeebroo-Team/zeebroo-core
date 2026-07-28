@extends('theme::layouts.app', ['title' => $project->name, 'heading' => $project->name])

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
    $taskStatusColor = [
        'todo'        => '#6b7280',
        'in_progress' => '#2563eb',
        'review'      => '#7c3aed',
        'done'        => '#16a34a',
    ];
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pm-stat-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:0 0 18px;}
@media(min-width:640px){.pm-stat-grid{grid-template-columns:repeat(4,1fr);}}
.pm-stat{border:1px solid var(--border);border-radius:11px;padding:13px 15px;background:var(--card);}
.pm-stat__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 5px;}
.pm-stat__value{font-size:26px;font-weight:800;color:var(--text);margin:0;line-height:1.1;}
.pm-meta-bar{display:flex;flex-wrap:wrap;gap:10px 18px;align-items:center;padding:10px 14px;border:1px solid var(--border);border-radius:10px;background:var(--card);margin:0 0 18px;font-size:12.5px;}
.pm-meta-bar__item{display:flex;align-items:center;gap:5px;color:var(--muted);}
.pm-section-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 10px;}
.pm-section-head h3{margin:0;font-size:14px;font-weight:800;color:var(--text);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('projectmanage::partials.pm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    {{-- Meta bar --}}
    <div class="pm-meta-bar">
        @if($project->client_name)
            <span class="pm-meta-bar__item"><i class="fa fa-building"></i> {{ $project->client_name }}</span>
        @endif
        <span class="pm-meta-bar__item">
            <i class="fa fa-circle-dot" style="color:{{ $priorityColor[$project->priority] ?? '#6b7280' }};"></i>
            <span style="color:{{ $priorityColor[$project->priority] ?? '#6b7280' }};font-weight:600;">{{ ucfirst($project->priority) }} priority</span>
        </span>
        <span class="pm-meta-bar__item">
            <span class="pcat-badge" style="border-color:{{ $statusColor[$project->status] ?? '#6b7280' }};color:{{ $statusColor[$project->status] ?? '#6b7280' }};">
                {{ ucfirst(str_replace('_', ' ', $project->status)) }}
            </span>
        </span>
        @if($project->start_date)
            <span class="pm-meta-bar__item"><i class="fa fa-calendar-days"></i> Start: {{ $project->start_date->format('d M Y') }}</span>
        @endif
        @if($project->due_date)
            <span class="pm-meta-bar__item"><i class="fa fa-flag"></i> Due: {{ $project->due_date->format('d M Y') }}</span>
        @endif
        @if($project->budget)
            <span class="pm-meta-bar__item"><i class="fa fa-coins"></i> Budget: {{ number_format((float) $project->budget, 2) }}</span>
        @endif
    </div>

    {{-- Task stat cards --}}
    <div class="pm-stat-grid">
        @foreach(['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'Review', 'done' => 'Done'] as $key => $label)
            <div class="pm-stat" style="border-top:3px solid {{ $taskStatusColor[$key] }};">
                <p class="pm-stat__label">{{ $label }}</p>
                <p class="pm-stat__value" style="color:{{ $taskStatusColor[$key] }};">{{ $stats[$key] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Action buttons --}}
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
        <a href="{{ route('pm.projects.tasks.board', $project) }}" class="linkbtn"
           style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-table-columns"></i> View Board
        </a>
        <a href="{{ route('pm.projects.tasks.index', $project) }}" class="linkbtn"
           style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-list"></i> View All Tasks
        </a>
        <a href="{{ route('pm.projects.edit', $project) }}" class="linkbtn"
           style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-edit"></i> Edit Project
        </a>
        @if(!$hasTasks)
            <form method="POST" action="{{ route('pm.projects.destroy', $project) }}" style="display:inline;"
                  onsubmit="return confirm('Delete this project? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="pcat-btn-del">
                    <i class="fa fa-trash"></i> Delete
                </button>
            </form>
        @endif
    </div>

    {{-- Milestones --}}
    <section style="margin-bottom:24px;">
        <div class="pm-section-head">
            <h3><i class="fa fa-flag-checkered" style="color:var(--muted);margin-right:5px;"></i> Milestones</h3>
        </div>
        @if($milestones->isNotEmpty())
            <div class="pcat-table-wrap" style="margin-bottom:14px;">
                <table class="pcat-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Due date</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($milestones as $ms)
                            <tr>
                                <td style="font-weight:600;color:var(--text);">{{ $ms->name }}</td>
                                <td>{{ $ms->due_date ? $ms->due_date->format('d M Y') : '—' }}</td>
                                <td>
                                    @if($ms->isCompleted())
                                        <span class="pcat-badge pcat-badge--on"><i class="fa fa-check"></i> Completed</span>
                                    @else
                                        <span class="pcat-badge pcat-badge--off">Pending</span>
                                    @endif
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex;gap:6px;align-items:center;">
                                        @if(!$ms->isCompleted())
                                            <form method="POST" action="{{ route('pm.projects.milestones.complete', [$project, $ms]) }}" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="linkbtn" style="padding:4px 10px;font-size:11px;">
                                                    <i class="fa fa-check"></i> Complete
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('pm.projects.milestones.reopen', [$project, $ms]) }}" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="linkbtn" style="padding:4px 10px;font-size:11px;background:transparent;border:1px solid var(--border);color:var(--text);">
                                                    <i class="fa fa-rotate-left"></i> Reopen
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('pm.projects.milestones.destroy', [$project, $ms]) }}" style="display:inline;"
                                              onsubmit="return confirm('Delete milestone?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="pcat-btn-del" style="padding:4px 8px;font-size:11px;">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted" style="font-size:13px;margin:0 0 10px;">No milestones yet.</p>
        @endif

        {{-- Add milestone form --}}
        <div class="pcat-inline" style="padding:12px 14px;">
            <p style="margin:0 0 8px;font-size:12px;font-weight:700;color:var(--text);">Add milestone</p>
            <form method="POST" action="{{ route('pm.projects.milestones.store', $project) }}">
                @csrf
                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
                    <div class="pcat-field" style="flex:1;min-width:160px;">
                        <label>Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Milestone name…" maxlength="150" required>
                    </div>
                    <div class="pcat-field" style="width:160px;">
                        <label>Due date</label>
                        <input type="date" name="due_date" value="{{ old('due_date') }}">
                    </div>
                    <div style="padding-bottom:1px;">
                        <button type="submit" class="linkbtn" style="padding:9px 16px;font-size:13px;">
                            <i class="fa fa-plus"></i> Add
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    {{-- Recent tasks --}}
    <section>
        <div class="pm-section-head">
            <h3><i class="fa fa-list-check" style="color:var(--muted);margin-right:5px;"></i> Recent tasks</h3>
            <a href="{{ route('pm.projects.tasks.index', $project) }}" class="pcat-link">View all</a>
        </div>
        @if($recentTasks->isNotEmpty())
            <div class="pcat-table-wrap">
                <table class="pcat-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Assigned to</th>
                            <th>Due date</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentTasks as $t)
                            @php
                                $sc = $taskStatusColor[$t->status] ?? '#6b7280';
                                $overdue = $t->isOverdue();
                            @endphp
                            <tr>
                                <td style="font-weight:600;color:var(--text);">{{ $t->title }}</td>
                                <td>
                                    <span class="pcat-badge" style="border-color:{{ $sc }};color:{{ $sc }};">
                                        {{ ucfirst(str_replace('_', ' ', $t->status)) }}
                                    </span>
                                </td>
                                <td>{{ $t->assignedTo?->name ?? '—' }}</td>
                                <td style="{{ $overdue ? 'color:#dc2626;font-weight:700;' : '' }}">
                                    {{ $t->due_date ? $t->due_date->format('d M Y') : '—' }}
                                    @if($overdue)<span style="font-size:10px;"> (overdue)</span>@endif
                                </td>
                                <td style="text-align:right;">
                                    <a href="{{ route('pm.tasks.show', $t) }}" class="pcat-link">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted" style="font-size:13px;margin:0;">No tasks yet. <a href="{{ route('pm.projects.tasks.index', $project) }}" class="pcat-link">Add the first task</a></p>
        @endif
    </section>
</div>

<div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
    <a href="{{ route('pm.projects.index') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All Projects
    </a>
</div>
@endsection
