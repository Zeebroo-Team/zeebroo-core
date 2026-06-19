@extends('theme::layouts.app', ['title' => 'Service Categories', 'heading' => 'Service Categories'])

@php
    $modalOpen = $hasItems && $errors->any();
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<style>
.scat-search-bar{display:flex;flex-wrap:wrap;align-items:center;gap:7px;margin-bottom:12px;}
.scat-search-input-wrap{position:relative;flex:1 1 200px;min-width:160px;}
.scat-search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none;}
.scat-search-input{width:100%;box-sizing:border-box;padding:8px 10px 8px 32px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);outline:none;transition:border-color .15s,box-shadow .15s;}
.scat-search-input:focus{border-color:var(--primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 18%,transparent);}
.scat-filter-select{padding:8px 28px 8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;outline:none;transition:border-color .15s;}
.scat-filter-select:focus{border-color:var(--primary);}
.scat-filter-btn{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;font-size:13px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;transition:border-color .15s,background .15s;}
.scat-filter-btn:hover{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
.scat-filter-clear{display:inline-flex;align-items:center;gap:5px;padding:8px 12px;font-size:13px;font-weight:600;border-radius:8px;border:1px solid color-mix(in srgb,#ef4444 35%,var(--border));background:transparent;color:#f97373;text-decoration:none;cursor:pointer;transition:border-color .15s,background .15s;}
.scat-filter-clear:hover{background:color-mix(in srgb,#ef4444 8%,transparent);border-color:color-mix(in srgb,#ef4444 55%,var(--border));}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .scat-filter-clear{color:#dc2626;}
.scat-results-table{width:100%;border-collapse:collapse;font-size:13px;min-width:400px;}
.scat-results-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);}
.scat-results-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);vertical-align:middle;}
.scat-results-table tr:last-child td{border-bottom:none;}
/* inline create form */
.scat-inline{padding:24px 20px;border:1px dashed var(--border);border-radius:11px;margin-bottom:12px;}
.scat-inline h2{margin:0 0 4px;font-size:15px;font-weight:800;}
.scat-field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.scat-field input,.scat-field textarea,.scat-field select{
    width:100%;box-sizing:border-box;padding:9px 11px;border-radius:8px;
    border:1px solid var(--border);background:var(--card);color:var(--text);
    font-size:13px;outline:none;transition:border-color .15s,box-shadow .15s;font-family:inherit;
}
.scat-field input:focus,.scat-field textarea:focus{
    border-color:color-mix(in srgb,var(--primary) 55%,var(--border));
    box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 15%,transparent);
}
.scat-field textarea{min-height:72px;resize:vertical;line-height:1.5;}
.scat-field .scat-err{color:#f87171;font-size:11px;margin-top:3px;}
.scat-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;}
@media(max-width:540px){.scat-form-grid{grid-template-columns:1fr;}}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('service::partials.service-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:12px;">{{ session('status') }}</div>
    @endif
    @if($errors->has('category'))
        <div class="pcat-banner pcat-banner--err" role="alert" style="margin-bottom:12px;">{{ $errors->first('category') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Group services for <strong style="color:var(--text);">{{ $business->name }}</strong> to make them easier to browse and filter.
    </p>

    @if(!$hasItems)
        {{-- ── Inline create (empty state) ── --}}
        <section class="scat-inline">
            <h2>Create your first service category</h2>
            <p class="muted" style="margin:4px 0 18px;font-size:13px;">Examples: Cleaning, Repair, Consultation, Health & Wellness.</p>
            @if($errors->any())
                <div class="pcat-banner pcat-banner--err" style="margin-bottom:12px;" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('service.categories.store') }}" class="scat-form-grid">
                @csrf
                <div class="scat-field">
                    <label for="scat-name-inline">Name <span style="color:#ef4444;">*</span></label>
                    <input id="scat-name-inline" type="text" name="name" value="{{ old('name') }}"
                           placeholder="e.g. Cleaning" required maxlength="255" autocomplete="off">
                    @error('name')<p class="scat-err">{{ $message }}</p>@enderror
                </div>
                <div class="scat-field">
                    <label for="scat-desc-inline">Description</label>
                    <input id="scat-desc-inline" type="text" name="description" value="{{ old('description') }}"
                           placeholder="Optional short description" maxlength="2000">
                </div>
                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:9px 20px;font-size:13px;">
                        <i class="fa fa-plus" aria-hidden="true"></i> Add Category
                    </button>
                </div>
            </form>
        </section>

    @else
        {{-- ── Search & filter bar ── --}}
        @if($totalCount > 0 || $isFiltering)
        <form method="get" action="{{ route('service.categories.index') }}" class="scat-search-bar" role="search">
            <div class="scat-search-input-wrap">
                <i class="fa fa-magnifying-glass scat-search-icon" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ $search }}" placeholder="Search categories…"
                    class="scat-search-input" autocomplete="off" aria-label="Search categories">
            </div>
            <select name="status" class="scat-filter-select" aria-label="Filter by status">
                <option value="">All status</option>
                <option value="active"   @selected($filterStatus === 'active')>Active</option>
                <option value="inactive" @selected($filterStatus === 'inactive')>Inactive</option>
            </select>
            <button type="submit" class="scat-filter-btn"><i class="fa fa-filter" aria-hidden="true"></i> Filter</button>
            @if($isFiltering)
                <a href="{{ route('service.categories.index') }}" class="scat-filter-clear">
                    <i class="fa fa-xmark" aria-hidden="true"></i> Clear
                </a>
            @endif
        </form>
        @endif

        {{-- ── Toolbar ── --}}
        <div class="pcat-toolbar">
            <span class="muted" style="font-size:13px;">
                @if($isFiltering)
                    {{ $categories->count() }} result{{ $categories->count() === 1 ? '' : 's' }} of {{ $totalCount }} {{ $totalCount === 1 ? 'category' : 'categories' }}.
                @else
                    {{ $totalCount }} {{ $totalCount === 1 ? 'category' : 'categories' }}. Drag to reorder.
                @endif
            </span>
            <span id="scat-reorder-status" class="pcat-reorder-status" hidden aria-live="polite"></span>
            <button type="button" id="scat-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus" aria-hidden="true"></i> Add Category
            </button>
        </div>

        @if($isFiltering)
            {{-- ── Flat search results table ── --}}
            @if($categories->isEmpty())
                <div style="padding:32px 16px;text-align:center;border:1px dashed var(--border);border-radius:10px;color:var(--muted);">
                    <i class="fa fa-magnifying-glass" aria-hidden="true" style="font-size:22px;margin-bottom:10px;display:block;opacity:.45;"></i>
                    <p style="margin:0 0 10px;font-size:14px;font-weight:600;">No categories match your filters.</p>
                    <a href="{{ route('service.categories.index') }}" class="scat-filter-clear" style="font-size:13px;"><i class="fa fa-xmark"></i> Clear filters</a>
                </div>
            @else
                <div style="border:1px solid var(--border);border-radius:11px;overflow:auto;margin-bottom:14px;">
                    <table class="scat-results-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Services</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categories as $cat)
                            <tr>
                                <td><strong style="color:var(--text);">{{ $cat->name }}</strong></td>
                                <td class="muted">{{ $cat->description ? \Str::limit($cat->description, 60) : '—' }}</td>
                                <td>{{ $cat->service_items_count }}</td>
                                <td>
                                    @if($cat->is_active)
                                        <span class="pcat-badge pcat-badge--on">Active</span>
                                    @else
                                        <span class="pcat-badge pcat-badge--off">Inactive</span>
                                    @endif
                                </td>
                                <td style="text-align:right;">
                                    <a href="{{ route('service.categories.edit', $cat) }}"
                                       style="display:inline-flex;align-items:center;gap:5px;padding:6px 11px;font-size:11.5px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));text-decoration:none;color:var(--text);background:color-mix(in srgb,var(--primary) 12%,transparent);">
                                        <i class="fa fa-pen-to-square" aria-hidden="true"></i> Edit
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            {{-- ── Draggable card list ── --}}
            <div id="scat-sort-list" class="pcat-list">
                @foreach($categories as $cat)
                <article class="pcat-card" data-category-id="{{ $cat->id }}">
                    <span class="pcat-drag-handle" title="Drag to reorder" aria-hidden="true">
                        <i class="fa fa-grip-vertical"></i>
                    </span>
                    <div class="pcat-card__body">
                        <div class="pcat-card__head">
                            <h3 class="pcat-card__title">{{ $cat->name }}</h3>
                        </div>
                        @if($cat->description)
                            <p class="pcat-card__desc muted">{{ \Str::limit($cat->description, 80) }}</p>
                        @endif
                        <div class="pcat-card__meta">
                            <span class="muted">{{ $cat->service_items_count }} {{ $cat->service_items_count === 1 ? 'service' : 'services' }}</span>
                            @if($cat->is_active)
                                <span class="pcat-badge pcat-badge--on">Active</span>
                            @else
                                <span class="pcat-badge pcat-badge--off">Inactive</span>
                            @endif
                        </div>
                    </div>
                    <div class="pcat-card__actions">
                        <a class="pcat-link" href="{{ route('service.categories.edit', $cat) }}">
                            <i class="fa fa-pen" aria-hidden="true"></i> Edit
                        </a>
                        @if($cat->service_items_count > 0)
                            <span class="muted pcat-card__note">In use</span>
                        @else
                            <form method="post" action="{{ route('service.categories.destroy', $cat) }}"
                                  style="margin:0;" onsubmit="return confirm('Delete \'{{ addslashes($cat->name) }}\'?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="pcat-btn-del" title="Delete">
                                    <i class="fa fa-trash-can" aria-hidden="true"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
                @endforeach
            </div>
        @endif

        {{-- ── Add category modal ── --}}
        <div id="scat-modal" class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}"
             role="dialog" aria-modal="true" aria-labelledby="scat-modal-title"
             aria-hidden="{{ $modalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-scat-close tabindex="-1"></div>
            <div class="pcat-modal__panel">
                <div class="pcat-modal__head">
                    <h2 id="scat-modal-title">Add Service Category</h2>
                    <button type="button" class="pcat-modal__close" data-scat-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @if($errors->any() && $modalOpen)
                        <div class="pcat-banner pcat-banner--err" style="margin-bottom:12px;" role="alert">{{ $errors->first() }}</div>
                    @endif
                    <form method="post" action="{{ route('service.categories.store') }}"
                          style="display:flex;flex-direction:column;gap:12px;">
                        @csrf
                        <div class="scat-field">
                            <label for="scat-modal-name">Name <span style="color:#ef4444;">*</span></label>
                            <input id="scat-modal-name" type="text" name="name"
                                   value="{{ $modalOpen ? old('name') : '' }}"
                                   placeholder="e.g. Repair, Consultation"
                                   required maxlength="255" autocomplete="off">
                            @error('name')<p class="scat-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="scat-field">
                            <label for="scat-modal-desc">Description</label>
                            <textarea id="scat-modal-desc" name="description"
                                      placeholder="Optional description…" rows="3">{{ $modalOpen ? old('description') : '' }}</textarea>
                            @error('description')<p class="scat-err">{{ $message }}</p>@enderror
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1"
                                       {{ old('is_active', '1') ? 'checked' : '' }}
                                       style="width:15px;height:15px;accent-color:var(--primary);">
                                Active
                            </label>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
                            <button type="button" data-scat-close
                                    style="padding:9px 16px;border:1px solid var(--border);border-radius:9px;background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;">
                                Cancel
                            </button>
                            <button type="submit" class="linkbtn" style="padding:9px 20px;font-size:13px;">
                                <i class="fa fa-plus" aria-hidden="true"></i> Add Category
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

