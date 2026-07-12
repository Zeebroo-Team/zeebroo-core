@extends('theme::layouts.app', ['title' => 'Leads', 'heading' => $project->name])

@php
    $hasLeads         = $hasLeads ?? false;
    $hasForms         = $hasForms ?? false;
    $hasActiveFilters = filled($search ?? '') || ($stageFilter ?? 'all') !== 'all' || ($statusFilter ?? 'open') !== 'open';
    $modalOpen        = $hasLeads && $errors->any();
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.ld-stage{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);}
.ld-stats{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:0 0 16px;}
@media(min-width:640px){.ld-stats{grid-template-columns:repeat(auto-fit,minmax(140px,1fr));}}
.ld-stat{border:1px solid var(--border);border-radius:11px;padding:12px 14px;background:var(--card);}
.ld-stat__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 6px;}
.ld-stat__value{font-size:22px;font-weight:800;color:var(--text);margin:0;line-height:1.15;}
.ld-stat__sub{font-size:11px;color:var(--muted);margin:3px 0 0;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $project])

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Track prospects for <strong style="color:var(--text);">{{ $project->name }}</strong> from first contact through to won or lost.
    </p>

    @if($hasForms && $leadForm)
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0 0 14px;padding:9px 12px;border:1px solid var(--border);border-radius:10px;background:var(--card);font-size:12.5px;">
            <i class="fa fa-file-lines" style="color:var(--muted);"></i>
            <span class="muted">Using lead form:</span>
            <strong style="color:var(--text);">{{ $leadForm->name }}</strong>
            @if($leadForm->is_published)
                <span class="pcat-badge pcat-badge--on">Published</span>
            @else
                <span class="pcat-badge pcat-badge--off">Draft</span>
            @endif
            <a href="{{ route('crm.projects.forms.builder', [$project, $leadForm]) }}" class="pcat-link" style="margin-left:auto;">
                <i class="fa fa-pen"></i> Edit form
            </a>
        </div>
    @endif

    <div class="ld-stats">
        @foreach($pipeline as $stageId => $info)
            <div class="ld-stat">
                <p class="ld-stat__label">{{ $info['label'] }}</p>
                <p class="ld-stat__value">{{ $info['count'] }}</p>
                @if($info['value'] > 0)
                    <p class="ld-stat__sub">{{ number_format($info['value'], 2) }}</p>
                @endif
            </div>
        @endforeach
    </div>

    @if($hasLeads)
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;align-items:center;">
        @foreach($statusTabs as $key => $label)
            <a href="{{ route('crm.projects.leads.index', array_merge(['project' => $project->id], request()->query(), ['status' => $key])) }}"
               style="padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid var(--border);
                      {{ ($statusFilter ?? 'open') === $key ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'background:transparent;color:var(--muted);' }}">
                {{ $label }}
            </a>
        @endforeach

        <form method="GET" action="{{ route('crm.projects.leads.index', $project) }}"
              style="margin-left:auto;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="status" value="{{ $statusFilter ?? 'open' }}">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search…"
                   style="padding:5px 10px;border-radius:8px;border:1px solid var(--border);font-size:12px;background:var(--card);color:var(--text);width:160px;">
            <select name="stage"
                    style="padding:5px 10px;border-radius:8px;border:1px solid var(--border);font-size:12px;background:var(--card);color:var(--text);cursor:pointer;">
                <option value="all">All stages</option>
                @foreach($stageOptions as $opt)
                    <option value="{{ $opt->id }}" @selected((string) ($stageFilter ?? 'all') === (string) $opt->id)>{{ $opt->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="linkbtn" style="padding:5px 12px;font-size:12px;">Filter</button>
            @if($hasActiveFilters)
                <a href="{{ route('crm.projects.leads.index', $project) }}" class="linkbtn"
                   style="padding:5px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--muted);">Clear</a>
            @endif
        </form>
    </div>
    @endif

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if(!$hasLeads && $hasForms) Add your <strong style="color:var(--text);">first lead</strong> below. @endif
        </span>
        <div style="display:flex;gap:8px;align-items:center;">
            @if($hasLeads)
                <a href="{{ route('crm.projects.leads.board', $project) }}" class="linkbtn"
                   style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa fa-table-columns"></i> Board view
                </a>
                <button type="button" id="ld-modal-open" class="linkbtn"
                        style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa fa-plus"></i> New lead
                </button>
            @endif
        </div>
    </div>

    @if(!$hasLeads)
        @if(!$hasForms)
            <section class="pcat-inline" style="text-align:center;padding:32px 20px;">
                <i class="fa fa-file-circle-plus" style="font-size:26px;color:var(--muted);margin-bottom:10px;display:block;"></i>
                <h2 style="margin:0 0 6px;">Before you add a lead, you should create a lead form first</h2>
                <p class="pcat-muted" style="margin:0 0 16px;">
                    A lead form defines the fields you'll capture prospects with — set one up, then come back to add your first lead.
                </p>
                <a href="{{ route('crm.projects.forms.index', $project) }}" class="linkbtn"
                   style="padding:8px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
                    <i class="fa fa-plus"></i> Create a lead form
                </a>
            </section>
        @else
            <section class="pcat-inline">
                <h2>New lead</h2>
                <p class="pcat-muted">Capture a prospect, then move it through the pipeline as you follow up.</p>
                @include('crm::leads.partials.create-form')
            </section>
        @endif
    @else
        @if($leads->isEmpty())
            <p class="muted" style="margin:24px 0;font-size:13px;">
                @if($hasActiveFilters)
                    No leads match your filters. <a href="{{ route('crm.projects.leads.index', $project) }}" class="pcat-link">Clear filters</a>
                @else
                    No leads found.
                @endif
            </p>
        @else
            <div class="pcat-table-wrap">
                <table class="pcat-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Stage</th>
                            <th style="text-align:right;">Value</th>
                            <th>Assigned to</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leads as $l)
                            <tr>
                                <td>
                                    <strong style="color:var(--text);">{{ $l->name }}</strong>
                                    @if($l->email)<div class="muted" style="font-size:11px;">{{ $l->email }}</div>@endif
                                </td>
                                <td>{{ $l->company ?? '—' }}</td>
                                <td><span class="ld-stage" style="border-color:{{ $l->stageColor() }};color:{{ $l->stageColor() }};">{{ $l->stageLabel() }}</span></td>
                                <td style="text-align:right;font-weight:700;">{{ $l->estimated_value !== null ? number_format((float) $l->estimated_value, 2) : '—' }}</td>
                                <td>{{ $l->assignedTo?->name ?? '—' }}</td>
                                <td style="text-align:right;">
                                    <a href="{{ route('crm.leads.show', $l) }}" class="pcat-link">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div id="ld-modal"
             class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}"
             role="dialog" aria-modal="true" aria-labelledby="ld-modal-title"
             aria-hidden="{{ $modalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-ld-modal-close tabindex="-1"></div>
            <div class="pcat-modal__panel" style="max-width:min(94vw,700px);">
                <div class="pcat-modal__head">
                    <h2 id="ld-modal-title">New lead</h2>
                    <button type="button" class="pcat-modal__close" data-ld-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @include('crm::leads.partials.create-form')
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

@if($hasLeads)
<script>
(function () {
    var modal  = document.getElementById('ld-modal');
    var openBtn = document.getElementById('ld-modal-open');
    function lock(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openM() { modal.classList.add('pcat-modal--open'); modal.setAttribute('aria-hidden','false'); lock(true); }
    function closeM() { modal.classList.remove('pcat-modal--open'); modal.setAttribute('aria-hidden','true'); lock(false); openBtn?.focus(); }
    openBtn?.addEventListener('click', openM);
    modal?.querySelectorAll('[data-ld-modal-close]').forEach(el => el.addEventListener('click', closeM));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal?.classList.contains('pcat-modal--open')) closeM(); });
    if (modal?.classList.contains('pcat-modal--open')) lock(true);
})();
</script>
@endif
@endsection
