@extends('theme::layouts.app', ['title' => 'Custom fields', 'heading' => $project->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.cf-type-badge{display:inline-block;font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;border:1px solid var(--border);color:var(--muted);text-transform:uppercase;letter-spacing:.03em;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $project])

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Add extra fields for <strong style="color:var(--text);">{{ $project->name }}</strong> — then drag them into the lead form builder to use them. Drag cards here to reorder.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            {{ $fields->count() }} {{ $fields->count() === 1 ? 'field' : 'fields' }}.
        </span>
        <span id="cf-reorder-status" class="pcat-reorder-status" hidden aria-live="polite"></span>
        <button type="button" id="cf-modal-open" class="linkbtn"
                style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-plus"></i> Add field
        </button>
    </div>

    @if($fields->isEmpty())
        <p class="muted" style="margin:24px 0;font-size:13px;">No custom fields yet — add one, then use it as a "Form field" block in the lead form builder.</p>
    @endif

    <div id="cf-sort-list" class="pcat-list">
        @foreach($fields as $f)
            <article class="pcat-card" data-field-id="{{ $f->id }}" style="cursor:default;">
                <span class="pcat-drag-handle" title="Drag to reorder" aria-hidden="true">
                    <i class="fa fa-grip-vertical"></i>
                </span>
                <div class="pcat-card__body">
                    <div class="pcat-card__head">
                        <h3 class="pcat-card__title">{{ $f->label }}</h3>
                    </div>
                    <div class="pcat-card__meta">
                        <span class="cf-type-badge">{{ $f->typeLabel() }}</span>
                        @if($f->is_required)
                            <span class="pcat-badge pcat-badge--on">Required</span>
                        @endif
                        @if($f->type === 'select' && $f->optionList())
                            <span class="muted">{{ implode(', ', $f->optionList()) }}</span>
                        @endif
                    </div>
                </div>
                <div class="pcat-card__actions">
                    <button type="button"
                            style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);cursor:pointer;"
                            onclick="cfOpenEdit({{ $f->id }}, {{ Illuminate\Support\Js::from($f->label) }}, {{ Illuminate\Support\Js::from($f->type) }}, {{ Illuminate\Support\Js::from(implode("\n", $f->optionList())) }}, {{ $f->is_required ? 'true' : 'false' }})">
                        <i class="fa fa-pen"></i> Edit
                    </button>
                    <form method="POST" action="{{ route('crm.projects.custom-fields.destroy', [$project, $f]) }}"
                          style="margin:0;" onsubmit="return confirm('Delete {{ addslashes($f->label) }}? Any saved values for it will be removed too.');">
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
    <div id="cf-add-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="cf-add-title" aria-hidden="true">
        <div class="pcat-modal__backdrop" data-cf-close tabindex="-1"></div>
        <div class="pcat-modal__panel">
            <div class="pcat-modal__head">
                <h2 id="cf-add-title">Add field</h2>
                <button type="button" class="pcat-modal__close" data-cf-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <form method="POST" action="{{ route('crm.projects.custom-fields.store', $project) }}" class="pcat-form-grid">
                    @csrf
                    <div class="pcat-field">
                        <label for="cf-add-label">Field label</label>
                        <input id="cf-add-label" name="label" maxlength="100" required placeholder="e.g. Budget range">
                    </div>
                    <div class="pcat-field">
                        <label for="cf-add-type">Field type</label>
                        <select id="cf-add-type" name="type" data-cf-type-select="add">
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcat-field" data-cf-options-wrap="add" hidden>
                        <label for="cf-add-options">Options <span class="muted" style="font-weight:400;text-transform:none;">one per line</span></label>
                        <textarea id="cf-add-options" name="options" placeholder="Small&#10;Medium&#10;Large"></textarea>
                    </div>
                    <div class="pcat-active-row">
                        <label class="pcat-active-row__lbl" for="cf-add-required">Required</label>
                        <label class="pcat-switch">
                            <input type="checkbox" id="cf-add-required" name="is_required" value="1">
                            <span class="pcat-switch-slider"></span>
                        </label>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                        <button type="button" class="pcat-btn-del" style="border-color:var(--border);color:var(--muted);background:transparent;" data-cf-close>Cancel</button>
                        <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div id="cf-edit-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="cf-edit-title" aria-hidden="true">
        <div class="pcat-modal__backdrop" data-cf-edit-close tabindex="-1"></div>
        <div class="pcat-modal__panel">
            <div class="pcat-modal__head">
                <h2 id="cf-edit-title">Edit field</h2>
                <button type="button" class="pcat-modal__close" data-cf-edit-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <form id="cf-edit-form" method="POST" action="" class="pcat-form-grid">
                    @csrf
                    @method('PUT')
                    <div class="pcat-field">
                        <label for="cf-edit-label">Field label</label>
                        <input id="cf-edit-label" name="label" maxlength="100" required>
                    </div>
                    <div class="pcat-field">
                        <label for="cf-edit-type">Field type</label>
                        <select id="cf-edit-type" name="type" data-cf-type-select="edit">
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcat-field" data-cf-options-wrap="edit" hidden>
                        <label for="cf-edit-options">Options <span class="muted" style="font-weight:400;text-transform:none;">one per line</span></label>
                        <textarea id="cf-edit-options" name="options"></textarea>
                    </div>
                    <div class="pcat-active-row">
                        <label class="pcat-active-row__lbl" for="cf-edit-required">Required</label>
                        <label class="pcat-switch">
                            <input type="checkbox" id="cf-edit-required" name="is_required" value="1">
                            <span class="pcat-switch-slider"></span>
                        </label>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                        <button type="button" class="pcat-btn-del" style="border-color:var(--border);color:var(--muted);background:transparent;" data-cf-edit-close>Cancel</button>
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
    var addModal  = document.getElementById('cf-add-modal');
    var editModal = document.getElementById('cf-edit-modal');
    var addBtn    = document.getElementById('cf-modal-open');

    function lockScroll(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openModal(m) { if (!m) return; m.classList.add('pcat-modal--open'); m.setAttribute('aria-hidden', 'false'); lockScroll(true); }
    function closeModal(m) { if (!m) return; m.classList.remove('pcat-modal--open'); m.setAttribute('aria-hidden', 'true'); lockScroll(false); }

    function toggleOptionsVisibility(scope) {
        var select = document.querySelector('[data-cf-type-select="' + scope + '"]');
        var wrap   = document.querySelector('[data-cf-options-wrap="' + scope + '"]');
        if (!select || !wrap) return;
        wrap.hidden = select.value !== 'select';
    }

    ['add', 'edit'].forEach(function (scope) {
        var select = document.querySelector('[data-cf-type-select="' + scope + '"]');
        select?.addEventListener('change', function () { toggleOptionsVisibility(scope); });
        toggleOptionsVisibility(scope);
    });

    addBtn && addBtn.addEventListener('click', function () {
        openModal(addModal);
        requestAnimationFrame(function () { document.getElementById('cf-add-label')?.focus(); });
    });

    addModal?.querySelectorAll('[data-cf-close]').forEach(el => el.addEventListener('click', function () { closeModal(addModal); }));
    editModal?.querySelectorAll('[data-cf-edit-close]').forEach(el => el.addEventListener('click', function () { closeModal(editModal); }));

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (addModal?.classList.contains('pcat-modal--open')) closeModal(addModal);
        if (editModal?.classList.contains('pcat-modal--open')) closeModal(editModal);
    });

    window.cfOpenEdit = function (id, label, type, options, required) {
        var form = document.getElementById('cf-edit-form');
        if (form) form.action = '{{ url('/crm/projects/' . $project->id . '/custom-fields') }}/' + id;
        document.getElementById('cf-edit-label').value = label;
        document.getElementById('cf-edit-type').value = type;
        document.getElementById('cf-edit-options').value = options;
        document.getElementById('cf-edit-required').checked = Boolean(required);
        toggleOptionsVisibility('edit');
        openModal(editModal);
        requestAnimationFrame(function () { document.getElementById('cf-edit-label')?.focus(); });
    };
})();

(function () {
    var list = document.getElementById('cf-sort-list');
    if (!list || typeof Sortable === 'undefined') return;

    var statusEl = document.getElementById('cf-reorder-status');
    var reorderUrl = @json(route('crm.projects.custom-fields.reorder', $project));

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
        var ids = Array.from(list.querySelectorAll('.pcat-card[data-field-id]'))
            .map(function (el) { return parseInt(el.getAttribute('data-field-id'), 10); })
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
