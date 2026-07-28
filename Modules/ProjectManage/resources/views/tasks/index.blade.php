@extends('theme::layouts.app', ['title' => 'Tasks — ' . $project->name, 'heading' => 'Tasks'])

@php
    $priorityColor = ['high'=>'#dc2626','normal'=>'#f59e0b','low'=>'#6b7280'];
    $taskStatusColor = [
        'todo'=>'#6b7280','in_progress'=>'#2563eb','review'=>'#7c3aed','done'=>'#16a34a',
    ];
    $modalOpen = $errors->any() && request()->old('title');
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('projectmanage::partials.pm-hub-nav')

    {{-- Breadcrumb --}}
    <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">
        <a href="{{ route('pm.projects.show', $project) }}" class="pcat-link">{{ $project->name }}</a>
        <span style="margin:0 4px;">/</span>
        <span>Tasks</span>
    </div>

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    {{-- Add task inline form at top --}}
    <section class="pcat-inline" style="margin-bottom:16px;">
        <p style="margin:0 0 8px;font-size:12px;font-weight:700;color:var(--text);">Add task</p>
        @if($errors->any())
            <div class="pcat-banner pcat-banner--err" style="margin-bottom:8px;">
                @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('pm.projects.tasks.store', $project) }}">
            @csrf
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
                <div class="pcat-field" style="flex:1;min-width:180px;">
                    <label>Title *</label>
                    <input type="text" name="title" value="{{ old('title') }}" placeholder="Task title…" maxlength="200" required>
                </div>
                <div class="pcat-field" style="width:130px;">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="normal" @selected(old('priority','normal')==='normal')>Normal</option>
                        <option value="high"   @selected(old('priority')==='high')>High</option>
                        <option value="low"    @selected(old('priority')==='low')>Low</option>
                    </select>
                </div>
                <div class="pcat-field" style="width:160px;">
                    <label>Assigned to</label>
                    <select name="assigned_to">
                        <option value="">Unassigned</option>
                        @foreach($assignableUsers as $u)
                            <option value="{{ $u->id }}" @selected(old('assigned_to')==(string)$u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pcat-field" style="width:155px;">
                    <label>Milestone</label>
                    <select name="milestone_id">
                        <option value="">None</option>
                        @foreach($milestones as $ms)
                            <option value="{{ $ms->id }}" @selected(old('milestone_id')==(string)$ms->id)>{{ $ms->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pcat-field" style="width:155px;">
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
    </section>

    {{-- Status filter tabs --}}
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
        @foreach($statusTabs as $key => $label)
            <a href="{{ route('pm.projects.tasks.index', array_merge(['project'=>$project->id], $key !== '' ? ['status'=>$key] : [])) }}"
               style="padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid var(--border);
                      {{ $statusFilter === $key ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'background:transparent;color:var(--muted);' }}">
                {{ $label }}
            </a>
        @endforeach
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
            <a href="{{ route('pm.projects.tasks.board', $project) }}" class="linkbtn"
               style="padding:5px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
                <i class="fa fa-table-columns"></i> Board
            </a>
        </div>
    </div>

    @if($tasks->isEmpty())
        <p class="muted" style="margin:20px 0;font-size:13px;">No tasks found.</p>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Milestone</th>
                        <th>Priority</th>
                        <th>Assigned to</th>
                        <th>Due date</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $t)
                        @php
                            $sc      = $taskStatusColor[$t->status] ?? '#6b7280';
                            $pc      = $priorityColor[$t->priority] ?? '#6b7280';
                            $overdue = $t->isOverdue();
                        @endphp
                        <tr>
                            <td>
                                <strong style="color:var(--text);">
                                    <a href="{{ route('pm.tasks.show', $t) }}" style="color:inherit;text-decoration:none;">{{ $t->title }}</a>
                                </strong>
                            </td>
                            <td>{{ $t->milestone?->name ?? '—' }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:{{ $pc }};font-weight:600;">
                                    <span style="width:7px;height:7px;border-radius:50%;background:{{ $pc }};display:inline-block;flex-shrink:0;"></span>
                                    {{ ucfirst($t->priority) }}
                                </span>
                            </td>
                            <td>{{ $t->assignedTo?->name ?? '—' }}</td>
                            <td style="{{ $overdue ? 'color:#dc2626;font-weight:700;' : '' }}">
                                {{ $t->due_date ? $t->due_date->format('d M Y') : '—' }}
                            </td>
                            <td>
                                <span class="pcat-badge" style="border-color:{{ $sc }};color:{{ $sc }};">
                                    {{ ucfirst(str_replace('_', ' ', $t->status)) }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;align-items:center;">
                                    <a href="{{ route('pm.tasks.show', $t) }}" class="pcat-link">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    @if(!$t->isCompleted())
                                        <form method="POST" action="{{ route('pm.tasks.complete', $t) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="linkbtn" style="padding:3px 8px;font-size:11px;" title="Mark done">
                                                <i class="fa fa-check"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('pm.tasks.reopen', $t) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="linkbtn" style="padding:3px 8px;font-size:11px;background:transparent;border:1px solid var(--border);color:var(--text);" title="Reopen">
                                                <i class="fa fa-rotate-left"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('pm.tasks.destroy', $t) }}" style="display:inline;"
                                          onsubmit="return confirm('Delete task?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="pcat-btn-del" style="padding:3px 7px;font-size:11px;" title="Delete">
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
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('pm.projects.show', $project) }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Back to project
    </a>
</div>
@endsection
