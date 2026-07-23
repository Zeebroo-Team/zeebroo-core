@extends('theme::layouts.app', ['title' => 'Mail filters', 'heading' => $business->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.mf-intro{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px;}
.mf-intro__text{max-width:520px;}
.mf-intro__title{font-size:16px;font-weight:800;color:var(--text);margin:0 0 4px;}
.mf-intro__desc{font-size:13px;color:var(--muted);line-height:1.5;margin:0;}
.mf-intro__count{font-size:12px;color:var(--muted);font-weight:600;background:color-mix(in srgb,var(--muted) 12%,transparent);padding:4px 11px;border-radius:999px;white-space:nowrap;}

.mf-list{display:flex;flex-direction:column;gap:10px;}
.mf-card{border:1px solid var(--border);border-radius:14px;padding:14px 16px;background:var(--card);display:flex;align-items:center;gap:12px;transition:border-color .15s,box-shadow .15s;}
.mf-card:hover{border-color:color-mix(in srgb,var(--primary) 30%,var(--border));box-shadow:0 4px 14px rgba(0,0,0,.05);}
.mf-card--inactive{opacity:.55;}
.mf-card__handle{color:var(--muted);cursor:grab;font-size:14px;padding:4px;flex-shrink:0;}
.mf-card__handle:active{cursor:grabbing;}
.mf-card__icon{width:38px;height:38px;border-radius:10px;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;}
.mf-card__body{flex:1;min-width:0;}
.mf-card__rule{font-size:13.5px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mf-card__rule .mf-quiet{color:var(--muted);font-weight:500;}
.mf-card__rule .mf-value{color:var(--primary);}
.mf-card__meta{display:flex;align-items:center;gap:8px;margin-top:6px;}
.mf-rule-badge{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;padding:3px 9px;border-radius:999px;border:1px solid var(--border);color:var(--muted);text-transform:uppercase;letter-spacing:.03em;}
.mf-card__actions{display:flex;gap:6px;flex-shrink:0;}

.mf-icon-btn{width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--muted);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12.5px;transition:all .15s;}
.mf-icon-btn:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.mf-icon-btn--danger:hover{color:#ef4444;border-color:#ef4444;background:color-mix(in srgb,#ef4444 8%,transparent);}

.mf-empty{border:1.5px dashed var(--border);border-radius:14px;padding:40px 20px;text-align:center;}
.mf-empty__icon{width:52px;height:52px;border-radius:14px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:20px;margin:0 auto 14px;}
.mf-empty__title{font-size:14px;font-weight:700;color:var(--text);margin:0 0 4px;}
.mf-empty__desc{font-size:12.5px;color:var(--muted);margin:0 0 16px;}

.mf-sort-ghost{opacity:.4;}
.mf-sort-chosen{box-shadow:0 6px 18px rgba(0,0,0,.1);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:20px;">
    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <div class="mf-intro">
        <div class="mf-intro__text">
            <h1 class="mf-intro__title">Mail filters</h1>
            <p class="mf-intro__desc">Automatically process incoming mail as it syncs. The first matching filter (top to bottom) wins — drag to reorder.</p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="mf-intro__count">{{ $filters->count() }} {{ $filters->count() === 1 ? 'filter' : 'filters' }}</span>
            <span id="mf-reorder-status" class="pcat-reorder-status" hidden aria-live="polite"></span>
            <button type="button" id="mf-modal-open" class="linkbtn" style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> Add filter
            </button>
        </div>
    </div>

    @if($filters->isEmpty())
        <div class="mf-empty">
            <div class="mf-empty__icon"><i class="fa fa-filter-circle-xmark"></i></div>
            <p class="mf-empty__title">No filters yet</p>
            <p class="mf-empty__desc">Incoming mail is stored as-is until you add a rule.</p>
            <button type="button" onclick="document.getElementById('mf-modal-open').click();" class="linkbtn" style="padding:8px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> Add filter
            </button>
        </div>
    @else
        <div id="mf-sort-list" class="mf-list">
            @foreach($filters as $f)
                <article class="mf-card {{ $f->is_active ? '' : 'mf-card--inactive' }}" data-filter-id="{{ $f->id }}">
                    <span class="mf-card__handle pcat-drag-handle" title="Drag to reorder" aria-hidden="true"><i class="fa fa-grip-vertical"></i></span>
                    <div class="mf-card__icon"><i class="fa {{ $f->field === 'subject' ? 'fa-heading' : 'fa-at' }}"></i></div>
                    <div class="mf-card__body">
                        <div class="mf-card__rule">
                            <span class="mf-quiet">If {{ strtolower($f->fieldLabel()) }} contains</span>
                            "<span class="mf-value">{{ $f->value }}</span>"
                        </div>
                        <div class="mf-card__meta">
                            <span class="mf-rule-badge"><i class="fa fa-bolt"></i> {{ $f->actionLabel() }}</span>
                            @if(!$f->is_active)
                                <span class="pcat-badge">Inactive</span>
                            @endif
                        </div>
                    </div>
                    <div class="mf-card__actions">
                        <button type="button" class="mf-icon-btn" title="Edit"
                                onclick="mfOpenEdit({{ $f->id }}, {{ Illuminate\Support\Js::from($f->field) }}, {{ Illuminate\Support\Js::from($f->value) }}, {{ Illuminate\Support\Js::from($f->action) }}, {{ $f->is_active ? 'true' : 'false' }})">
                            <i class="fa fa-pen"></i>
                        </button>
                        <form method="POST" action="{{ route('mail.filters.destroy', $f) }}" style="margin:0;" onsubmit="return confirm('Delete this filter?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="mf-icon-btn mf-icon-btn--danger" title="Delete"><i class="fa fa-trash-can"></i></button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

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
        var ids = Array.from(list.querySelectorAll('.mf-card[data-filter-id]'))
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
        ghostClass: 'mf-sort-ghost',
        chosenClass: 'mf-sort-chosen',
        draggable: '.mf-card',
        onEnd: save,
    });
})();
</script>
@endsection