@if($hasItems && !$isFiltering)
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
@endif
<script>
(function () {
    // ── Modal open/close ──
    var modal = document.getElementById('scat-modal');
    var openBtn = document.getElementById('scat-modal-open');

    function lock(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openModal() {
        if (!modal) return;
        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lock(true);
        var inp = document.getElementById('scat-modal-name');
        if (inp) window.requestAnimationFrame(function () { inp.focus(); });
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lock(false);
        if (openBtn) openBtn.focus();
    }

    if (openBtn) openBtn.addEventListener('click', openModal);
    if (modal) {
        modal.querySelectorAll('[data-scat-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('pcat-modal--open')) closeModal();
    });
    if (modal && modal.classList.contains('pcat-modal--open')) lock(true);

    // ── Drag-to-reorder ──
    var sortList = document.getElementById('scat-sort-list');
    var statusEl = document.getElementById('scat-reorder-status');
    var reorderUrl = '{{ route('service.categories.reorder') }}';

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value || '';
    }
    function setStatus(text, state) {
        if (!statusEl) return;
        statusEl.textContent = text || '';
        statusEl.hidden = !text;
        statusEl.classList.remove('is-saving', 'is-error');
        if (state) statusEl.classList.add(state);
    }

    if (sortList && typeof Sortable !== 'undefined') {
        Sortable.create(sortList, {
            animation: 150,
            ghostClass: 'pcat-sort-ghost',
            chosenClass: 'pcat-sort-chosen',
            draggable: '.pcat-card',
            handle: '.pcat-drag-handle',
            onEnd: function () {
                var order = Array.from(sortList.querySelectorAll('.pcat-card')).map(function (el) {
                    return parseInt(el.getAttribute('data-category-id'), 10);
                });
                setStatus('Saving…', 'is-saving');
                fetch(reorderUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ order: order }),
                })
                .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, d: d }; }); })
                .then(function (r) {
                    if (r.ok) {
                        setStatus('Saved', 'is-saving');
                        window.setTimeout(function () { setStatus(''); }, 1800);
                    } else {
                        setStatus((r.d && r.d.error) || 'Could not save.', 'is-error');
                    }
                })
                .catch(function () { setStatus('Could not save.', 'is-error'); });
            },
        });
    }
})();
</script>
@endsection
