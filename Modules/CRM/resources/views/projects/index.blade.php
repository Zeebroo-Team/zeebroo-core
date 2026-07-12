@extends('theme::layouts.app', ['title' => 'Projects', 'heading' => 'Projects'])

@php
    $hasProjects = $hasProjects ?? false;
    $modalOpen   = $hasProjects && $errors->any();
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('project'))
        <div class="pcat-banner pcat-banner--err">{{ $errors->first('project') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Group leads for <strong style="color:var(--text);">{{ $business->name }}</strong> by project — each project has its own pipeline stages and custom lead fields.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if(!$hasProjects) Create your <strong style="color:var(--text);">first project</strong> below. @endif
        </span>
        @if($hasProjects)
            <button type="button" id="pj-modal-open" class="linkbtn"
                    style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> New project
            </button>
        @endif
    </div>

    @if(!$hasProjects)
        <section class="pcat-inline">
            <h2>New project</h2>
            <p class="pcat-muted">e.g. "Website Redesign" or "Q3 Outbound" — leads, stages, and custom fields all live inside a project.</p>
            @include('crm::projects.partials.create-form')
        </section>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Leads</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($projects as $p)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $p->name }}</strong></td>
                            <td><span class="muted" style="font-size:12px;">{{ \Illuminate\Support\Str::limit($p->description ?? '—', 60) }}</span></td>
                            <td>{{ $p->leads_count ?? 0 }}</td>
                            <td>
                                @if($p->isArchived())
                                    <span class="pcat-badge pcat-badge--off">Archived</span>
                                @else
                                    <span class="pcat-badge pcat-badge--on">Active</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('crm.projects.show', $p) }}" class="pcat-link">
                                    <i class="fa fa-eye"></i> Open
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="pj-modal"
             class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}"
             role="dialog" aria-modal="true" aria-labelledby="pj-modal-title"
             aria-hidden="{{ $modalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-pj-modal-close tabindex="-1"></div>
            <div class="pcat-modal__panel" style="max-width:min(94vw,560px);">
                <div class="pcat-modal__head">
                    <h2 id="pj-modal-title">New project</h2>
                    <button type="button" class="pcat-modal__close" data-pj-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @include('crm::projects.partials.create-form')
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
    var modal  = document.getElementById('pj-modal');
    var openBtn = document.getElementById('pj-modal-open');
    function lock(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openM() { modal.classList.add('pcat-modal--open'); modal.setAttribute('aria-hidden','false'); lock(true); }
    function closeM() { modal.classList.remove('pcat-modal--open'); modal.setAttribute('aria-hidden','true'); lock(false); openBtn?.focus(); }
    openBtn?.addEventListener('click', openM);
    modal?.querySelectorAll('[data-pj-modal-close]').forEach(el => el.addEventListener('click', closeM));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal?.classList.contains('pcat-modal--open')) closeM(); });
    if (modal?.classList.contains('pcat-modal--open')) lock(true);
})();
</script>
@endif
@endsection
