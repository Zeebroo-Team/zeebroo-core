@extends('theme::layouts.app', ['title' => 'Pipeline stages', 'heading' => $project->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.stg-swatch{width:16px;height:16px;border-radius:50%;display:inline-block;flex-shrink:0;border:1px solid rgba(0,0,0,.15);}
.stg-color-input{width:44px;height:34px;padding:2px;border-radius:8px;border:1px solid var(--border);background:var(--card);cursor:pointer;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $project])

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('stage'))
        <div class="pcat-banner pcat-banner--err">{{ $errors->first('stage') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Customize the stages leads move through for <strong style="color:var(--text);">{{ $project->name }}</strong>. Drag cards to reorder.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            {{ $stages->count() }} {{ $stages->count() === 1 ? 'stage' : 'stages' }}.
        </span>
        <span id="stg-reorder-status" class="pcat-reorder-status" hidden aria-live="polite"></span>
        <button type="button" id="stg-modal-open" class="linkbtn"
                style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-plus"></i> Add stage
        </button>
    </div>

    <div id="stg-sort-list" class="pcat-list">
        @foreach($stages as $s)
            <article class="pcat-card" data-stage-id="{{ $s->id }}" style="cursor:default;">
                <span class="pcat-drag-handle" title="Drag to reorder" aria-hidden="true">
                    <i class="fa fa-grip-vertical"></i>
                </span>
                <div class="pcat-card__body">
                    <div class="pcat-card__head">
                        <span class="stg-swatch" style="background:{{ $s->color }};"></span>
                        <h3 class="pcat-card__title">{{ $s->name }}</h3>
                    </div>
                    <div class="pcat-card__meta">
                        <span class="muted">{{ $s->leads_count ?? 0 }} lead{{ ($s->leads_count ?? 0) !== 1 ? 's' : '' }}</span>
                        @if($s->is_won)
                            <span class="pcat-badge pcat-badge--on">Won outcome</span>
                        @elseif($s->is_lost)
                            <span class="pcat-badge" style="border-color:color-mix(in srgb,#ef4444 45%,var(--border));color:#f97373;">Lost outcome</span>
                        @endif
                    </div>
                </div>
                <div class="pcat-card__actions">
                    <a href="{{ route('crm.projects.stages.automations.index', [$project, $s]) }}"
                       style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                        <i class="fa fa-bolt"></i> Automations
                    </a>
                    <button type="button"
                            style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);cursor:pointer;"
                            onclick="stgOpenEdit({{ $s->id }},'{{ addslashes($s->name) }}','{{ $s->color }}',{{ $s->is_won ? 'true' : 'false' }},{{ $s->is_lost ? 'true' : 'false' }})">
                        <i class="fa fa-pen"></i> Edit
                    </button>
                    @if(($s->leads_count ?? 0) > 0)
                        <span class="muted pcat-card__note">In use</span>
                    @else
                        <form method="POST" action="{{ route('crm.projects.stages.destroy', [$project, $s]) }}"
                              style="margin:0;" onsubmit="return confirm('Delete {{ addslashes($s->name) }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="pcat-btn-del" title="Delete">
                                <i class="fa fa-trash-can"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    {{-- Add modal --}}
    <div id="stg-add-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="stg-add-title" aria-hidden="true">
        <div class="pcat-modal__backdrop" data-stg-close tabindex="-1"></div>
        <div class="pcat-modal__panel">
            <div class="pcat-modal__head">
                <h2 id="stg-add-title">Add stage</h2>
                <button type="button" class="pcat-modal__close" data-stg-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <form method="POST" action="{{ route('crm.projects.stages.store', $project) }}" class="pcat-form-grid">
                    @csrf
                    <div class="pcat-field">
                        <label for="stg-add-name">Stage name</label>
                        <input id="stg-add-name" name="name" value="{{ old('name') }}" maxlength="60" required placeholder="e.g. Trial">
                        @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                    </div>
                    <div class="pcat-field">
                        <label for="stg-add-color">Color</label>
                        <input id="stg-add-color" type="color" name="color" value="{{ old('color', '#64748b') }}" class="stg-color-input">
                    </div>
                    <div class="pcat-active-row">
                        <label class="pcat-active-row__lbl" for="stg-add-won">Counts as won</label>
                        <label class="pcat-switch">
                            <input type="checkbox" id="stg-add-won" name="is_won" value="1" data-stg-outcome="won">
                            <span class="pcat-switch-slider"></span>
                        </label>
                    </div>
                    <div class="pcat-active-row">
                        <label class="pcat-active-row__lbl" for="stg-add-lost">Counts as lost</label>
                        <label class="pcat-switch">
                            <input type="checkbox" id="stg-add-lost" name="is_lost" value="1" data-stg-outcome="lost">
                            <span class="pcat-switch-slider"></span>
                        </label>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                        <button type="button" class="pcat-btn-del" style="border-color:var(--border);color:var(--muted);background:transparent;" data-stg-close>Cancel</button>
                        <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div id="stg-edit-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="stg-edit-title" aria-hidden="true">
        <div class="pcat-modal__backdrop" data-stg-edit-close tabindex="-1"></div>
        <div class="pcat-modal__panel">
            <div class="pcat-modal__head">
                <h2 id="stg-edit-title">Edit stage</h2>
                <button type="button" class="pcat-modal__close" data-stg-edit-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <form id="stg-edit-form" method="POST" action="" class="pcat-form-grid">
                    @csrf
                    @method('PUT')
                    <div class="pcat-field">
                        <label for="stg-edit-name">Stage name</label>
                        <input id="stg-edit-name" name="name" maxlength="60" required>
                    </div>
                    <div class="pcat-field">
                        <label for="stg-edit-color">Color</label>
                        <input id="stg-edit-color" type="color" name="color" class="stg-color-input">
                    </div>
                    <div class="pcat-active-row">
                        <label class="pcat-active-row__lbl" for="stg-edit-won">Counts as won</label>
                        <label class="pcat-switch">
                            <input type="checkbox" id="stg-edit-won" name="is_won" value="1" data-stg-outcome="won">
                            <span class="pcat-switch-slider"></span>
                        </label>
                    </div>
                    <div class="pcat-active-row">
                        <label class="pcat-active-row__lbl" for="stg-edit-lost">Counts as lost</label>
                        <label class="pcat-switch">
                            <input type="checkbox" id="stg-edit-lost" name="is_lost" value="1" data-stg-outcome="lost">
                            <span class="pcat-switch-slider"></span>
                        </label>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                        <button type="button" class="pcat-btn-del" style="border-color:var(--border);color:var(--muted);background:transparent;" data-stg-edit-close>Cancel</button>
                        <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div style="margin-top:14px;">
    <a href="{{ route('crm.projects.leads.index', $project) }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Leads
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var addModal  = document.getElementById('stg-add-modal');
    var editModal = document.getElementById('stg-edit-modal');
    var addBtn    = document.getElementById('stg-modal-open');

    function lockScroll(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openModal(m) { if (!m) return; m.classList.add('pcat-modal--open'); m.setAttribute('aria-hidden', 'false'); lockScroll(true); }
    function closeModal(m) { if (!m) return; m.classList.remove('pcat-modal--open'); m.setAttribute('aria-hidden', 'true'); lockScroll(false); }

    addBtn && addBtn.addEventListener('click', function () {
        openModal(addModal);
        requestAnimationFrame(function () { document.getElementById('stg-add-name')?.focus(); });
    });

    addModal?.querySelectorAll('[data-stg-close]').forEach(el => el.addEventListener('click', function () { closeModal(addModal); }));
    editModal?.querySelectorAll('[data-stg-edit-close]').forEach(el => el.addEventListener('click', function () { closeModal(editModal); }));

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (addModal?.classList.contains('pcat-modal--open')) closeModal(addModal);
        if (editModal?.classList.contains('pcat-modal--open')) closeModal(editModal);
    });

    // Mutually exclusive won/lost toggles within each form.
    document.querySelectorAll('[data-stg-outcome]').forEach(function (box) {
        box.addEventListener('change', function () {
            if (!box.checked) return;
            var form = box.closest('form');
            var other = form?.querySelector('[data-stg-outcome="' + (box.dataset.stgOutcome === 'won' ? 'lost' : 'won') + '"]');
            if (other) other.checked = false;
        });
    });

    window.stgOpenEdit = function (id, name, color, isWon, isLost) {
        var form = document.getElementById('stg-edit-form');
        if (form) form.action = '{{ url('/crm/projects/' . $project->id . '/stages') }}/' + id;
        document.getElementById('stg-edit-name').value = name;
        document.getElementById('stg-edit-color').value = color || '#64748b';
        document.getElementById('stg-edit-won').checked = Boolean(isWon);
        document.getElementById('stg-edit-lost').checked = Boolean(isLost);
        openModal(editModal);
        requestAnimationFrame(function () { document.getElementById('stg-edit-name')?.focus(); });
    };
})();

