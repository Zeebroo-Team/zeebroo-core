@extends('theme::layouts.app', ['title' => 'My Projects', 'heading' => 'My Projects'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.mp-subnav{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
.mp-seg{display:inline-flex;border:1px solid var(--border);border-radius:9px;overflow:hidden;}
.mp-seg button{background:transparent;border:none;border-right:1px solid var(--border);padding:7px 14px;font-size:13px;font-weight:600;color:var(--text);cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
.mp-seg button:last-child{border-right:none;}
.mp-seg button.active{background:var(--primary);color:#fff;}
.mp-subnav__hint{font-size:12px;color:var(--muted);}
.mp-subnav__actions{margin-left:auto;display:flex;gap:8px;}
.mp-btn-ghost{background:transparent !important;border:1px solid var(--border) !important;color:var(--text) !important;}

.mp-hero{margin-bottom:14px;}
.mp-hero__title{font-size:19px;font-weight:700;color:var(--text);display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;}
.mp-hero__date{font-size:13px;font-weight:400;color:var(--muted);}
.mp-hero__sub{font-size:13px;color:var(--muted);margin-top:4px;}
.mp-hl-overdue{color:#dc2626;}
.mp-hl-today{color:#d97706;}

.mp-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:14px;}
.mp-stat{border:1px solid var(--border);border-radius:11px;padding:12px 14px;background:var(--card);display:flex;align-items:center;gap:12px;cursor:pointer;transition:border-color .15s;}
.mp-stat:hover{border-color:color-mix(in srgb,var(--mp-c) 50%,var(--border));}
.mp-stat__icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--mp-c) 13%,transparent);color:var(--mp-c);flex-shrink:0;}
.mp-stat strong{display:block;font-size:20px;color:var(--text);line-height:1.1;}
.mp-stat span{font-size:12px;color:var(--muted);}

.mp-lists{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;margin-bottom:18px;}
.mp-list{border:1px solid var(--border);border-radius:11px;background:var(--card);overflow:hidden;}
.mp-list__head{display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid var(--border);font-size:13px;font-weight:700;color:var(--text);}
.mp-list__count{margin-left:auto;font-size:12px;color:var(--muted);font-weight:600;}
.mp-list__body{max-height:300px;overflow:auto;}
.mp-empty{text-align:center;color:var(--muted);font-size:13px;padding:22px 12px;}
.mp-item{display:flex;align-items:flex-start;gap:10px;padding:9px 14px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);}
.mp-item:last-child{border-bottom:none;}
.mp-item__body{flex:1;min-width:0;}
.mp-item__title{font-size:13px;font-weight:600;color:var(--text);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mp-item__title:hover{color:var(--primary);}
.mp-meta{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:11px;color:var(--muted);margin-top:3px;}

.mp-check{background:none;border:none;padding:0;cursor:pointer;font-size:16px;color:color-mix(in srgb,var(--muted) 55%,transparent);line-height:1;margin-top:2px;}
.mp-check:hover{color:#16a34a;}
.mp-check--done{color:#16a34a;}

.mp-pri{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:1px 6px;border-radius:4px;background:color-mix(in srgb,var(--mp-c) 13%,transparent);color:var(--mp-c);}
.mp-status{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:2px 7px;border-radius:999px;white-space:nowrap;background:color-mix(in srgb,var(--mp-c) 14%,transparent);color:var(--mp-c);}
.mp-due--overdue{color:#dc2626;font-weight:700;}
.mp-due--today{color:#d97706;font-weight:700;}

.mp-proj-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;}
.mp-proj{border:1px solid var(--border);border-radius:11px;background:var(--card);padding:12px 14px;cursor:pointer;transition:border-color .15s,box-shadow .15s;}
.mp-proj:hover{border-color:color-mix(in srgb,var(--mp-c) 50%,var(--border));box-shadow:0 4px 14px rgba(0,0,0,.06);}
.mp-proj__head{display:flex;align-items:flex-start;gap:10px;}
.mp-proj__avatar{width:30px;height:30px;border-radius:8px;background:var(--mp-c);color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.mp-proj__name{font-size:14px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mp-proj__bar{height:5px;border-radius:999px;background:color-mix(in srgb,var(--border) 70%,transparent);margin:12px 0 10px;overflow:hidden;}
.mp-proj__bar > div{height:100%;background:var(--mp-c);border-radius:999px;}
.mp-proj__foot{display:flex;align-items:center;justify-content:space-between;font-size:12px;color:var(--muted);}
.mp-proj__open{font-weight:600;color:var(--text);}

.mp-filters{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:12px;}
.mp-chip{padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;}
.mp-chip.active{background:var(--primary);color:#fff;border-color:var(--primary);}
.mp-filters input,.mp-filters select{padding:6px 9px;font-size:12px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.mp-filters input{min-width:200px;}
.mp-table th[data-sort]{cursor:pointer;user-select:none;}
.mp-table th[data-sort]::after{content:'';margin-left:4px;font-size:9px;}
.mp-table th.mp-sort--asc::after{content:'▲';}
.mp-table th.mp-sort--desc::after{content:'▼';}
.mp-status-select{padding:4px 7px;font-size:12px;border-radius:6px;border:1px solid var(--border);background:var(--card);color:var(--text);}

.mp-board{display:flex;gap:14px;align-items:flex-start;overflow-x:auto;padding-bottom:8px;}
.mp-col{flex:0 0 260px;min-width:0;border:1px solid var(--border);border-top:3px solid var(--mp-c);border-radius:12px;background:color-mix(in srgb,var(--card) 96%,transparent);padding:12px;transition:opacity .15s;}
.mp-col__head{display:flex;align-items:center;gap:7px;margin-bottom:10px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--mp-c);}
.mp-col__sub{font-size:10px;font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mp-col__count{margin-left:auto;font-size:11px;padding:2px 6px;border-radius:999px;font-weight:700;background:color-mix(in srgb,var(--mp-c) 13%,transparent);}
.mp-col--dragover{border-style:dashed;border-color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,var(--card));}
.mp-col--blocked{opacity:.4;}
.mp-card{border:1px solid var(--border);border-radius:9px;background:var(--card);padding:10px 11px;margin-bottom:8px;cursor:grab;}
.mp-card:last-child{margin-bottom:0;}
.mp-card--dragging{opacity:.4;}
.mp-card__title{font-size:13px;font-weight:600;color:var(--text);text-decoration:none;display:block;margin-bottom:5px;line-height:1.3;}
.mp-card__move{margin-top:8px;padding:3px 6px;font-size:11px;border-radius:6px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.mp-dot{width:8px;height:8px;border-radius:50%;display:inline-block;background:var(--mp-c);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('projectmanage::partials.pm-hub-nav')

    <div class="mp-subnav">
        <div class="mp-seg" role="tablist">
            <button type="button" data-mp-view="overview"><i class="fa fa-house"></i> Overview</button>
            <button type="button" data-mp-view="tasks"><i class="fa fa-list-check"></i> My Tasks</button>
            <button type="button" data-mp-view="board"><i class="fa fa-table-columns"></i> Kanban Board</button>
        </div>
        <span class="mp-subnav__hint">Tasks assigned to you across every project</span>
        <div class="mp-subnav__actions">
            <button type="button" class="linkbtn mp-btn-ghost" id="mp-refresh" style="padding:6px 12px;font-size:12px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-rotate"></i> Refresh
            </button>
            <button type="button" class="linkbtn" id="mp-new-task" style="padding:6px 12px;font-size:12px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> New Task
            </button>
        </div>
    </div>

    {{-- ── Overview ─────────────────────────────────────────────────── --}}
    <section id="mp-view-overview" hidden>
        <div class="mp-hero" id="mp-hero"></div>
        <div class="mp-stats" id="mp-stats"></div>

        <div class="mp-lists">
            <div class="mp-list">
                <div class="mp-list__head"><i class="fa fa-triangle-exclamation" style="color:#dc2626;"></i> Overdue <span class="mp-list__count" id="mp-ov-overdue-count">0</span></div>
                <div class="mp-list__body" id="mp-ov-overdue"></div>
            </div>
            <div class="mp-list">
                <div class="mp-list__head"><i class="fa fa-calendar-day" style="color:#d97706;"></i> Due Today <span class="mp-list__count" id="mp-ov-today-count">0</span></div>
                <div class="mp-list__body" id="mp-ov-today"></div>
            </div>
            <div class="mp-list">
                <div class="mp-list__head"><i class="fa fa-calendar-week" style="color:#2563eb;"></i> Upcoming · next 7 days <span class="mp-list__count" id="mp-ov-upcoming-count">0</span></div>
                <div class="mp-list__body" id="mp-ov-upcoming"></div>
            </div>
        </div>

        <div class="pcat-toolbar">
            <h3 style="font-size:14px;color:var(--text);margin:0;"><i class="fa fa-diagram-project" style="color:var(--primary);"></i> My Projects</h3>
            <a href="#" class="pcat-link" data-mp-goto-view="board">Open board <i class="fa fa-arrow-right"></i></a>
        </div>
        <div class="mp-proj-grid" id="mp-ov-projects"></div>
    </section>

    {{-- ── My Tasks ─────────────────────────────────────────────────── --}}
    <section id="mp-view-tasks" hidden>
        <div class="mp-filters">
            <div id="mp-task-chips" style="display:flex;gap:6px;flex-wrap:wrap;">
                <button type="button" class="mp-chip" data-filter="open">Open</button>
                <button type="button" class="mp-chip" data-filter="today">Due Today</button>
                <button type="button" class="mp-chip" data-filter="overdue">Overdue</button>
                <button type="button" class="mp-chip" data-filter="done">Completed</button>
                <button type="button" class="mp-chip" data-filter="cancelled">Cancelled</button>
                <button type="button" class="mp-chip" data-filter="all">All</button>
            </div>
            <select id="mp-task-project"></select>
            <input type="search" id="mp-task-search" placeholder="Search tasks…">
        </div>
        <div class="pcat-table-wrap">
            <table class="pcat-table mp-table" id="mp-tasks-table">
                <thead>
                    <tr>
                        <th style="width:34px;"></th>
                        <th data-sort="title">Title</th>
                        <th data-sort="project_name">Project</th>
                        <th data-sort="priority">Priority</th>
                        <th data-sort="due_date">Due date</th>
                        <th data-sort="status">Stage</th>
                    </tr>
                </thead>
                <tbody id="mp-tasks-body"></tbody>
            </table>
        </div>
    </section>

    {{-- ── Kanban Board ─────────────────────────────────────────────── --}}
    <section id="mp-view-board" hidden>
        <div class="mp-filters">
            <select id="mp-board-project"></select>
            <select id="mp-board-priority">
                <option value="">All priorities</option>
                <option value="high">High</option>
                <option value="normal">Normal</option>
                <option value="low">Low</option>
            </select>
            <select id="mp-board-due">
                <option value="">Any due date</option>
                <option value="overdue">Overdue</option>
                <option value="today">Due today</option>
                <option value="week">Due this week</option>
                <option value="none">No due date</option>
            </select>
        </div>
        <div class="mp-board" id="mp-board"></div>
    </section>

    {{-- ── New task (assigned to me) ────────────────────────────────── --}}
    <div id="mp-task-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="mp-task-modal-title" aria-hidden="true">
        <div class="pcat-modal__backdrop" data-mp-close tabindex="-1"></div>
        <div class="pcat-modal__panel">
            <div class="pcat-modal__head">
                <h2 id="mp-task-modal-title">New task</h2>
                <button type="button" class="pcat-modal__close" data-mp-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <div class="pcat-banner pcat-banner--err" id="mp-tf-error" style="margin-bottom:10px;" hidden></div>
                <form id="mp-task-form">
                    <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:10px;">
                        <div class="pcat-field">
                            <label>Project *</label>
                            <select id="mp-tf-project" required></select>
                        </div>
                        <div class="pcat-field">
                            <label>Stage</label>
                            <select id="mp-tf-status"></select>
                        </div>
                    </div>
                    <div class="pcat-field" style="margin-bottom:10px;">
                        <label>Title *</label>
                        <input type="text" id="mp-tf-title" maxlength="200" required placeholder="What needs to be done?">
                    </div>
                    <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:10px;">
                        <div class="pcat-field">
                            <label>Priority</label>
                            <select id="mp-tf-priority">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="pcat-field">
                            <label>Due date</label>
                            <input type="date" id="mp-tf-due">
                        </div>
                    </div>
                    <div class="pcat-field" style="margin-bottom:10px;">
                        <label>Estimated hours</label>
                        <input type="number" id="mp-tf-hours" min="0" step="0.25" placeholder="Optional">
                    </div>
                    <div class="pcat-field" style="margin-bottom:14px;">
                        <label>Description</label>
                        <textarea id="mp-tf-desc" maxlength="5000" placeholder="Optional details…"></textarea>
                    </div>
                    <p class="pcat-muted" style="font-size:12px;margin:0 0 12px;">The task is assigned to you. You can only add tasks to projects whose team you are on.</p>
                    <div style="display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" class="linkbtn mp-btn-ghost" style="padding:8px 14px;font-size:13px;" data-mp-close>Cancel</button>
                        <button type="submit" class="linkbtn" id="mp-tf-save" style="padding:8px 18px;font-size:13px;"><i class="fa fa-plus"></i> Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var URLS = {
        data:       @js(route('pm.my-projects.data')),
        store:      @js(route('pm.my-projects.tasks.store')),
        status:     @js(route('pm.my-projects.tasks.status', ['task' => '__ID__'])),
        completion: @js(route('pm.my-projects.tasks.completion-status', ['task' => '__ID__'])),
    };
    var CSRF = @js(csrf_token());

    var BUILTIN_COLS = [
        { status: 'todo',        label: 'To Do',       sort_order: 1,  is_custom: false, color: null },
        { status: 'in_progress', label: 'In Progress', sort_order: 2,  is_custom: false, color: null },
        { status: 'review',      label: 'Review',      sort_order: 3,  is_custom: false, color: null },
        { status: 'done',        label: 'Done',        sort_order: 99, is_custom: false, color: null },
    ];
    var BUILTIN_COLORS  = { todo: '#6b7280', in_progress: '#2563eb', review: '#7c3aed', done: '#16a34a' };
    var PRIORITY_COLORS = { high: '#dc2626', normal: '#2563eb', low: '#6b7280' };
    var PROJECT_COLORS  = { active: '#16a34a', on_hold: '#f59e0b', completed: '#2563eb', archived: '#6b7280' };
    var PRIORITY_RANK   = { high: 0, normal: 1, low: 2 };

    var initial = @json($work);
    var mp = {
        view:          @js($view),
        tasks:         initial.tasks || [],
        projects:      initial.projects || [],
        taskFilter:    'open',
        taskProject:   '',
        taskSearch:    '',
        sortKey:       'due_date',
        sortDir:       1,
        boardProject:  '',
        boardPriority: '',
        boardDue:      '',
    };

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ── Dates (local YYYY-MM-DD — toISOString() is UTC and would shift "today") ──
    function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function today() { return ymd(new Date()); }
    function inDays(n) { var d = new Date(); d.setDate(d.getDate() + n); return ymd(d); }

    // Completion status (incomplete / complete / cancelled) is separate from the stage (t.status).
    var COMPLETION = { incomplete: 'Incomplete', complete: 'Complete', cancelled: 'Cancelled' };
    var COMPLETION_COLORS = { incomplete: '#6b7280', complete: '#16a34a', cancelled: '#dc2626' };
    function completionOf(t) { return t.completion_status || 'incomplete'; }
    function isComplete(t)  { return completionOf(t) === 'complete'; }
    function isCancelled(t) { return completionOf(t) === 'cancelled'; }
    function isDone(t)      { return completionOf(t) !== 'incomplete'; }   // closed: complete or cancelled
    function isOverdue(t)  { return !isDone(t) && !!t.due_date && t.due_date < today(); }
    function isDueToday(t) { return !isDone(t) && t.due_date === today(); }
    function isThisWeek(t) { return !isDone(t) && !!t.due_date && t.due_date > today() && t.due_date <= inDays(7); }
    function byDue(a, b)   { return (a.due_date || '9999').localeCompare(b.due_date || '9999'); }

    function project(id)     { return mp.projects.find(function (p) { return +p.id === +id; }); }
    function statusesFor(id) { var p = project(id); return (p && p.statuses) || BUILTIN_COLS; }
    function task(id)        { return mp.tasks.find(function (t) { return +t.id === +id; }); }

    function colColor(s) { return s.color || BUILTIN_COLORS[s.status] || '#0ea5e9'; }
    // Tasks whose stage was deleted from their project
    var UNDEFINED_COL = { status: 'undefined', label: 'Not Defined', sort_order: 0, is_custom: false, is_undefined: true, color: '#9ca3af' };
    function isOrphan(t) { return !statusesFor(t.project_id).some(function (s) { return s.status === t.status; }); }
    function statusMeta(t) {
        return statusesFor(t.project_id).find(function (s) { return s.status === t.status; }) || UNDEFINED_COL;
    }

    // ── HTML snippets ──
    function statusBadge(t) {
        var s = statusMeta(t);
        return '<span class="mp-status" style="--mp-c:' + esc(colColor(s)) + '" title="Stage">' + esc(s.label) + '</span>';
    }
    function completionBadge(t) {
        var cs = completionOf(t);
        return '<span class="mp-status" style="--mp-c:' + (COMPLETION_COLORS[cs] || '#6b7280') + '" title="Status">' + esc(COMPLETION[cs] || cs) + '</span>';
    }
    function priorityBadge(p) {
        return '<span class="mp-pri" style="--mp-c:' + (PRIORITY_COLORS[p] || '#6b7280') + '">' + esc(p) + '</span>';
    }
    function dueHtml(t) {
        if (!t.due_date) return '<span>—</span>';
        if (isOverdue(t))  return '<span class="mp-due--overdue"><i class="fa fa-triangle-exclamation"></i> ' + esc(t.due_date) + '</span>';
        if (isDueToday(t)) return '<span class="mp-due--today"><i class="fa fa-calendar-day"></i> Today</span>';
        return '<span><i class="fa fa-calendar"></i> ' + esc(t.due_date) + '</span>';
    }
    function checkBtn(t) {
        var done = isDone(t);
        var icon = isCancelled(t) ? 'fa-circle-xmark' : done ? 'fa-circle-check' : 'fa-circle';
        return '<button type="button" class="mp-check' + (done ? ' mp-check--done' : '') + '" data-mp-toggle="' + t.id + '" title="' + (done ? 'Reopen' : 'Mark as complete') + '"'
             + (isCancelled(t) ? ' style="color:#dc2626;"' : '') + '>'
             + '<i class="fa ' + icon + '"></i></button>';
    }

    // ── Server calls ──
    function request(method, url, body) {
        return fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (json) {
                if (!res.ok) {
                    var firstErr = json.errors && Object.values(json.errors)[0];
                    throw new Error((firstErr && firstErr[0]) || json.message || 'Request failed');
                }
                return json;
            });
        });
    }

    function reload() {
        return request('GET', URLS.data).then(function (json) {
            mp.tasks    = json.data.tasks || [];
            mp.projects = json.data.projects || [];
            fillProjectSelects();
        });
    }

    // Changes a task's stage and patches the local copy with the server's response.
    function setStatus(taskId, status) {
        return request('PATCH', URLS.status.replace('__ID__', taskId), { status: status }).then(function (json) {
            patchTask(taskId, json.data);
        });
    }

    // Changes a task's completion status (incomplete / complete / cancelled), stage untouched.
    function setCompletion(taskId, completion) {
        return request('PATCH', URLS.completion.replace('__ID__', taskId), { completion_status: completion }).then(function (json) {
            patchTask(taskId, json.data);
        });
    }

    function patchTask(taskId, data) {
        var i = mp.tasks.findIndex(function (t) { return +t.id === +taskId; });
        if (i !== -1 && data) mp.tasks[i] = data;
    }

    function notify(msg) { window.alert(msg); }

    function bindToggles(root) {
        $$('[data-mp-toggle]', root).forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var t = task(el.getAttribute('data-mp-toggle'));
                if (!t) return;
                el.disabled = true;
                setCompletion(t.id, isDone(t) ? 'incomplete' : 'complete')
                    .catch(function (err) { notify(err.message); })
                    .then(renderCurrent);
            });
        });
    }

    // ── View switching ──
    function switchView(view) {
        mp.view = view;
        $$('[data-mp-view]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-mp-view') === view); });
        ['overview', 'tasks', 'board'].forEach(function (v) { $('#mp-view-' + v).hidden = v !== view; });
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('view', view);
            window.history.replaceState(null, '', url);
        } catch (e) {}
        renderCurrent();
    }

    function renderCurrent() {
        if (mp.view === 'overview') renderOverview();
        else if (mp.view === 'tasks') renderTasks();
        else renderBoard();
    }

    function fillProjectSelects() {
        var opts = '<option value="">All projects</option>' + mp.projects.map(function (p) {
            return '<option value="' + p.id + '">' + esc(p.name) + '</option>';
        }).join('');
        [['#mp-task-project', 'taskProject'], ['#mp-board-project', 'boardProject']].forEach(function (pair) {
            var el = $(pair[0]);
            if (mp[pair[1]] && !project(mp[pair[1]])) mp[pair[1]] = '';   // project no longer visible
            el.innerHTML = opts;
            el.value = mp[pair[1]];
        });
    }

    // ── Overview ──
    function renderOverview() {
        var open     = mp.tasks.filter(function (t) { return !isDone(t); });
        var overdue  = open.filter(isOverdue).sort(byDue);
        var dueToday = open.filter(isDueToday);
        var upcoming = open.filter(isThisWeek).sort(byDue);

        var hr = new Date().getHours();
        var greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
        var dateStr = new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
        var parts = [];
        if (overdue.length)  parts.push('<b class="mp-hl-overdue">' + overdue.length + ' overdue</b>');
        if (dueToday.length) parts.push('<b class="mp-hl-today">' + dueToday.length + ' due today</b>');
        var plural = open.length === 1 ? '' : 's';
        var summary = !open.length
            ? 'You\'re all caught up — no open tasks. 🎉'
            : parts.length
                ? 'You have ' + parts.join(' and ') + ' out of ' + open.length + ' open task' + plural + '.'
                : 'You have ' + open.length + ' open task' + plural + ' and nothing urgent. Nice work!';
        $('#mp-hero').innerHTML =
            '<div class="mp-hero__title">' + greet + ' 👋 <span class="mp-hero__date">' + esc(dateStr) + '</span></div>' +
            '<div class="mp-hero__sub">' + summary + '</div>';

        var tiles = [
            { color: '#2563eb', icon: 'fa-list-check',           label: 'Open Tasks', value: open.length,                   filter: 'open' },
            { color: '#d97706', icon: 'fa-calendar-day',         label: 'Due Today',  value: dueToday.length,               filter: 'today' },
            { color: '#dc2626', icon: 'fa-triangle-exclamation', label: 'Overdue',    value: overdue.length,                filter: 'overdue' },
            { color: '#16a34a', icon: 'fa-circle-check',         label: 'Completed',  value: mp.tasks.filter(isComplete).length, filter: 'done' },
        ];
        var stats = $('#mp-stats');
        stats.innerHTML = tiles.map(function (s) {
            return '<div class="mp-stat" style="--mp-c:' + s.color + '" data-goto-filter="' + s.filter + '" title="View ' + esc(s.label.toLowerCase()) + '">'
                 + '<div class="mp-stat__icon"><i class="fa ' + s.icon + '"></i></div>'
                 + '<div><strong>' + s.value + '</strong><span>' + s.label + '</span></div></div>';
        }).join('');
        $$('[data-goto-filter]', stats).forEach(function (el) {
            el.addEventListener('click', function () {
                setTaskFilter(el.getAttribute('data-goto-filter'));
                switchView('tasks');
            });
        });

        fillList('overdue',  overdue,  'Nothing overdue.');
        fillList('today',    dueToday, 'Nothing due today.');
        fillList('upcoming', upcoming, 'Nothing due in the next 7 days.');
        renderOverviewProjects();
    }

    function fillList(key, list, emptyMsg) {
        $('#mp-ov-' + key + '-count').textContent = list.length;
        var body = $('#mp-ov-' + key);
        body.innerHTML = list.length ? list.map(function (t) {
            return '<div class="mp-item">' + checkBtn(t)
                 + '<div class="mp-item__body">'
                 +   '<a class="mp-item__title" href="' + esc(t.url) + '">' + esc(t.title) + '</a>'
                 +   '<div class="mp-meta">' + priorityBadge(t.priority)
                 +     '<span><i class="fa fa-diagram-project"></i> ' + esc(t.project_name) + '</span>'
                 +     (t.due_date ? dueHtml(t) : '')
                 +   '</div>'
                 + '</div>' + statusBadge(t) + '</div>';
        }).join('') : '<div class="mp-empty">' + emptyMsg + '</div>';
        bindToggles(body);
    }

    function renderOverviewProjects() {
        var grid = $('#mp-ov-projects');
        if (!mp.projects.length) {
            grid.innerHTML = '<div class="mp-empty" style="grid-column:1/-1;border:1px dashed var(--border);border-radius:11px;">'
                + 'You are not on any project yet. Projects appear here once a manager adds you to a team or assigns you a task.</div>';
            return;
        }
        grid.innerHTML = mp.projects.map(function (p) {
            var mine  = mp.tasks.filter(function (t) { return +t.project_id === +p.id; });
            var done  = mine.filter(isComplete).length;
            var counted = mine.length - mine.filter(isCancelled).length;   // cancelled tasks don't count
            var pct   = counted ? Math.round(done / counted * 100) : 0;
            var color = p.color || 'var(--primary)';
            return '<div class="mp-proj" style="--mp-c:' + esc(color) + '" data-board-project="' + p.id + '" title="Open this project on the kanban board">'
                 + '<div class="mp-proj__head">'
                 +   '<div class="mp-proj__avatar">' + esc((p.name || '?').trim().charAt(0).toUpperCase()) + '</div>'
                 +   '<div style="flex:1;min-width:0;">'
                 +     '<div class="mp-proj__name" title="' + esc(p.name) + '">' + esc(p.name) + '</div>'
                 +     '<div class="mp-meta">' + priorityBadge(p.priority)
                 +       (p.due_date ? '<span><i class="fa fa-calendar"></i> ' + esc(p.due_date) + '</span>' : '')
                 +     '</div>'
                 +   '</div>'
                 +   '<span class="mp-status" style="--mp-c:' + (PROJECT_COLORS[p.status] || '#6b7280') + '">' + esc(p.status.replace(/_/g, ' ')) + '</span>'
                 + '</div>'
                 + '<div class="mp-proj__bar" title="' + pct + '% of my tasks done"><div style="width:' + pct + '%"></div></div>'
                 + '<div class="mp-proj__foot">'
                 +   '<span><i class="fa fa-user-check"></i> ' + mine.length + ' my task' + (mine.length === 1 ? '' : 's') + ' · ' + done + ' done</span>'
                 +   '<span class="mp-proj__open">Open <i class="fa fa-arrow-right"></i></span>'
                 + '</div></div>';
        }).join('');
        $$('[data-board-project]', grid).forEach(function (card) {
            card.addEventListener('click', function () {
                mp.boardProject = card.getAttribute('data-board-project');
                $('#mp-board-project').value = mp.boardProject;
                switchView('board');
            });
        });
    }

    // ── My Tasks ──
    function setTaskFilter(filter) {
        mp.taskFilter = filter;
        $$('#mp-task-chips .mp-chip').forEach(function (c) { c.classList.toggle('active', c.getAttribute('data-filter') === filter); });
    }

    function sortValue(t, key) {
        if (key === 'priority') return PRIORITY_RANK[t.priority] != null ? PRIORITY_RANK[t.priority] : 9;
        if (key === 'due_date') return t.due_date || '9999-99-99';   // no date sorts last
        if (key === 'status')   return statusMeta(t).sort_order != null ? statusMeta(t).sort_order : 50;
        return String(t[key] || '').toLowerCase();
    }

    function renderTasks() {
        var q = mp.taskSearch.trim().toLowerCase();
        var filterFn = {
            all: function () { return true; },
            open: function (t) { return !isDone(t); },
            today: isDueToday, overdue: isOverdue, done: isComplete, cancelled: isCancelled,
        }[mp.taskFilter] || function () { return true; };

        var list = mp.tasks.filter(function (t) {
            if (!filterFn(t)) return false;
            if (mp.taskProject && +t.project_id !== +mp.taskProject) return false;
            if (q && (t.title + ' ' + (t.project_name || '') + ' ' + (t.milestone_name || '')).toLowerCase().indexOf(q) === -1) return false;
            return true;
        }).sort(function (a, b) {
            var va = sortValue(a, mp.sortKey), vb = sortValue(b, mp.sortKey);
            return ((va < vb ? -1 : va > vb ? 1 : 0) * mp.sortDir) || byDue(a, b);
        });

        $$('#mp-tasks-table th[data-sort]').forEach(function (th) {
            var key = th.getAttribute('data-sort');
            th.classList.toggle('mp-sort--asc',  key === mp.sortKey && mp.sortDir === 1);
            th.classList.toggle('mp-sort--desc', key === mp.sortKey && mp.sortDir === -1);
        });

        var tbody = $('#mp-tasks-body');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="mp-empty">' + (mp.tasks.length ? 'No tasks match your filters.' : 'No tasks are assigned to you yet.') + '</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(function (t) {
            var done = isDone(t);
            var opts = (isOrphan(t) ? '<option value="" selected disabled>Not Defined</option>' : '')
                + statusesFor(t.project_id).map(function (s) {
                    return '<option value="' + esc(s.status) + '"' + (s.status === t.status ? ' selected' : '') + '>' + esc(s.label) + '</option>';
                }).join('');
            return '<tr>'
                 + '<td>' + checkBtn(t) + '</td>'
                 + '<td><a href="' + esc(t.url) + '" style="font-weight:600;text-decoration:none;color:' + (done ? 'var(--muted)' : 'var(--text)') + ';' + (done ? 'text-decoration:line-through;' : '') + '">' + esc(t.title) + '</a>'
                 +   (t.milestone_name ? '<div style="font-size:11px;color:var(--muted);"><i class="fa fa-flag"></i> ' + esc(t.milestone_name) + '</div>' : '')
                 + '</td>'
                 + '<td style="font-size:12px;color:var(--muted);">' + esc(t.project_name) + '</td>'
                 + '<td>' + priorityBadge(t.priority) + '</td>'
                 + '<td style="font-size:12px;">' + dueHtml(t) + '</td>'
                 + '<td style="white-space:nowrap;"><select class="mp-status-select" data-mp-status="' + t.id + '" title="Stage">' + opts + '</select> ' + completionBadge(t) + '</td>'
                 + '</tr>';
        }).join('');

        bindToggles(tbody);
        $$('[data-mp-status]', tbody).forEach(function (sel) {
            sel.addEventListener('change', function () {
                sel.disabled = true;
                setStatus(sel.getAttribute('data-mp-status'), sel.value)
                    .catch(function (err) { notify(err.message); })
                    .then(renderTasks);
            });
        });
    }

    // ── Kanban board ──
    // One project selected → exactly its columns. All projects → the built-in columns plus
    // every custom status (merged by key) of the projects that hold my tasks. A card can
    // only be dropped on a column that exists in its own project.
    // Built-in stages appear only when one of these projects still has them; a built-in that the
    // projects renamed / recoloured differently falls back to its default name and colour.
    function boardColumns() {
        if (mp.boardProject) return statusesFor(mp.boardProject);
        var withTasks = new Set(mp.tasks.map(function (t) { return +t.project_id; }));
        var projects  = mp.projects.filter(function (p) { return withTasks.has(+p.id); });
        if (!projects.length) return BUILTIN_COLS;
        var byKey = new Map();
        projects.forEach(function (p) {
            statusesFor(p.id).forEach(function (s) {
                var col = byKey.get(s.status);
                if (!col) {
                    byKey.set(s.status, Object.assign({}, s, { projectNames: s.is_custom ? [p.name] : null }));
                    return;
                }
                if (col.projectNames) col.projectNames.push(p.name);
                var builtin = BUILTIN_COLS.find(function (b) { return b.status === s.status; });
                if (builtin && (col.label !== s.label || col.color !== s.color)) {
                    col.label = builtin.label;
                    col.color = null;
                }
            });
        });
        return Array.from(byKey.values()).sort(function (a, b) {
            return (a.sort_order - b.sort_order) || ((a.is_custom ? 1 : 0) - (b.is_custom ? 1 : 0));
        });
    }

    function boardTasks() {
        return mp.tasks.filter(function (t) {
            if (mp.boardProject && +t.project_id !== +mp.boardProject) return false;
            if (mp.boardPriority && t.priority !== mp.boardPriority) return false;
            if (mp.boardDue === 'overdue' && !isOverdue(t)) return false;
            if (mp.boardDue === 'today' && !isDueToday(t)) return false;
            if (mp.boardDue === 'week' && !(isDueToday(t) || isThisWeek(t))) return false;
            if (mp.boardDue === 'none' && t.due_date) return false;
            return true;
        });
    }

    var dragTask = null;

    function renderBoard() {
        var wrap  = $('#mp-board');
        var tasks = boardTasks();
        wrap.innerHTML = '';
        // "Not Defined" (tasks whose stage was deleted) leads the board while it holds tasks.
        var orphans = tasks.filter(isOrphan);
        var cols    = boardColumns();
        (orphans.length ? [UNDEFINED_COL].concat(cols) : cols).forEach(function (col) {
            var colTasks = col.is_undefined ? orphans : tasks.filter(function (t) { return t.status === col.status && !isOrphan(t); });
            var colEl = document.createElement('div');
            colEl.className = 'mp-col';
            colEl.style.setProperty('--mp-c', colColor(col));
            colEl.setAttribute('data-col', col.status);
            colEl.innerHTML =
                '<div class="mp-col__head"' + (col.projectNames ? ' title="Only tasks of: ' + esc(col.projectNames.join(', ')) + '"' : '') + '>'
                + '<span class="mp-dot"></span>' + esc(col.label)
                + (col.projectNames ? '<span class="mp-col__sub">' + esc(col.projectNames.join(', ')) + '</span>' : '')
                + '<span class="mp-col__count">' + colTasks.length + '</span></div>'
                + '<div class="mp-col__cards">' + (colTasks.length ? '' : '<div class="mp-empty" style="padding:8px 2px;font-size:12px;">No tasks</div>') + '</div>';
            var cards = $('.mp-col__cards', colEl);
            colTasks.forEach(function (t) { cards.appendChild(boardCard(t)); });
            if (!col.is_undefined) bindDrop(colEl, col);   // not a drop target
            wrap.appendChild(colEl);
        });
    }

    function boardCard(t) {
        var card = document.createElement('div');
        card.className = 'mp-card';
        card.draggable = true;
        var p = project(t.project_id);
        var moveOpts = statusesFor(t.project_id).filter(function (s) { return s.status !== t.status; }).map(function (s) {
            return '<option value="' + esc(s.status) + '">' + esc(s.label) + '</option>';
        }).join('');
        card.innerHTML =
            '<a class="mp-card__title" href="' + esc(t.url) + '" draggable="false">' + esc(t.title) + '</a>'
            + '<div class="mp-meta">' + completionBadge(t) + priorityBadge(t.priority) + (t.due_date ? dueHtml(t) : '') + '</div>'
            + '<div class="mp-meta">'
            +   (!mp.boardProject ? '<span><span class="mp-dot" style="--mp-c:' + esc((p && p.color) || 'var(--muted)') + '"></span> ' + esc(t.project_name) + '</span>' : '')
            +   (t.milestone_name ? '<span><i class="fa fa-flag"></i> ' + esc(t.milestone_name) + '</span>' : '')
            + '</div>'
            + '<select class="mp-card__move" title="Move to…"><option value="">Move…</option>' + moveOpts + '</select>';

        card.addEventListener('dragstart', function (e) {
            if (e.target.closest && e.target.closest('select')) { e.preventDefault(); return; }
            dragTask = t;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(t.id));
            // Grey out the columns this task's project doesn't have
            var allowed = new Set(statusesFor(t.project_id).map(function (s) { return s.status; }));
            $$('#mp-board .mp-col').forEach(function (c) { c.classList.toggle('mp-col--blocked', !allowed.has(c.getAttribute('data-col'))); });
            requestAnimationFrame(function () { card.classList.add('mp-card--dragging'); });
        });
        card.addEventListener('dragend', function () {
            dragTask = null;
            card.classList.remove('mp-card--dragging');
            $$('#mp-board .mp-col').forEach(function (c) { c.classList.remove('mp-col--blocked', 'mp-col--dragover'); });
        });
        $('select', card).addEventListener('change', function () {
            if (this.value) moveTask(t, this.value);
        });
        return card;
    }

    function bindDrop(colEl, col) {
        colEl.addEventListener('dragover', function (e) {
            if (!dragTask || colEl.classList.contains('mp-col--blocked')) return;   // no preventDefault → "not allowed" cursor
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            colEl.classList.add('mp-col--dragover');
        });
        colEl.addEventListener('dragleave', function (e) {
            if (!colEl.contains(e.relatedTarget)) colEl.classList.remove('mp-col--dragover');
        });
        colEl.addEventListener('drop', function (e) {
            e.preventDefault();
            colEl.classList.remove('mp-col--dragover');
            var t = dragTask;
            dragTask = null;
            if (t && t.status !== col.status) moveTask(t, col.status);
        });
    }

    // Optimistic move: update locally and re-render, then roll back if the server refuses.
    function moveTask(t, toStatus) {
        if (!statusesFor(t.project_id).some(function (s) { return s.status === toStatus; })) {
            notify('"' + t.project_name + '" has no such stage column.');
            return;
        }
        var fromStatus = t.status;
        t.status = toStatus;
        renderBoard();
        setStatus(t.id, toStatus).catch(function (err) {
            t.status = fromStatus;
            notify('Could not move task: ' + err.message);
        }).then(renderBoard);
    }

    // ── New task (assigned to me) ──
    var modal = $('#mp-task-modal');

    function memberProjects() { return mp.projects.filter(function (p) { return p.is_member; }); }

    function fillStatusSelect(projectId) {
        $('#mp-tf-status').innerHTML = statusesFor(projectId).map(function (s) {
            return '<option value="' + esc(s.status) + '">' + esc(s.label) + '</option>';
        }).join('');
    }

    function showFormError(msg) {
        var el = $('#mp-tf-error');
        el.textContent = msg || '';
        el.hidden = !msg;
    }

    function openModal() {
        var projects = memberProjects();
        if (!projects.length) { notify('You are not on any project team yet — ask a manager to add you.'); return; }

        var sel = $('#mp-tf-project');
        sel.innerHTML = projects.map(function (p) { return '<option value="' + p.id + '">' + esc(p.name) + '</option>'; }).join('');
        // Preselect the project currently filtered on, when I can add tasks there
        var preset = projects.find(function (p) { return +p.id === +(mp.view === 'board' ? mp.boardProject : mp.taskProject); }) || projects[0];
        sel.value = String(preset.id);
        fillStatusSelect(preset.id);

        $('#mp-task-form').reset();
        sel.value = String(preset.id);
        showFormError('');

        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('pcat-modal-open-html');
        setTimeout(function () { $('#mp-tf-title').focus(); }, 80);
    }

    function closeModal() {
        modal.classList.remove('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('pcat-modal-open-html');
    }

    $('#mp-task-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var hours = $('#mp-tf-hours').value;
        var body = {
            project_id:      +$('#mp-tf-project').value,
            title:           $('#mp-tf-title').value.trim(),
            description:     $('#mp-tf-desc').value.trim() || null,
            status:          $('#mp-tf-status').value || null,
            priority:        $('#mp-tf-priority').value,
            due_date:        $('#mp-tf-due').value || null,
            estimated_hours: hours !== '' ? +hours : null,
        };
        if (!body.project_id) { showFormError('Choose a project.'); return; }
        if (!body.title)      { showFormError('Task title is required.'); return; }

        var save = $('#mp-tf-save');
        save.disabled = true;
        request('POST', URLS.store, body).then(function (json) {
            mp.tasks.push(json.data);
            closeModal();
            renderCurrent();
        }).catch(function (err) {
            showFormError(err.message);
        }).then(function () { save.disabled = false; });
    });

    $('#mp-tf-project').addEventListener('change', function () { fillStatusSelect(this.value); });
    $$('[data-mp-close]').forEach(function (el) { el.addEventListener('click', closeModal); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('pcat-modal--open')) closeModal();
    });

    // ── Wiring ──
    $$('[data-mp-view]').forEach(function (b) {
        b.addEventListener('click', function () { switchView(b.getAttribute('data-mp-view')); });
    });
    $$('[data-mp-goto-view]').forEach(function (a) {
        a.addEventListener('click', function (e) { e.preventDefault(); switchView(a.getAttribute('data-mp-goto-view')); });
    });
    $('#mp-new-task').addEventListener('click', openModal);
    $('#mp-refresh').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        reload().catch(function (err) { notify('Failed to load your projects: ' + err.message); })
            .then(function () { btn.disabled = false; renderCurrent(); });
    });

    $$('#mp-task-chips .mp-chip').forEach(function (chip) {
        chip.addEventListener('click', function () { setTaskFilter(chip.getAttribute('data-filter')); renderTasks(); });
    });
    $('#mp-task-search').addEventListener('input', function () { mp.taskSearch = this.value; renderTasks(); });
    $('#mp-task-project').addEventListener('change', function () { mp.taskProject = this.value; renderTasks(); });
    $$('#mp-tasks-table th[data-sort]').forEach(function (th) {
        th.addEventListener('click', function () {
            var key = th.getAttribute('data-sort');
            if (mp.sortKey === key) mp.sortDir *= -1;
            else { mp.sortKey = key; mp.sortDir = 1; }
            renderTasks();
        });
    });

    $('#mp-board-project').addEventListener('change', function () { mp.boardProject = this.value; renderBoard(); });
    $('#mp-board-priority').addEventListener('change', function () { mp.boardPriority = this.value; renderBoard(); });
    $('#mp-board-due').addEventListener('change', function () { mp.boardDue = this.value; renderBoard(); });

    fillProjectSelects();
    setTaskFilter(mp.taskFilter);
    switchView(mp.view);
})();
</script>
@endsection
