@extends('theme::layouts.app', ['title' => $task->title, 'heading' => $task->title])

@php
    $priorityColor = ['high'=>'#dc2626','normal'=>'#f59e0b','low'=>'#6b7280'];
    $taskStatusColor = [
        'todo'=>'#6b7280','in_progress'=>'#2563eb','review'=>'#7c3aed','done'=>'#16a34a',
    ];
    $sc      = $taskStatusColor[$task->status] ?? '#6b7280';
    $pc      = $priorityColor[$task->priority] ?? '#6b7280';
    $overdue = $task->isOverdue();
    $totalMinutes = $task->totalLoggedMinutes();
    $loggedHours  = floor($totalMinutes / 60);
    $loggedMins   = $totalMinutes % 60;
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pm-show-grid{display:grid;grid-template-columns:1fr 280px;gap:18px;align-items:start;}
@media(max-width:768px){.pm-show-grid{grid-template-columns:1fr;}}
.pm-sidebar{border:1px solid var(--border);border-radius:12px;padding:14px;background:var(--card);}
.pm-sidebar__row{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);font-size:12.5px;}
.pm-sidebar__row:last-child{border-bottom:none;}
.pm-sidebar__label{color:var(--muted);font-weight:600;}
.pm-comment{padding:10px 0;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
.pm-comment:last-child{border-bottom:none;}
.pm-comment__meta{font-size:11px;color:var(--muted);margin-bottom:4px;}
.pm-comment__body{font-size:13px;color:var(--text);line-height:1.5;margin:0;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('projectmanage::partials.pm-hub-nav')

    <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">
        <a href="{{ route('pm.projects.show', $task->project) }}" class="pcat-link">{{ $task->project->name }}</a>
        <span style="margin:0 4px;">/</span>
        <a href="{{ route('pm.projects.tasks.index', $task->project) }}" class="pcat-link">Tasks</a>
        <span style="margin:0 4px;">/</span>
        <span>{{ Str::limit($task->title, 40) }}</span>
    </div>

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    <div class="pm-show-grid">
        {{-- LEFT --}}
        <div>
            {{-- Task header --}}
            <div style="margin-bottom:16px;">
                <h2 style="margin:0 0 8px;font-size:18px;font-weight:800;color:var(--text);line-height:1.3;">{{ $task->title }}</h2>
                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    <span class="pcat-badge" style="border-color:{{ $sc }};color:{{ $sc }};">
                        {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                    </span>
                    <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:{{ $pc }};font-weight:600;">
                        <span style="width:8px;height:8px;border-radius:50%;background:{{ $pc }};display:inline-block;"></span>
                        {{ ucfirst($task->priority) }} priority
                    </span>
                    @if($task->milestone)
                        <span class="pcat-badge pcat-badge--off"><i class="fa fa-flag" style="margin-right:3px;"></i>{{ $task->milestone->name }}</span>
                    @endif
                    @if($task->due_date)
                        <span style="font-size:12px;{{ $overdue ? 'color:#dc2626;font-weight:700;' : 'color:var(--muted);' }}">
                            <i class="fa fa-calendar"></i>
                            Due {{ $task->due_date->format('d M Y') }}
                            @if($overdue) &mdash; overdue@endif
                        </span>
                    @endif
                </div>
            </div>

            @if($task->description)
                <div style="font-size:13.5px;line-height:1.6;color:var(--text);margin-bottom:20px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:var(--card);">
                    {{ $task->description }}
                </div>
            @endif

            {{-- Edit task link --}}
            <div style="margin-bottom:20px;">
                <a href="#pm-edit-task-form" class="pcat-link" id="pm-edit-toggle">
                    <i class="fa fa-edit"></i> Edit task
                </a>
                <div id="pm-edit-task-form" style="display:none;margin-top:10px;">
                    <section class="pcat-inline">
                        <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:var(--text);">Edit task</p>
                        <form method="POST" action="{{ route('pm.tasks.update', $task) }}">
                            @csrf
                            @method('PUT')
                            <div class="pcat-field" style="margin-bottom:10px;">
                                <label>Title *</label>
                                <input type="text" name="title" value="{{ old('title', $task->title) }}" maxlength="200" required>
                            </div>
                            <div class="pcat-field" style="margin-bottom:10px;">
                                <label>Description</label>
                                <textarea name="description" style="min-height:80px;">{{ old('description', $task->description) }}</textarea>
                            </div>
                            <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:10px;">
                                <div class="pcat-field">
                                    <label>Status</label>
                                    <select name="status">
                                        <option value="todo"        @selected(old('status',$task->status)==='todo')>To Do</option>
                                        <option value="in_progress" @selected(old('status',$task->status)==='in_progress')>In Progress</option>
                                        <option value="review"      @selected(old('status',$task->status)==='review')>Review</option>
                                        <option value="done"        @selected(old('status',$task->status)==='done')>Done</option>
                                    </select>
                                </div>
                                <div class="pcat-field">
                                    <label>Priority</label>
                                    <select name="priority">
                                        <option value="low"    @selected(old('priority',$task->priority)==='low')>Low</option>
                                        <option value="normal" @selected(old('priority',$task->priority)==='normal')>Normal</option>
                                        <option value="high"   @selected(old('priority',$task->priority)==='high')>High</option>
                                    </select>
                                </div>
                            </div>
                            <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:10px;">
                                <div class="pcat-field">
                                    <label>Assigned to</label>
                                    <select name="assigned_to">
                                        <option value="">Unassigned</option>
                                        @foreach($assignableUsers as $u)
                                            <option value="{{ $u->id }}" @selected(old('assigned_to',(string)$task->assigned_to)===(string)$u->id)>{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="pcat-field">
                                    <label>Milestone</label>
                                    <select name="milestone_id">
                                        <option value="">None</option>
                                        @foreach($milestones as $ms)
                                            <option value="{{ $ms->id }}" @selected(old('milestone_id',(string)$task->milestone_id)===(string)$ms->id)>{{ $ms->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:12px;">
                                <div class="pcat-field">
                                    <label>Due date</label>
                                    <input type="date" name="due_date" value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}">
                                </div>
                                <div class="pcat-field">
                                    <label>Estimated hours</label>
                                    <input type="number" name="estimated_hours" step="0.5" min="0"
                                           value="{{ old('estimated_hours', $task->estimated_hours) }}" placeholder="0.0">
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">
                                    <i class="fa fa-check"></i> Save
                                </button>
                                <button type="button" id="pm-edit-cancel"
                                        class="linkbtn" style="padding:8px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>

            {{-- Comments --}}
            <section style="margin-bottom:24px;">
                <h3 style="margin:0 0 10px;font-size:14px;font-weight:800;color:var(--text);">
                    <i class="fa fa-comments" style="color:var(--muted);margin-right:5px;"></i>
                    Comments ({{ $task->comments->count() }})
                </h3>
                @if($task->comments->isNotEmpty())
                    <div style="margin-bottom:12px;">
                        @foreach($task->comments as $c)
                            <div class="pm-comment">
                                <div class="pm-comment__meta">
                                    <strong style="color:var(--text);">{{ $c->user?->name ?? 'Unknown' }}</strong>
                                    &mdash; {{ $c->created_at->diffForHumans() }}
                                </div>
                                <p class="pm-comment__body">{{ $c->body }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="muted" style="font-size:13px;margin:0 0 10px;">No comments yet.</p>
                @endif
                <form method="POST" action="{{ route('pm.tasks.comment', $task) }}">
                    @csrf
                    <div class="pcat-field" style="margin-bottom:8px;">
                        <label>Add comment</label>
                        <textarea name="body" placeholder="Write a comment…" style="min-height:70px;" maxlength="5000">{{ old('body') }}</textarea>
                    </div>
                    <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;">
                        <i class="fa fa-paper-plane"></i> Post comment
                    </button>
                </form>
            </section>

            {{-- Time logs --}}
            <section>
                <h3 style="margin:0 0 10px;font-size:14px;font-weight:800;color:var(--text);">
                    <i class="fa fa-clock" style="color:var(--muted);margin-right:5px;"></i>
                    Time logs
                </h3>
                @if($task->timeLogs->isNotEmpty())
                    <div class="pcat-table-wrap" style="margin-bottom:12px;">
                        <table class="pcat-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Minutes</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($task->timeLogs as $tl)
                                    <tr>
                                        <td>{{ $tl->user?->name ?? '—' }}</td>
                                        <td>{{ $tl->logged_at->format('d M Y') }}</td>
                                        <td>{{ $tl->minutes }} min</td>
                                        <td>{{ $tl->note ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="muted" style="font-size:13px;margin:0 0 10px;">No time logged yet.</p>
                @endif
                <section class="pcat-inline" style="padding:10px 12px;">
                    <p style="margin:0 0 8px;font-size:12px;font-weight:700;color:var(--text);">Log time</p>
                    <form method="POST" action="{{ route('pm.tasks.time', $task) }}">
                        @csrf
                        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
                            <div class="pcat-field" style="width:110px;">
                                <label>Minutes *</label>
                                <input type="number" name="minutes" min="1" max="32767"
                                       value="{{ old('minutes') }}" placeholder="60" required>
                            </div>
                            <div class="pcat-field" style="width:155px;">
                                <label>Date *</label>
                                <input type="date" name="logged_at" value="{{ old('logged_at', now()->toDateString()) }}" required>
                            </div>
                            <div class="pcat-field" style="flex:1;min-width:150px;">
                                <label>Note</label>
                                <input type="text" name="note" value="{{ old('note') }}" placeholder="Optional note…" maxlength="255">
                            </div>
                            <div style="padding-bottom:1px;">
                                <button type="submit" class="linkbtn" style="padding:9px 14px;font-size:13px;">
                                    <i class="fa fa-clock"></i> Log
                                </button>
                            </div>
                        </div>
                    </form>
                </section>
            </section>
        </div>

        {{-- RIGHT SIDEBAR --}}
        <aside class="pm-sidebar">
            <div class="pm-sidebar__row">
                <span class="pm-sidebar__label">Assigned to</span>
                <span style="font-weight:600;color:var(--text);">{{ $task->assignedTo?->name ?? 'Unassigned' }}</span>
            </div>
            <div class="pm-sidebar__row">
                <span class="pm-sidebar__label">Project</span>
                <a href="{{ route('pm.projects.show', $task->project) }}" class="pcat-link">{{ $task->project->name }}</a>
            </div>
            <div class="pm-sidebar__row">
                <span class="pm-sidebar__label">Est. hours</span>
                <span style="color:var(--text);">
                    {{ $task->estimated_hours !== null ? number_format((float)$task->estimated_hours, 1) . ' h' : '—' }}
                </span>
            </div>
            <div class="pm-sidebar__row">
                <span class="pm-sidebar__label">Logged</span>
                <span style="color:var(--text);">
                    @if($totalMinutes > 0)
                        @if($loggedHours > 0) {{ $loggedHours }}h @endif
                        @if($loggedMins > 0) {{ $loggedMins }}m @endif
                    @else
                        0 min
                    @endif
                </span>
            </div>

            <div style="margin-top:12px;display:flex;flex-direction:column;gap:6px;">
                @if(!$task->isCompleted())
                    <form method="POST" action="{{ route('pm.tasks.complete', $task) }}">
                        @csrf
                        <button type="submit" class="linkbtn" style="width:100%;padding:9px;font-size:13px;text-align:center;">
                            <i class="fa fa-check"></i> Mark as done
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('pm.tasks.reopen', $task) }}">
                        @csrf
                        <button type="submit" class="linkbtn" style="width:100%;padding:9px;font-size:13px;text-align:center;background:transparent;border:1px solid var(--border);color:var(--text);">
                            <i class="fa fa-rotate-left"></i> Reopen task
                        </button>
                    </form>
                @endif
                <form method="POST" action="{{ route('pm.tasks.destroy', $task) }}"
                      onsubmit="return confirm('Delete this task?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="pcat-btn-del" style="width:100%;padding:8px;font-size:12px;text-align:center;">
                        <i class="fa fa-trash"></i> Delete task
                    </button>
                </form>
            </div>
        </aside>
    </div>
</div>

<div style="margin-top:14px;">
    <a href="{{ route('pm.projects.tasks.index', $task->project) }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Back to tasks
    </a>
</div>

<script>
(function () {
    var toggle  = document.getElementById('pm-edit-toggle');
    var form    = document.getElementById('pm-edit-task-form');
    var cancel  = document.getElementById('pm-edit-cancel');
    if (!toggle || !form) return;
    toggle.addEventListener('click', function (e) {
        e.preventDefault();
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    });
    if (cancel) {
        cancel.addEventListener('click', function () { form.style.display = 'none'; });
    }
})();
</script>
@endsection
