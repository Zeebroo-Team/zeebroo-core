@extends('theme::layouts.app', ['title' => 'Board — ' . $project->name, 'heading' => 'Board'])

@php
    $colMeta = [
        'todo'        => ['label' => 'To Do',       'color' => '#6b7280'],
        'in_progress' => ['label' => 'In Progress',  'color' => '#2563eb'],
        'review'      => ['label' => 'Review',       'color' => '#7c3aed'],
        'done'        => ['label' => 'Done',         'color' => '#16a34a'],
    ];
    $priorityColor = ['high'=>'#dc2626','normal'=>'#f59e0b','low'=>'#6b7280'];
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pm-board{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px;align-items:start;}
.pm-col{border:1px solid var(--border);border-radius:12px;background:color-mix(in srgb,var(--card) 96%,transparent);padding:12px;}
.pm-col__head{display:flex;align-items:center;gap:7px;margin-bottom:10px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;}
.pm-col__count{font-size:11px;padding:2px 6px;border-radius:999px;font-weight:700;}
.pm-task-card{border:1px solid var(--border);border-radius:9px;background:var(--card);padding:10px 11px;margin-bottom:8px;}
.pm-task-card:last-child{margin-bottom:0;}
.pm-task-card__title{font-size:13px;font-weight:600;color:var(--text);margin:0 0 5px;line-height:1.3;}
.pm-task-card__meta{display:flex;flex-wrap:wrap;align-items:center;gap:5px;font-size:11px;color:var(--muted);}
.pm-task-card__actions{margin-top:8px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
.pm-board-add-form{margin-top:10px;border-top:1px solid var(--border);padding-top:10px;}
.pm-board-add-toggle{font-size:12px;color:var(--primary);font-weight:600;background:none;border:none;cursor:pointer;padding:0;display:inline-flex;align-items:center;gap:4px;}
.pm-board-add-body{display:none;margin-top:8px;}
.pm-board-add-body.open{display:block;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('projectmanage::partials.pm-hub-nav')

    <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">
        <a href="{{ route('pm.projects.show', $project) }}" class="pcat-link">{{ $project->name }}</a>
        <span style="margin:0 4px;">/</span>
        <span>Board</span>
    </div>

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center;">
        <a href="{{ route('pm.projects.tasks.index', $project) }}" class="linkbtn"
           style="padding:6px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
            <i class="fa fa-list"></i> List view
        </a>
    </div>

    <div class="pm-board">
        @foreach($colMeta as $status => $meta)
            @php $colTasks = $columns[$status] ?? collect(); @endphp
            <div class="pm-col" style="border-top:3px solid {{ $meta['color'] }};">
                <div class="pm-col__head" style="color:{{ $meta['color'] }};">
                    {{ $meta['label'] }}
                    <span class="pm-col__count" style="background:{{ $meta['color'] }}20;color:{{ $meta['color'] }};">
                        {{ $colTasks->count() }}
                    </span>
                </div>

                @foreach($colTasks as $t)
                    @php $overdue = $t->isOverdue(); @endphp
                    <div class="pm-task-card">
                        <p class="pm-task-card__title">
                            <a href="{{ route('pm.tasks.show', $t) }}" style="color:inherit;text-decoration:none;">{{ $t->title }}</a>
                        </p>
                        <div class="pm-task-card__meta">
                            <span style="display:inline-flex;align-items:center;gap:3px;">
                                <span style="width:7px;height:7px;border-radius:50%;background:{{ $priorityColor[$t->priority] ?? '#6b7280' }};display:inline-block;flex-shrink:0;"></span>
                                {{ ucfirst($t->priority) }}
                            </span>
                            @if($t->assignedTo)
                                <span style="display:inline-flex;align-items:center;gap:3px;">
                                    <span style="width:18px;height:18px;border-radius:50%;background:var(--primary);color:#fff;font-size:9px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;">
                                        {{ strtoupper(substr($t->assignedTo->name, 0, 1)) }}
                                    </span>
                                    {{ $t->assignedTo->name }}
                                </span>
                            @endif
                            @if($t->due_date)
                                <span style="{{ $overdue ? 'color:#dc2626;font-weight:700;' : '' }}">
                                    <i class="fa fa-calendar" style="font-size:10px;"></i>
                                    {{ $t->due_date->format('d M') }}
                                    @if($overdue) <span style="font-size:9px;">(overdue)</span>@endif
                                </span>
                            @endif
                        </div>
                        <div class="pm-task-card__actions">
                            <a href="{{ route('pm.tasks.show', $t) }}" class="pcat-link" style="font-size:11px;">
                                <i class="fa fa-eye"></i> View
                            </a>
                            {{-- Move status dropdown --}}
                            <div style="position:relative;display:inline-block;" class="pm-move-wrap">
                                <button type="button" class="linkbtn pm-move-btn"
                                        style="padding:3px 8px;font-size:11px;background:transparent;border:1px solid var(--border);color:var(--text);">
                                    Move <i class="fa fa-chevron-down" style="font-size:9px;"></i>
                                </button>
                                <div class="pm-move-menu" style="display:none;position:absolute;top:100%;left:0;z-index:50;background:var(--card);border:1px solid var(--border);border-radius:8px;min-width:130px;box-shadow:0 8px 24px rgba(0,0,0,.2);padding:4px 0;">
                                    @foreach($colMeta as $ns => $nm)
                                        @if($ns !== $status)
                                            <form method="POST" action="{{ route('pm.tasks.status', $t) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $ns }}">
                                                <button type="submit"
                                                        style="width:100%;text-align:left;padding:7px 12px;font-size:12px;background:none;border:none;cursor:pointer;color:var(--text);font-weight:500;">
                                                    <span style="width:8px;height:8px;border-radius:50%;background:{{ $nm['color'] }};display:inline-block;margin-right:4px;"></span>
                                                    {{ $nm['label'] }}
                                                </button>
                                            </form>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Add task at bottom of column --}}
                <div class="pm-board-add-form">
                    <button type="button" class="pm-board-add-toggle" data-col="{{ $status }}">
                        <i class="fa fa-plus" style="font-size:10px;"></i> Add task
                    </button>
                    <div class="pm-board-add-body" id="pm-add-{{ $status }}">
                        <form method="POST" action="{{ route('pm.projects.tasks.store', $project) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $status }}">
                            <input type="hidden" name="_from" value="board">
                            <div class="pcat-field" style="margin-bottom:6px;">
                                <input type="text" name="title" placeholder="Task title…" maxlength="200" required
                                       style="font-size:12px;padding:7px 9px;">
                            </div>
                            <div style="display:flex;gap:6px;">
                                <button type="submit" class="linkbtn" style="padding:5px 12px;font-size:12px;">
                                    <i class="fa fa-plus"></i> Add
                                </button>
                                <button type="button" class="linkbtn pm-board-add-cancel"
                                        style="padding:5px 10px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);"
                                        data-col="{{ $status }}">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div style="margin-top:14px;">
    <a href="{{ route('pm.projects.show', $project) }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Back to project
    </a>
</div>

<script>
(function () {
    // Move dropdowns
    document.querySelectorAll('.pm-move-wrap').forEach(function (wrap) {
        var btn  = wrap.querySelector('.pm-move-btn');
        var menu = wrap.querySelector('.pm-move-menu');
        if (!btn || !menu) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = menu.style.display !== 'none';
            document.querySelectorAll('.pm-move-menu').forEach(function (m) { m.style.display = 'none'; });
            menu.style.display = open ? 'none' : 'block';
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.pm-move-menu').forEach(function (m) { m.style.display = 'none'; });
    });

    // Add task toggles
    document.querySelectorAll('.pm-board-add-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var col = btn.getAttribute('data-col');
            var body = document.getElementById('pm-add-' + col);
            if (body) { body.classList.add('open'); btn.style.display = 'none'; }
        });
    });
    document.querySelectorAll('.pm-board-add-cancel').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var col = btn.getAttribute('data-col');
            var body = document.getElementById('pm-add-' + col);
            var toggle = document.querySelector('.pm-board-add-toggle[data-col="' + col + '"]');
            if (body) body.classList.remove('open');
            if (toggle) toggle.style.display = '';
        });
    });
})();
</script>
@endsection
