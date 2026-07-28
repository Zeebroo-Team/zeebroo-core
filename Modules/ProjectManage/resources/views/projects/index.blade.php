@extends('theme::layouts.app', ['title' => 'Projects', 'heading' => 'Projects'])

@php
    $hasProjects = $hasProjects ?? false;
    $modalOpen   = $hasProjects && $errors->any() && request()->old();

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

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('projectmanage::partials.pm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    @if($errors->any() && !$hasProjects)
        <div class="pcat-banner pcat-banner--err">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    <div class="pcat-toolbar">
        <span style="font-size:14px;font-weight:700;color:var(--text);">All Projects</span>
        @if($hasProjects)
            <button type="button" id="pm-pj-modal-open" class="linkbtn"
                    style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> New project
            </button>
        @endif
    </div>

    @if(!$hasProjects)
        <section class="pcat-inline">
            <h2>Create your first project</h2>
            <p class="pcat-muted">Organise work into projects, then break them down into milestones and tasks.</p>
            <form method="POST" action="{{ route('pm.projects.store') }}" style="margin-top:14px;">
                @csrf
                <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:12px;">
                    <div class="pcat-field">
                        <label for="pm-name">Project name</label>
                        <input type="text" id="pm-name" name="name" value="{{ old('name') }}"
                               placeholder="e.g. Website Redesign" maxlength="150" required>
                        @error('name')<p style="color:#f87171;font-size:11px;margin:4px 0 0;">{{ $message }}</p>@enderror
                    </div>
                    <div class="pcat-field">
                        <label for="pm-client">Client name (optional)</label>
                        <input type="text" id="pm-client" name="client_name" value="{{ old('client_name') }}"
                               placeholder="e.g. Acme Corp" maxlength="120">
                    </div>
                </div>
                <div class="pcat-field" style="margin-bottom:12px;">
                    <label for="pm-desc">Description (optional)</label>
                    <textarea id="pm-desc" name="description" placeholder="Brief description…">{{ old('description') }}</textarea>
                </div>
                <button type="submit" class="linkbtn" style="padding:9px 20px;font-size:13px;">
                    <i class="fa fa-plus"></i> Create project
                </button>
            </form>
        </section>
    @else
        @if($projects->isEmpty())
            <p class="muted" style="margin:24px 0;font-size:13px;">No projects yet.</p>
        @else
            <div class="pcat-table-wrap">
                <table class="pcat-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Tasks</th>
                            <th>Due date</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $p)
                            <tr>
                                <td>
                                    <strong style="color:var(--text);">{{ $p->name }}</strong>
                                    @if($p->description)
                                        <div class="muted" style="font-size:11px;margin-top:2px;">{{ Str::limit($p->description, 60) }}</div>
                                    @endif
                                </td>
                                <td>{{ $p->client_name ?? '—' }}</td>
                                <td>
                                    <span class="pcat-badge" style="border-color:{{ $statusColor[$p->status] ?? '#6b7280' }};color:{{ $statusColor[$p->status] ?? '#6b7280' }};">
                                        {{ ucfirst(str_replace('_', ' ', $p->status)) }}
                                    </span>
                                </td>
                                <td>
                                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:{{ $priorityColor[$p->priority] ?? '#6b7280' }};font-weight:600;">
                                        <span style="width:8px;height:8px;border-radius:50%;background:{{ $priorityColor[$p->priority] ?? '#6b7280' }};display:inline-block;"></span>
                                        {{ ucfirst($p->priority) }}
                                    </span>
                                </td>
                                <td>{{ $p->tasks_count }}</td>
                                <td>{{ $p->due_date ? $p->due_date->format('d M Y') : '—' }}</td>
                                <td style="text-align:right;">
                                    <a href="{{ route('pm.projects.show', $p) }}" class="pcat-link">
                                        <i class="fa fa-eye"></i> Open
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Create project modal --}}
        <div id="pm-pj-modal"
             class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}"
             role="dialog" aria-modal="true" aria-labelledby="pm-pj-modal-title"
             aria-hidden="{{ $modalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-pm-pj-close tabindex="-1"></div>
            <div class="pcat-modal__panel">
                <div class="pcat-modal__head">
                    <h2 id="pm-pj-modal-title">New project</h2>
                    <button type="button" class="pcat-modal__close" data-pm-pj-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @if($errors->any())
                        <div class="pcat-banner pcat-banner--err" style="margin-bottom:10px;">
                            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                        </div>
                    @endif
                    <form method="POST" action="{{ route('pm.projects.store') }}">
                        @csrf
                        <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:10px;">
                            <div class="pcat-field">
                                <label>Project name *</label>
                                <input type="text" name="name" value="{{ old('name') }}"
                                       placeholder="e.g. Website Redesign" maxlength="150" required>
                            </div>
                            <div class="pcat-field">
                                <label>Client name</label>
                                <input type="text" name="client_name" value="{{ old('client_name') }}"
                                       placeholder="e.g. Acme Corp" maxlength="120">
                            </div>
                        </div>
                        <div class="pcat-field" style="margin-bottom:10px;">
                            <label>Description</label>
                            <textarea name="description" placeholder="Brief description…">{{ old('description') }}</textarea>
                        </div>
                        <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:14px;">
                            <div class="pcat-field">
                                <label>Priority</label>
                                <select name="priority">
                                    <option value="normal" @selected(old('priority','normal')==='normal')>Normal</option>
                                    <option value="high"   @selected(old('priority')==='high')>High</option>
                                    <option value="low"    @selected(old('priority')==='low')>Low</option>
                                </select>
                            </div>
                            <div class="pcat-field">
                                <label>Due date</label>
                                <input type="date" name="due_date" value="{{ old('due_date') }}">
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:8px;">
                            <button type="button" class="linkbtn"
                                    style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);"
                                    data-pm-pj-close>Cancel</button>
                            <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">
                                <i class="fa fa-plus"></i> Create
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('dashboard') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>

@if($hasProjects)
<script>
(function () {
    var modal   = document.getElementById('pm-pj-modal');
    var openBtn = document.getElementById('pm-pj-modal-open');
    if (!modal) return;

    function open() {
        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('pcat-modal-open-html');
    }
    function close() {
        modal.classList.remove('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('pcat-modal-open-html');
    }
    if (openBtn) openBtn.addEventListener('click', open);
    modal.querySelectorAll('[data-pm-pj-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('pcat-modal--open')) close();
    });
})();
</script>
@endif
@endsection
