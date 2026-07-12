@extends('theme::layouts.app', ['title' => 'Mail templates', 'heading' => $business->name])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:720px;padding:14px;">
    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Reusable subject + message pairs you can drop into Compose without retyping them every time.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            {{ $templates->count() }} {{ $templates->count() === 1 ? 'template' : 'templates' }}.
        </span>
        <button type="button" id="tpl-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-plus"></i> Add template
        </button>
    </div>

    @if($templates->isEmpty())
        <p class="muted" style="margin:24px 0;font-size:13px;">No templates yet — add one to reuse it from Compose.</p>
    @endif

    <div class="pcat-list">
        @foreach($templates as $t)
            <article class="pcat-card" style="cursor:default;">
                <div class="pcat-card__body">
                    <div class="pcat-card__head">
                        <h3 class="pcat-card__title">{{ $t->name }}</h3>
                    </div>
                    <div class="pcat-card__meta">
                        <span class="muted">{{ $t->subject }}</span>
                    </div>
                </div>
                <div class="pcat-card__actions">
                    <button type="button"
                            style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);cursor:pointer;"
                            onclick="tplOpenEdit({{ $t->id }}, {{ Illuminate\Support\Js::from($t->name) }}, {{ Illuminate\Support\Js::from($t->subject) }}, {{ Illuminate\Support\Js::from($t->body) }})">
                        <i class="fa fa-pen"></i> Edit
                    </button>
                    <form method="POST" action="{{ route('mail.templates.destroy', $t) }}" style="margin:0;" onsubmit="return confirm('Delete {{ addslashes($t->name) }}?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="pcat-btn-del" title="Delete"><i class="fa fa-trash-can"></i></button>
                    </form>
                </div>
            </article>
        @endforeach
    </div>

    {{-- Add modal --}}
    <div id="tpl-add-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="tpl-add-title" aria-hidden="true">
        <div class="pcat-modal__backdrop" data-tpl-close tabindex="-1"></div>
        <div class="pcat-modal__panel" style="max-width:min(94vw,560px);">
            <div class="pcat-modal__head">
                <h2 id="tpl-add-title">Add template</h2>
                <button type="button" class="pcat-modal__close" data-tpl-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <form method="POST" action="{{ route('mail.templates.store') }}" class="pcat-form-grid">
                    @csrf
                    @include('mail::templates.partials.fields', ['scope' => 'add'])
                    <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                        <button type="button" class="pcat-btn-del" style="border-color:var(--border);color:var(--muted);background:transparent;" data-tpl-close>Cancel</button>
                        <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div id="tpl-edit-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="tpl-edit-title" aria-hidden="true">
        <div class="pcat-modal__backdrop" data-tpl-edit-close tabindex="-1"></div>
        <div class="pcat-modal__panel" style="max-width:min(94vw,560px);">
            <div class="pcat-modal__head">
                <h2 id="tpl-edit-title">Edit template</h2>
                <button type="button" class="pcat-modal__close" data-tpl-edit-close aria-label="Close">&times;</button>
            </div>
            <div class="pcat-modal__body">
                <form id="tpl-edit-form" method="POST" action="" class="pcat-form-grid">
                    @csrf
                    @method('PUT')
                    @include('mail::templates.partials.fields', ['scope' => 'edit'])
                    <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                        <button type="button" class="pcat-btn-del" style="border-color:var(--border);color:var(--muted);background:transparent;" data-tpl-edit-close>Cancel</button>
                        <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var addModal  = document.getElementById('tpl-add-modal');
    var editModal = document.getElementById('tpl-edit-modal');
    var addBtn    = document.getElementById('tpl-modal-open');

    function lockScroll(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openModal(m) { if (!m) return; m.classList.add('pcat-modal--open'); m.setAttribute('aria-hidden', 'false'); lockScroll(true); }
    function closeModal(m) { if (!m) return; m.classList.remove('pcat-modal--open'); m.setAttribute('aria-hidden', 'true'); lockScroll(false); }

    addBtn && addBtn.addEventListener('click', function () {
        openModal(addModal);
        requestAnimationFrame(function () { document.getElementById('tpl-add-name')?.focus(); });
    });

    addModal?.querySelectorAll('[data-tpl-close]').forEach(el => el.addEventListener('click', function () { closeModal(addModal); }));
    editModal?.querySelectorAll('[data-tpl-edit-close]').forEach(el => el.addEventListener('click', function () { closeModal(editModal); }));

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (addModal?.classList.contains('pcat-modal--open')) closeModal(addModal);
        if (editModal?.classList.contains('pcat-modal--open')) closeModal(editModal);
    });

    window.tplOpenEdit = function (id, name, subject, body) {
        var form = document.getElementById('tpl-edit-form');
        if (form) form.action = '{{ url('/mail/templates') }}/' + id;
        document.getElementById('tpl-edit-name').value = name;
        document.getElementById('tpl-edit-subject').value = subject;
        document.getElementById('tpl-edit-body').value = body;
        openModal(editModal);
        requestAnimationFrame(function () { document.getElementById('tpl-edit-name')?.focus(); });
    };
})();
</script>
@endsection