(function () {
    var list = document.getElementById('stg-sort-list');
    if (!list || typeof Sortable === 'undefined') return;

    var statusEl = document.getElementById('stg-reorder-status');
    var reorderUrl = @json(route('crm.projects.stages.reorder', $project));

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value || '';
    }

    function setStatus(text, cls) {
        if (!statusEl) return;
        statusEl.textContent = text || '';
        statusEl.hidden = !text;
        statusEl.classList.remove('is-saving', 'is-error');
        if (cls) statusEl.classList.add(cls);
    }

    function save() {
        var ids = Array.from(list.querySelectorAll('.pcat-card[data-stage-id]'))
            .map(function (el) { return parseInt(el.getAttribute('data-stage-id'), 10); })
            .filter(Boolean);
        if (!ids.length) return;
        setStatus('Saving…', 'is-saving');
        fetch(reorderUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ ids: ids }),
        })
        .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, d: d }; }); })
        .then(function (r) {
            if (!r.ok) { setStatus('Could not save.', 'is-error'); return; }
            setStatus('Saved', '');
            setTimeout(function () { setStatus('', ''); }, 1800);
        })
        .catch(function () { setStatus('Could not save.', 'is-error'); });
    }

    Sortable.create(list, {
        animation: 150,
        handle: '.pcat-drag-handle',
        ghostClass: 'pcat-sort-ghost',
        chosenClass: 'pcat-sort-chosen',
        draggable: '.pcat-card',
        onEnd: save,
    });
})();
</script>
@endsection
