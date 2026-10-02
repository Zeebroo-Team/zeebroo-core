@extends('theme::layouts.app', ['title' => 'Board — ' . $project->name, 'heading' => 'Board'])

@php
    $builtinColors = ['todo' => '#6b7280', 'in_progress' => '#2563eb', 'review' => '#7c3aed', 'done' => '#16a34a'];
    $colMeta = collect($columns)->mapWithKeys(fn ($c) => [$c['status'] => [
        'id'         => $c['id'],
        'label'      => $c['label'],
        'color'      => $c['color'] ?: ($builtinColors[$c['status']] ?? '#0ea5e9'),
        'sort_order' => $c['sort_order'],
        'is_custom'  => $c['is_custom'],
        'tasks'      => $c['tasks'],
    ]])->all();
    $sortMax = \Modules\ProjectManage\Models\Task::CUSTOM_SORT_MAX;
    $sortHint = 'To Do = 1, In Progress = 2, Review = 3, Done is always last.';
    $priorityColor = ['high'=>'#dc2626','normal'=>'#f59e0b','low'=>'#6b7280'];
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pm-board{display:flex;gap:14px;align-items:flex-start;overflow-x:auto;padding-bottom:8px;}
.pm-col{flex:0 0 260px;min-width:0;border:1px solid var(--border);border-radius:12px;background:color-mix(in srgb,var(--card) 96%,transparent);padding:12px;}
.pm-col__head{display:flex;align-items:center;gap:7px;margin-bottom:10px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;}
.pm-col__head-actions{margin-left:auto;display:flex;align-items:center;gap:2px;}
.pm-col__icon{background:none;border:none;cursor:pointer;color:var(--muted);padding:2px 4px;font-size:11px;}
.pm-col__icon:hover{color:var(--text);}
.pm-col__icon--danger:hover{color:#dc2626;}
.pm-col__sort{font-size:10px;font-weight:600;color:var(--muted);letter-spacing:0;text-transform:none;}
.pm-status-form{display:none;border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:10px;background:var(--card);}
.pm-status-form.open{display:block;}
.pm-status-form *,.pm-status-form *::before,.pm-status-form *::after{box-sizing:border-box;}
.pm-status-form__row{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;}
.pm-status-form__row > div{min-width:0;}
.pm-status-form label{display:block;font-size:11px;font-weight:600;color:var(--muted);margin:0 0 3px;line-height:1.3;white-space:nowrap;}
.pm-status-form input[type=text],.pm-status-form input[type=number]{font-size:12px;height:34px;margin:0;padding:0 8px;border:1px solid var(--border);border-radius:7px;background:var(--card);color:var(--text);width:100%;max-width:100%;}
.pm-status-form input[type=color]{display:block;width:42px;height:34px;margin:0;padding:2px;border:1px solid var(--border);border-radius:7px;background:var(--card);cursor:pointer;}
.pm-status-form__row > .linkbtn{height:34px;margin:0;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;}
.pm-status-form__hint{font-size:11px;color:var(--muted);margin-top:6px;}
.pm-board-toolbar{display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:12px;}
.pm-board-toolbar .pm-status-form{margin-bottom:0;flex:1 1 100%;}
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
.pm-task-card[draggable="true"]{cursor:grab;}
.pm-task-card[draggable="true"]:active{cursor:grabbing;}
.pm-task-card--dragging{opacity:.4;}
.pm-col--dragover{border-style:dashed;border-color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,var(--card));}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('projectmanage::partials.pm-hub-nav')

    <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">
        <a href="{{ route('pm.projects.show', $project) }}" class="pcat-link">{{ $project->name }}</a>
        <span style="margin:0 4px;">/</span>
        <span>Board</span>
    </div>

    @include('projectmanage::partials.pm-detail-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner" style="font-weight:600;color:#dc2626;">{{ $errors->first() }}</div>
    @endif

    <div class="pm-board-toolbar">
        <button type="button" class="linkbtn" data-toggle-form="pm-status-add"
                style="padding:6px 12px;font-size:12px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-table-columns"></i> Add Status
        </button>
        <form method="POST" action="{{ route('pm.projects.statuses.store', $project) }}" class="pm-status-form" id="pm-status-add">
            @csrf
            <div class="pm-status-form__row">
                <div style="flex:1 1 200px;"><label>Status name *</label><input type="text" name="label" maxlength="60" required placeholder="e.g. Testing, Blocked"></div>
                <div><label>Color</label><input type="color" name="color" value="#0ea5e9"></div>
                <div style="flex:0 0 110px;"><label>Sort number</label><input type="number" name="sort_order" min="0" max="{{ $sortMax }}" placeholder="Auto"></div>
                <button type="submit" class="linkbtn" style="padding:6px 12px;font-size:12px;"><i class="fa fa-plus"></i> Add</button>
                <button type="button" class="linkbtn" data-toggle-form="pm-status-add"
                        style="padding:6px 10px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);">Cancel</button>
            </div>
            <div class="pm-status-form__hint">{{ $sortHint }} Leave blank to add after the last status.</div>
        </form>
    </div>

    <div class="pm-board">
        @foreach($colMeta as $status => $meta)
            @php $colTasks = $meta['tasks']; @endphp
            <div class="pm-col" data-col="{{ $status }}" style="border-top:3px solid {{ $meta['color'] }};">
                <div class="pm-col__head" style="color:{{ $meta['color'] }};">
                    {{ $meta['label'] }}
                    <span class="pm-col__count" style="background:{{ $meta['color'] }}20;color:{{ $meta['color'] }};">
                        {{ $colTasks->count() }}
                    </span>
                    <span class="pm-col__head-actions">
                        @if($status !== \Modules\ProjectManage\Models\Task::STATUS_DONE)
                            <span class="pm-col__sort" title="Sort number">#{{ $meta['sort_order'] }}</span>
                        @endif
                        @if($meta['is_custom'])
                            <button type="button" class="pm-col__icon" title="Edit status" data-toggle-form="pm-status-edit-{{ $meta['id'] }}"><i class="fa fa-pen"></i></button>
                            <form method="POST" action="{{ route('pm.statuses.destroy', $meta['id']) }}" style="display:inline;"
                                  onsubmit="return confirm(@js($colTasks->count() ? "Delete status \"{$meta['label']}\"? Its {$colTasks->count()} task(s) will be moved to To Do." : "Delete status \"{$meta['label']}\"?"));">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="pm-col__icon pm-col__icon--danger" title="Delete status"><i class="fa fa-trash"></i></button>
                            </form>
                        @endif
                    </span>
                </div>

                @if($meta['is_custom'])
                    <form method="POST" action="{{ route('pm.statuses.update', $meta['id']) }}" class="pm-status-form" id="pm-status-edit-{{ $meta['id'] }}">
                        @csrf
                        @method('PUT')
                        <div style="margin-bottom:6px;"><label>Status name</label><input type="text" name="label" maxlength="60" required value="{{ $meta['label'] }}"></div>
                        <div class="pm-status-form__row">
                            <div><label>Color</label><input type="color" name="color" value="{{ $meta['color'] }}"></div>
                            <div style="flex:1;"><label>Sort number</label><input type="number" name="sort_order" min="0" max="{{ $sortMax }}" value="{{ $meta['sort_order'] }}" required></div>
                        </div>
                        <div class="pm-status-form__hint">{{ $sortHint }}</div>
                        <div style="display:flex;gap:6px;margin-top:8px;">
                            <button type="submit" class="linkbtn" style="padding:5px 12px;font-size:12px;">Save</button>
                            <button type="button" class="linkbtn" data-toggle-form="pm-status-edit-{{ $meta['id'] }}"
                                    style="padding:5px 10px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);">Cancel</button>
                        </div>
                    </form>
                @endif

                @foreach($colTasks as $t)
                    @php $overdue = $t->isOverdue(); @endphp
                    <div class="pm-task-card" draggable="true" data-status="{{ $status }}"
                         data-status-url="{{ route('pm.tasks.status', $t) }}">
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
                                            {{-- Rendered for every status; the card's own status is hidden so a drag-and-drop move can just toggle visibility --}}
                                            <form method="POST" action="{{ route('pm.tasks.status', $t) }}" data-move-status="{{ $ns }}" @if($ns === $status) style="display:none;" @endif>
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $ns }}">
                                                <button type="submit"
                                                        style="width:100%;text-align:left;padding:7px 12px;font-size:12px;background:none;border:none;cursor:pointer;color:var(--text);font-weight:500;">
                                                    <span style="width:8px;height:8px;border-radius:50%;background:{{ $nm['color'] }};display:inline-block;margin-right:4px;"></span>
                                                    {{ $nm['label'] }}
                                                </button>
                                            </form>
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
            if (open) return;
            // Fixed positioning so the horizontally-scrolling board doesn't clip the menu.
            var r = btn.getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.left = r.left + 'px';
            menu.style.top  = (r.bottom + 2) + 'px';
            menu.style.display = 'block';
        });
    });
    function closeMoveMenus() {
        document.querySelectorAll('.pm-move-menu').forEach(function (m) { m.style.display = 'none'; });
    }
    document.addEventListener('click', closeMoveMenus);
    window.addEventListener('scroll', closeMoveMenus, true);

    // Drag-and-drop between columns — moves the card immediately, then saves the new
    // status; reverts the card if the server rejects the change.
    var csrf = @js(csrf_token());
    var dragCard = null;

    function adjustCount(col, delta) {
        var el = col && col.querySelector('.pm-col__count');
        if (el) el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
    }
    function placeCard(card, col, status) {
        col.insertBefore(card, col.querySelector('.pm-board-add-form'));
        card.setAttribute('data-status', status);
        card.querySelectorAll('[data-move-status]').forEach(function (f) {
            f.style.display = f.getAttribute('data-move-status') === status ? 'none' : '';
        });
    }

    document.querySelectorAll('.pm-task-card[draggable="true"]').forEach(function (card) {
        // Links are natively draggable; disable that so grabbing the title drags the card
        card.querySelectorAll('a').forEach(function (a) { a.setAttribute('draggable', 'false'); });
        card.addEventListener('dragstart', function (e) {
            dragCard = card;
            closeMoveMenus();
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.getAttribute('data-status-url'));
            requestAnimationFrame(function () { card.classList.add('pm-task-card--dragging'); });
        });
        card.addEventListener('dragend', function () {
            dragCard = null;
            card.classList.remove('pm-task-card--dragging');
            document.querySelectorAll('.pm-col--dragover').forEach(function (c) { c.classList.remove('pm-col--dragover'); });
        });
    });

    document.querySelectorAll('.pm-col[data-col]').forEach(function (col) {
        col.addEventListener('dragover', function (e) {
            if (!dragCard) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            col.classList.add('pm-col--dragover');
        });
        col.addEventListener('dragleave', function (e) {
            if (!col.contains(e.relatedTarget)) col.classList.remove('pm-col--dragover');
        });
        col.addEventListener('drop', function (e) {
            e.preventDefault();
            col.classList.remove('pm-col--dragover');
            var card = dragCard;
            dragCard = null;
            if (!card) return;
            var toStatus   = col.getAttribute('data-col');
            var fromStatus = card.getAttribute('data-status');
            if (!toStatus || toStatus === fromStatus) return;
            var fromCol = card.closest('.pm-col');

            placeCard(card, col, toStatus);
            adjustCount(fromCol, -1);
            adjustCount(col, 1);

            fetch(card.getAttribute('data-status-url'), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({ status: toStatus })
            }).then(function (res) {
                if (res.ok) return;
                return res.json().catch(function () { return {}; }).then(function (body) {
                    throw new Error(body.message || 'Move failed');
                });
            }).catch(function (err) {
                placeCard(card, fromCol, fromStatus);
                adjustCount(col, -1);
                adjustCount(fromCol, 1);
                alert('Could not move task: ' + err.message);
            });
        });
    });

    // Add / edit status forms
    document.querySelectorAll('[data-toggle-form]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = document.getElementById(btn.getAttribute('data-toggle-form'));
            if (!form) return;
            form.classList.toggle('open');
            var first = form.querySelector('input[type=text]');
            if (form.classList.contains('open') && first) first.focus();
        });
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
