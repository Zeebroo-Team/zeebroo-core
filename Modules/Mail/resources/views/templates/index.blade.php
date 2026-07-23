@extends('theme::layouts.app', ['title' => 'Mail templates', 'heading' => $business->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.mt-intro{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px;}
.mt-intro__text{max-width:520px;}
.mt-intro__title{font-size:16px;font-weight:800;color:var(--text);margin:0 0 4px;}
.mt-intro__desc{font-size:13px;color:var(--muted);line-height:1.5;margin:0;}
.mt-intro__count{font-size:12px;color:var(--muted);font-weight:600;background:color-mix(in srgb,var(--muted) 12%,transparent);padding:4px 11px;border-radius:999px;white-space:nowrap;}

.mt-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;}

.mt-card{border:1px solid var(--border);border-radius:14px;padding:16px;background:var(--card);display:flex;flex-direction:column;gap:10px;transition:border-color .15s,box-shadow .15s,transform .15s;}
.mt-card:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));box-shadow:0 6px 18px rgba(0,0,0,.07);transform:translateY(-1px);}

.mt-card__head{display:flex;align-items:flex-start;gap:10px;}
.mt-card__icon{width:36px;height:36px;border-radius:10px;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;}
.mt-card__titles{min-width:0;}
.mt-card__name{font-size:14.5px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin:0;}
.mt-card__subject{font-size:12px;color:var(--muted);font-style:italic;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;}

.mt-card__snippet{font-size:12.5px;color:var(--muted);line-height:1.55;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:calc(1.55em * 3);}

.mt-card__footer{display:flex;align-items:center;justify-content:space-between;gap:8px;padding-top:10px;border-top:1px solid var(--border);margin-top:auto;}
.mt-card__meta{font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.mt-card__actions{display:flex;gap:6px;flex-shrink:0;}

.mt-icon-btn{width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--muted);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12.5px;transition:all .15s;}
.mt-icon-btn:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.mt-icon-btn--danger:hover{color:#ef4444;border-color:#ef4444;background:color-mix(in srgb,#ef4444 8%,transparent);}

.mt-empty{grid-column:1/-1;border:1.5px dashed var(--border);border-radius:14px;padding:40px 20px;text-align:center;}
.mt-empty__icon{width:52px;height:52px;border-radius:14px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:20px;margin:0 auto 14px;}
.mt-empty__title{font-size:14px;font-weight:700;color:var(--text);margin:0 0 4px;}
.mt-empty__desc{font-size:12.5px;color:var(--muted);margin:0 0 16px;}

@media (max-width: 520px){
    .mt-grid{grid-template-columns:1fr;}
}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:20px;">
    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <div class="mt-intro">
        <div class="mt-intro__text">
            <h1 class="mt-intro__title">Mail templates</h1>
            <p class="mt-intro__desc">Reusable subject + message pairs you can drop into Compose without retyping them every time.</p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="mt-intro__count">{{ $templates->count() }} {{ $templates->count() === 1 ? 'template' : 'templates' }}</span>
            <button type="button" id="tpl-modal-open" class="linkbtn" style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> Add template
            </button>
        </div>
    </div>

    <div class="mt-grid">
        @forelse($templates as $t)
            <article class="mt-card">
                <div class="mt-card__head">
                    <div class="mt-card__icon"><i class="fa fa-file-lines"></i></div>
                    <div class="mt-card__titles">
                        <h3 class="mt-card__name" title="{{ $t->name }}">{{ $t->name }}</h3>
                        <div class="mt-card__subject" title="{{ $t->subject }}">{{ $t->subject }}</div>
                    </div>
                </div>
                <div class="mt-card__snippet">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $t->body)), 140) }}</div>
                <div class="mt-card__footer">
                    <span class="mt-card__meta">Updated {{ $t->updated_at?->diffForHumans() }}</span>
                    <div class="mt-card__actions">
                        <button type="button" class="mt-icon-btn" title="Edit"
                                onclick="tplOpenEdit({{ $t->id }}, {{ Illuminate\Support\Js::from($t->name) }}, {{ Illuminate\Support\Js::from($t->subject) }}, {{ Illuminate\Support\Js::from($t->body) }})">
                            <i class="fa fa-pen"></i>
                        </button>
                        <form method="POST" action="{{ route('mail.templates.destroy', $t) }}" style="margin:0;" onsubmit="return confirm('Delete {{ addslashes($t->name) }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="mt-icon-btn mt-icon-btn--danger" title="Delete"><i class="fa fa-trash-can"></i></button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="mt-empty">
                <div class="mt-empty__icon"><i class="fa fa-file-circle-plus"></i></div>
                <p class="mt-empty__title">No templates yet</p>
                <p class="mt-empty__desc">Add one to reuse it from Compose without retyping it every time.</p>
                <button type="button" onclick="document.getElementById('tpl-modal-open').click();" class="linkbtn" style="padding:8px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa fa-plus"></i> Add template
                </button>
            </div>
        @endforelse
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
