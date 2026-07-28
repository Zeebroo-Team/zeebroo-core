@extends('theme::layouts.app', ['title' => 'My Tasks', 'heading' => 'My Tasks'])

@php
    $priorityColor = ['high'=>'#dc2626','normal'=>'#f59e0b','low'=>'#6b7280'];
    $taskStatusColor = [
        'todo'=>'#6b7280','in_progress'=>'#2563eb','review'=>'#7c3aed','done'=>'#16a34a',
    ];
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('projectmanage::partials.pm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    {{-- Filter tabs --}}
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
        @foreach($filterTabs as $key => $label)
            <a href="{{ route('pm.my-tasks', ['filter' => $key]) }}"
               style="padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid var(--border);
                      {{ $filter === $key ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'background:transparent;color:var(--muted);' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($tasks->isEmpty())
        <div style="text-align:center;padding:36px 20px;">
            <i class="fa fa-list-check" style="font-size:28px;color:var(--muted);margin-bottom:10px;display:block;"></i>
            <p class="muted" style="font-size:14px;">No tasks found for this filter.</p>
        </div>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Project</th>
                        <th>Milestone</th>
                        <th>Priority</th>
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
                            <td>
                                @if($t->project)
                                    <a href="{{ route('pm.projects.show', $t->project) }}" class="pcat-link">{{ $t->project->name }}</a>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>{{ $t->milestone?->name ?? '—' }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:{{ $pc }};font-weight:600;">
                                    <span style="width:7px;height:7px;border-radius:50%;background:{{ $pc }};display:inline-block;flex-shrink:0;"></span>
                                    {{ ucfirst($t->priority) }}
                                </span>
                            </td>
                            <td style="{{ $overdue ? 'color:#dc2626;font-weight:700;' : '' }}">
                                {{ $t->due_date ? $t->due_date->format('d M Y') : '—' }}
                                @if($overdue)<span style="font-size:10px;"> (overdue)</span>@endif
                            </td>
                            <td>
                                <span class="pcat-badge" style="border-color:{{ $sc }};color:{{ $sc }};">
                                    {{ ucfirst(str_replace('_', ' ', $t->status)) }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;align-items:center;">
                                    <a href="{{ route('pm.tasks.show', $t) }}" class="pcat-link">
                                        <i class="fa fa-eye"></i> View
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
    <a href="{{ route('dashboard') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>
@endsection
