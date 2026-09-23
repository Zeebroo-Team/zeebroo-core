@extends('theme::layouts.app', ['title' => 'CRM', 'heading' => 'CRM'])

@php
    $modalOpen = $errors->any();
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.crm-ov-welcome{border:1px solid var(--border);border-radius:12px;padding:18px 20px;background:color-mix(in srgb,var(--primary) 6%,var(--card));margin-bottom:16px;}
.crm-ov-welcome h2{margin:0 0 6px;font-size:18px;color:var(--text);}
.crm-ov-welcome p{margin:0 0 14px;font-size:13px;color:var(--muted);line-height:1.5;max-width:640px;}
.crm-ov-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:16px;}
.crm-ov-stat{border:1px solid var(--border);border-radius:11px;padding:14px 16px;background:var(--card);}
.crm-ov-stat strong{display:block;font-size:24px;color:var(--text);}
.crm-ov-stat span{font-size:12px;color:var(--muted);}
.crm-ov-tips{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px;}
.crm-ov-tip{border:1px solid var(--border);border-radius:11px;padding:14px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.crm-ov-tip .fa{color:var(--primary);margin-bottom:8px;font-size:16px;}
.crm-ov-tip strong{display:block;font-size:13px;color:var(--text);margin-bottom:4px;}
.crm-ov-tip p{margin:0;font-size:12px;color:var(--muted);line-height:1.45;}
.crm-ov-recent{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;}
.crm-ov-card{border:1px solid var(--border);border-radius:11px;padding:14px;background:var(--card);text-decoration:none;display:block;}
.crm-ov-card strong{display:block;font-size:13px;color:var(--text);margin-bottom:4px;}
.crm-ov-card span{font-size:12px;color:var(--muted);}
.crm-ov-card .crm-ov-leads{margin-top:8px;display:inline-flex;align-items:center;gap:6px;font-size:11px;color:var(--muted);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('project'))
        <div class="pcat-banner pcat-banner--err">{{ $errors->first('project') }}</div>
    @endif

    <div class="crm-ov-welcome">
        <h2><i class="fa fa-handshake"></i> Welcome to CRM</h2>
        <p>Group your leads into relations, track them through a pipeline, capture new ones with forms, and keep every contact and follow-up task in one place.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" id="crm-ov-modal-open" class="linkbtn" style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> Create New Relation
            </button>
            <a href="{{ route('crm.projects.index') }}" class="linkbtn"
               style="padding:9px 18px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-diagram-project"></i> View All Relations
            </a>
        </div>
    </div>

    <div class="crm-ov-stats">
        <div class="crm-ov-stat"><strong>{{ $relationsCount }}</strong><span>Relations</span></div>
        <div class="crm-ov-stat"><strong>{{ $contactsCount }}</strong><span>Contacts</span></div>
        <div class="crm-ov-stat"><strong>{{ $openTasksCount }}</strong><span>Open Tasks</span></div>
    </div>

    <h3 style="font-size:13px;color:var(--text);margin:0 0 10px;"><i class="fa fa-lightbulb" style="color:var(--primary);"></i> Quick Tips</h3>
    <div class="crm-ov-tips">
        <div class="crm-ov-tip">
            <i class="fa fa-diagram-project"></i>
            <strong>Track deals with Relations</strong>
            <p>Group leads into a relation and move them through your pipeline stage by stage.</p>
        </div>
        <div class="crm-ov-tip">
            <i class="fa fa-window-restore"></i>
            <strong>Capture leads with Forms</strong>
            <p>Publish a form for a relation and new leads drop straight into its pipeline.</p>
        </div>
        <div class="crm-ov-tip">
            <i class="fa fa-address-book"></i>
            <strong>Keep contacts organized</strong>
            <p>Every customer and lead you talk to shows up in Contacts with their activity.</p>
        </div>
        <div class="crm-ov-tip">
            <i class="fa fa-list-check"></i>
            <strong>Never miss a follow-up</strong>
            <p>Create tasks and track what's open, overdue or completed in Tasks.</p>
        </div>
    </div>

    @if($hasProjects)
        <div class="pcat-toolbar" style="margin-top:16px;">
            <h3 style="font-size:13px;color:var(--text);margin:0;"><i class="fa fa-clock-rotate-left" style="color:var(--primary);"></i> Recent Relations</h3>
            <a href="{{ route('crm.projects.index') }}" class="pcat-link">View all <i class="fa fa-arrow-right"></i></a>
        </div>
        <div class="crm-ov-recent">
            @foreach($recentProjects as $p)
                <a href="{{ route('crm.projects.show', $p) }}" class="crm-ov-card">
                    <strong>{{ $p->name }}</strong>
                    <span>{{ \Illuminate\Support\Str::limit($p->description ?? 'No description', 60) }}</span>
                    <div class="crm-ov-leads"><i class="fa fa-users"></i> {{ $p->leads_count ?? 0 }} lead{{ ($p->leads_count ?? 0) === 1 ? '' : 's' }}</div>
                </a>
            @endforeach
        </div>
    @endif

    <div id="crm-ov-modal"
         class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}"
         role="dialog" aria-modal="true" aria-labelledby="crm-ov-modal-title"
         aria-hidden="{{ $modalOpen ? 'false' : 'true' }}">
        <div class="pcat-modal__backdrop" data-crm-ov-modal-close tabindex="-1"></div>
        <div class="pcat-modal__panel" style="max-width:min(94vw,560px);">
            <div class="pcat-modal__head">
                <h2 id="crm-ov-modal-title">New relation</h2>
                <button type="button" class="pcat-modal__close" data-crm-ov-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                @include('crm::projects.partials.create-form')
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var modal   = document.getElementById('crm-ov-modal');
    var openBtn = document.getElementById('crm-ov-modal-open');
    function lock(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openM() { modal.classList.add('pcat-modal--open'); modal.setAttribute('aria-hidden','false'); lock(true); }
    function closeM() { modal.classList.remove('pcat-modal--open'); modal.setAttribute('aria-hidden','true'); lock(false); openBtn?.focus(); }
    openBtn?.addEventListener('click', openM);
    modal?.querySelectorAll('[data-crm-ov-modal-close]').forEach(el => el.addEventListener('click', closeM));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal?.classList.contains('pcat-modal--open')) closeM(); });
    if (modal?.classList.contains('pcat-modal--open')) lock(true);
})();
</script>
@endsection
