@extends('theme::layouts.app', ['title' => 'Mail filters', 'heading' => $business->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.mf-rule-badge{display:inline-block;font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;border:1px solid var(--border);color:var(--muted);text-transform:uppercase;letter-spacing:.03em;}
</style>

<div class="pcat-page-card card" style="max-width:720px;padding:14px;">
    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Automatically process incoming mail as it syncs. The first matching filter (top to bottom) wins — drag cards to reorder.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            {{ $filters->count() }} {{ $filters->count() === 1 ? 'filter' : 'filters' }}.
        </span>
        <span id="mf-reorder-status" class="pcat-reorder-status" hidden aria-live="polite"></span>
        <button type="button" id="mf-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-plus"></i> Add filter
        </button>
    </div>

    @if($filters->isEmpty())
        <p class="muted" style="margin:24px 0;font-size:13px;">No filters yet — incoming mail is stored as-is.</p>
    @endif

    <div id="mf-sort-list" class="pcat-list">
        @foreach($filters as $f)
            <article class="pcat-card" data-filter-id="{{ $f->id }}" style="cursor:default;{{ $f->is_active ? '' : 'opacity:.55;' }}">
                <span class="pcat-drag-handle" title="Drag to reorder" aria-hidden="true"><i class="fa fa-grip-vertical"></i></span>
                <div class="pcat-card__body">
                    <div class="pcat-card__head">
                        <h3 class="pcat-card__title">If {{ strtolower($f->fieldLabel()) }} contains "{{ $f->value }}"</h3>
                    </div>
                    <div class="pcat-card__meta">
                        <span class="mf-rule-badge"><i class="fa fa-bolt"></i> {{ $f->actionLabel() }}</span>
                        @if(!$f->is_active)
                            <span class="pcat-badge">Inactive</span>
                        @endif
                    </div>
                </div>
                <div class="pcat-card__actions">
                    <button type="button"
                            style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);cursor:pointer;"
                            onclick="mfOpenEdit({{ $f->id }}, {{ Illuminate\Support\Js::from($f->field) }}, {{ Illuminate\Support\Js::from($f->value) }}, {{ Illuminate\Support\Js::from($f->action) }}, {{ $f->is_active ? 'true' : 'false' }})">
                        <i class="fa fa-pen"></i> Edit
                    </button>
                    <form method="POST" action="{{ route('mail.filters.destroy', $f) }}" style="margin:0;" onsubmit="return confirm('Delete this filter?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="pcat-btn-del" title="Delete"><i class="fa fa-trash-can"></i></button>
                    </form>
                </div>
            </article>
        @endforeach
    </div>

    {{-- Add modal --}}
    <div id="mf-add-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="mf-add-title" aria-hidden="true">
        <div class="pcat-modal__backdrop" data-mf-close tabindex="-1"></div>
        <div class="pcat-modal__panel" style="max-width:min(94vw,520px);">
            <div class="pcat-modal__head">
                <h2 id="mf-add-title">Add filter</h2>
                <button type="button" class="pcat-modal__close" data-mf-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <form method="POST" action="{{ route('mail.filters.store') }}" class="pcat-form-grid">
                    @csrf
                    @include('mail::filters.partials.fields', ['scope' => 'add', 'fields' => $fields, 'actions' => $actions])
                    <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                        <button type="button" class="pcat-btn-del" style="border-color:var(--border);color:var(--muted);background:transparent;" data-mf-close>Cancel</button>
                        <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div id="mf-edit-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="mf-edit-title" aria-hidden="true">
        <div class="pcat-modal__backdrop" data-mf-edit-close tabindex="-1"></div>
        <div class="pcat-modal__panel" style="max-width:min(94vw,520px);">
            <div class="pcat-modal__head">
                <h2 id="mf-edit-title">Edit filter</h2>
                <button type="button" class="pcat-modal__close" data-mf-edit-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <form id="mf-edit-form" method="POST" action="" class="pcat-form-grid">
                    @csrf
                    @method('PUT')
                    @include('mail::filters.partials.fields', ['scope' => 'edit', 'fields' => $fields, 'actions' => $actions])
                    <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                        <button type="button" class="pcat-btn-del" style="border-color:var(--border);color:var(--muted);background:transparent;" data-mf-edit-close>Cancel</button>
                        <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var addModal  = document.getElementById('mf-add-modal');
    var editModal = document.getElementById('mf-edit-modal');
    var addBtn    = document.getElementById('mf-modal-open');

    function lockScroll(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openModal(m) { if (!m) return; m.classList.add('pcat-modal--open'); m.setAttribute('aria-hidden', 'false'); lockScroll(true); }
    function closeModal(m) { if (!m) return; m.classList.remove('pcat-modal--open'); m.setAttribute('aria-hidden', 'true'); lockScroll(false); }

    addBtn && addBtn.addEventListener('click', function () {
        openModal(addModal);
        requestAnimationFrame(function () { document.getElementById('mf-add-value')?.focus(); });
    });

    addModal?.querySelectorAll('[data-mf-close]').forEach(el => el.addEventListener('click', function () { closeModal(addModal); }));
    editModal?.querySelectorAll('[data-mf-edit-close]').forEach(el => el.addEventListener('click', function () { closeModal(editModal); }));

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (addModal?.classList.contains('pcat-modal--open')) closeModal(addModal);
        if (editModal?.classList.contains('pcat-modal--open')) closeModal(editModal);
    });

    window.mfOpenEdit = function (id, field, value, action, isActive) {
        var form = document.getElementById('mf-edit-form');
        if (form) form.action = '{{ url('/mail/filters') }}/' + id;
        document.getElementById('mf-edit-field').value = field;
        document.getElementById('mf-edit-value').value = value;
        document.getElementById('mf-edit-action').value = action;
        document.getElementById('mf-edit-active').checked = Boolean(isActive);
        openModal(editModal);
        requestAnimationFrame(function () { document.getElementById('mf-edit-value')?.focus(); });
    };
})();

(function () {
    var list = document.getElementById('mf-sort-list');
    if (!list || typeof Sortable === 'undefined') return;

    var statusEl = document.getElementById('mf-reorder-status');
    var reorderUrl = @json(route('mail.filters.reorder'));

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function setStatus(text, cls) {
        if (!statusEl) return;
        statusEl.textContent = text || '';
        statusEl.hidden = !text;
        statusEl.classList.remove('is-saving', 'is-error');
        if (cls) statusEl.classList.add(cls);
    }

    function save() {
        var ids = Array.from(list.querySelectorAll('.pcat-card[data-filter-id]'))
            .map(function (el) { return parseInt(el.getAttribute('data-filter-id'), 10); })
            .filter(Boolean);
        if (!ids.length) return;
        setStatus('Saving…', 'is-saving');
        fetch(reorderUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
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
