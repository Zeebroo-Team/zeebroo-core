@extends('theme::layouts.app', ['title' => $stage->name . ' automations', 'heading' => $project->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.auto-recipient-badge{display:inline-block;font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;border:1px solid var(--border);color:var(--muted);text-transform:uppercase;letter-spacing:.03em;}
.auto-merge-help{font-size:11px;color:var(--muted);line-height:1.6;background:color-mix(in srgb,var(--card) 92%,var(--border) 8%);border:1px solid var(--border);border-radius:8px;padding:8px 10px;margin:0 0 12px;}
.auto-merge-help code{background:var(--card);border:1px solid var(--border);border-radius:4px;padding:1px 5px;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $project])

    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:12px;">
        <a href="{{ route('crm.projects.stages.index', $project) }}" class="pcat-link">
            <i class="fa fa-arrow-left"></i> Stages
        </a>
        <span class="muted">/</span>
        <span class="stg-swatch" style="display:inline-block;width:14px;height:14px;border-radius:50%;background:{{ $stage->color }};border:1px solid rgba(0,0,0,.15);"></span>
        <strong style="font-size:13px;color:var(--text);">{{ $stage->name }}</strong>
    </div>

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Send an email automatically whenever a lead enters the <strong style="color:var(--text);">{{ $stage->name }}</strong> stage.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            {{ $automations->count() }} {{ $automations->count() === 1 ? 'automation' : 'automations' }}.
        </span>
        <button type="button" id="auto-modal-open" class="linkbtn"
                style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-plus"></i> Add automation
        </button>
    </div>

    @if($automations->isEmpty())
        <p class="muted" style="margin:24px 0;font-size:13px;">No automations yet — add one to send an email whenever a lead reaches this stage.</p>
    @endif

    <div class="pcat-list">
        @foreach($automations as $a)
            <article class="pcat-card" style="cursor:default;{{ $a->is_active ? '' : 'opacity:.55;' }}">
                <div class="pcat-card__body">
                    <div class="pcat-card__head">
                        <h3 class="pcat-card__title">{{ $a->subject }}</h3>
                    </div>
                    <div class="pcat-card__meta">
                        <span class="auto-recipient-badge"><i class="fa fa-paper-plane"></i> {{ $a->recipientLabel() }}</span>
                        @if(!$a->is_active)
                            <span class="pcat-badge">Inactive</span>
                        @endif
                    </div>
                </div>
                <div class="pcat-card__actions">
                    <button type="button"
                            style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);cursor:pointer;"
                            onclick="autoOpenEdit({{ $a->id }}, {{ Illuminate\Support\Js::from($a->recipient_type) }}, {{ Illuminate\Support\Js::from($a->recipient_email) }}, {{ Illuminate\Support\Js::from($a->subject) }}, {{ Illuminate\Support\Js::from($a->body) }}, {{ $a->is_active ? 'true' : 'false' }})">
                        <i class="fa fa-pen"></i> Edit
                    </button>
                    <form method="POST" action="{{ route('crm.projects.stages.automations.destroy', [$project, $stage, $a]) }}"
                          style="margin:0;" onsubmit="return confirm('Delete this automation?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="pcat-btn-del" title="Delete">
                            <i class="fa fa-trash-can"></i>
                        </button>
                    </form>
                </div>
            </article>
        @endforeach
    </div>

    {{-- Add modal --}}
    <div id="auto-add-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="auto-add-title" aria-hidden="true">
        <div class="pcat-modal__backdrop" data-auto-close tabindex="-1"></div>
        <div class="pcat-modal__panel" style="max-width:min(94vw,560px);">
            <div class="pcat-modal__head">
                <h2 id="auto-add-title">Add automation</h2>
                <button type="button" class="pcat-modal__close" data-auto-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <form method="POST" action="{{ route('crm.projects.stages.automations.store', [$project, $stage]) }}" class="pcat-form-grid">
                    @csrf
                    @include('crm::leads.stages.partials.automation-fields', ['scope' => 'add', 'recipientTypes' => $recipientTypes])
                    <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                        <button type="button" class="pcat-btn-del" style="border-color:var(--border);color:var(--muted);background:transparent;" data-auto-close>Cancel</button>
                        <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div id="auto-edit-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="auto-edit-title" aria-hidden="true">
        <div class="pcat-modal__backdrop" data-auto-edit-close tabindex="-1"></div>
        <div class="pcat-modal__panel" style="max-width:min(94vw,560px);">
            <div class="pcat-modal__head">
                <h2 id="auto-edit-title">Edit automation</h2>
                <button type="button" class="pcat-modal__close" data-auto-edit-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <form id="auto-edit-form" method="POST" action="" class="pcat-form-grid">
                    @csrf
                    @method('PUT')
                    @include('crm::leads.stages.partials.automation-fields', ['scope' => 'edit', 'recipientTypes' => $recipientTypes])
                    <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                        <button type="button" class="pcat-btn-del" style="border-color:var(--border);color:var(--muted);background:transparent;" data-auto-edit-close>Cancel</button>
                        <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var addModal  = document.getElementById('auto-add-modal');
    var editModal = document.getElementById('auto-edit-modal');
    var addBtn    = document.getElementById('auto-modal-open');

    function lockScroll(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openModal(m) { if (!m) return; m.classList.add('pcat-modal--open'); m.setAttribute('aria-hidden', 'false'); lockScroll(true); }
    function closeModal(m) { if (!m) return; m.classList.remove('pcat-modal--open'); m.setAttribute('aria-hidden', 'true'); lockScroll(false); }

    function toggleCustomEmail(scope) {
        var select = document.querySelector('[data-auto-recipient-select="' + scope + '"]');
        var wrap   = document.querySelector('[data-auto-recipient-email-wrap="' + scope + '"]');
        if (!select || !wrap) return;
        wrap.hidden = select.value !== 'custom';
    }

    ['add', 'edit'].forEach(function (scope) {
        var select = document.querySelector('[data-auto-recipient-select="' + scope + '"]');
        select?.addEventListener('change', function () { toggleCustomEmail(scope); });
        toggleCustomEmail(scope);
    });

    addBtn && addBtn.addEventListener('click', function () {
        openModal(addModal);
        requestAnimationFrame(function () { document.getElementById('auto-add-subject')?.focus(); });
    });

    addModal?.querySelectorAll('[data-auto-close]').forEach(el => el.addEventListener('click', function () { closeModal(addModal); }));
    editModal?.querySelectorAll('[data-auto-edit-close]').forEach(el => el.addEventListener('click', function () { closeModal(editModal); }));

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (addModal?.classList.contains('pcat-modal--open')) closeModal(addModal);
        if (editModal?.classList.contains('pcat-modal--open')) closeModal(editModal);
    });

    window.autoOpenEdit = function (id, recipientType, recipientEmail, subject, body, isActive) {
        var form = document.getElementById('auto-edit-form');
        if (form) form.action = '{{ url('/crm/projects/' . $project->id . '/stages/' . $stage->id . '/automations') }}/' + id;
        document.getElementById('auto-edit-recipient').value = recipientType;
        document.getElementById('auto-edit-recipient-email').value = recipientEmail || '';
        document.getElementById('auto-edit-subject').value = subject;
        document.getElementById('auto-edit-body').value = body;
        document.getElementById('auto-edit-active').checked = Boolean(isActive);
        toggleCustomEmail('edit');
        openModal(editModal);
        requestAnimationFrame(function () { document.getElementById('auto-edit-subject')?.focus(); });
    };
})();
</script>
@endsection
