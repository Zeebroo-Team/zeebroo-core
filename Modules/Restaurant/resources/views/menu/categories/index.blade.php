@extends('theme::layouts.app', ['title' => 'Menu Categories', 'heading' => 'Restaurant'])

@section('content')
@include('product::partials.catalog-hub-styles')

<style>
.mcat-search-bar{display:flex;flex-wrap:wrap;align-items:center;gap:7px;margin-bottom:12px;}
.mcat-search-wrap{position:relative;flex:1 1 200px;min-width:160px;}
.mcat-search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none;}
.mcat-search-input{width:100%;box-sizing:border-box;padding:8px 10px 8px 32px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);outline:none;transition:border-color .15s,box-shadow .15s;}
.mcat-search-input:focus{border-color:var(--primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 18%,transparent);}
.mcat-filter-clear{display:inline-flex;align-items:center;gap:5px;padding:8px 12px;font-size:13px;font-weight:600;border-radius:8px;border:1px solid color-mix(in srgb,#ef4444 35%,var(--border));background:transparent;color:#f97373;text-decoration:none;cursor:pointer;}
.mcat-filter-clear:hover{background:color-mix(in srgb,#ef4444 8%,transparent);}
.mcat-results-table{width:100%;border-collapse:collapse;font-size:13px;}
.mcat-results-table th{text-align:left;padding:9px 12px;background:color-mix(in srgb,var(--card) 92%,transparent);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);}
.mcat-results-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);vertical-align:middle;}
.mcat-results-table tr:last-child td{border-bottom:none;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
  @include('restaurant::partials.nav')

  @if(session('status'))
    <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
  @endif

  <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
    Organise your menu into sections for <strong style="color:var(--text);">{{ $business->name }}</strong>. Drag cards to reorder how categories appear on the POS.
  </p>

  {{-- Search bar (only when categories exist) --}}
  @if($totalCount > 0)
  <form method="GET" action="{{ route('restaurant.menu.categories.index') }}" class="mcat-search-bar" role="search">
    <div class="mcat-search-wrap">
      <i class="fa fa-magnifying-glass mcat-search-icon" aria-hidden="true"></i>
      <input type="search" name="q" value="{{ $search }}" placeholder="Search categories…"
             class="mcat-search-input" autocomplete="off" aria-label="Search categories">
    </div>
    <button type="submit" class="pcat-filter-btn" style="display:inline-flex;align-items:center;gap:5px;padding:8px 14px;font-size:13px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;">
      <i class="fa fa-filter" aria-hidden="true"></i> Filter
    </button>
    @if($isFiltering)
      <a href="{{ route('restaurant.menu.categories.index') }}" class="mcat-filter-clear">
        <i class="fa fa-xmark" aria-hidden="true"></i> Clear
      </a>
    @endif
  </form>
  @endif

  <div class="pcat-toolbar">
    <span class="muted" style="margin:0;font-size:13px;">
      @if($totalCount === 0)
        Add your <strong style="color:var(--text);">first category</strong> below.
      @elseif($isFiltering)
        {{ $filtered->count() }} result{{ $filtered->count() === 1 ? '' : 's' }} of {{ $totalCount }} {{ $totalCount === 1 ? 'category' : 'categories' }}.
      @else
        {{ $totalCount }} {{ $totalCount === 1 ? 'category' : 'categories' }}. Drag cards to reorder.
      @endif
    </span>
    @if($totalCount > 0)
      <span id="mcat-reorder-status" class="pcat-reorder-status" hidden aria-live="polite"></span>
      <button type="button" id="mcat-modal-open" class="linkbtn"
              style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-plus"></i> Add category
      </button>
    @endif
  </div>

  {{-- Empty state: inline create form --}}
  @if($totalCount === 0)
    <section class="pcat-inline">
      <h2>Create category</h2>
      <p class="pcat-muted">Examples: Starters, Main Course, Desserts, Drinks.</p>
      <form method="POST" action="{{ route('restaurant.menu.categories.store') }}"
            class="pcat-form-grid pcat-form-grid--2" style="margin-top:14px;">
        @csrf
        <div class="pcat-field" style="grid-column:1/-1;">
          <label for="new-cat-name">Category name</label>
          <input id="new-cat-name" name="name" value="{{ old('name') }}" maxlength="255" required
                 placeholder="e.g. Main Course" autofocus>
          @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>
        <div class="pcat-field" style="grid-column:1/-1;">
          <label for="new-cat-desc">Description <span class="muted" style="font-weight:400;text-transform:none;">optional</span></label>
          <textarea id="new-cat-desc" name="description" maxlength="1000"
                    placeholder="Short description…">{{ old('description') }}</textarea>
        </div>
        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
          <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save category</button>
        </div>
      </form>
    </section>

  {{-- Search results (flat table) --}}
  @elseif($isFiltering)
    @if($filtered->isEmpty())
      <div style="padding:32px 16px;text-align:center;border:1px dashed var(--border);border-radius:10px;color:var(--muted);">
        <i class="fa fa-magnifying-glass" aria-hidden="true" style="font-size:22px;margin-bottom:10px;display:block;opacity:.45;"></i>
        <p style="margin:0 0 10px;font-size:14px;font-weight:600;">No categories match your search.</p>
        <a href="{{ route('restaurant.menu.categories.index') }}" class="mcat-filter-clear" style="font-size:13px;">
          <i class="fa fa-xmark"></i> Clear filters
        </a>
      </div>
    @else
      <div style="border:1px solid var(--border);border-radius:11px;overflow:auto;margin-bottom:14px;">
        <table class="mcat-results-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th>Items</th>
              <th>Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($filtered as $cat)
            <tr>
              <td><strong>{{ $cat->name }}</strong></td>
              <td><span style="color:var(--muted);font-size:12px;">{{ Str::limit($cat->description ?? '—', 60) }}</span></td>
              <td>{{ $cat->menu_items_count ?? 0 }}</td>
              <td>
                @if($cat->is_active)
                  <span class="pcat-badge pcat-badge--on">Active</span>
                @else
                  <span class="pcat-badge pcat-badge--off">Inactive</span>
                @endif
              </td>
              <td style="text-align:right;">
                <button type="button"
                        onclick="mcatOpenEdit({{ $cat->id }},'{{ addslashes($cat->name) }}','{{ addslashes($cat->description ?? '') }}',{{ $cat->is_active ? 'true' : 'false' }})"
                        style="display:inline-flex;align-items:center;gap:5px;padding:6px 11px;font-size:11.5px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);cursor:pointer;">
                  <i class="fa fa-pen-to-square" aria-hidden="true"></i> Edit
                </button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

  {{-- Sortable card list --}}
  @else
    <div id="mcat-sort-list" class="pcat-list">
      @foreach($categories as $cat)
        <article class="pcat-card pcat-card--parent"
                 data-category-id="{{ $cat->id }}"
                 style="cursor:default;">
          <span class="pcat-drag-handle" title="Drag to reorder" aria-hidden="true">
            <i class="fa fa-grip-vertical"></i>
          </span>
          <div class="pcat-card__body">
            <div class="pcat-card__head">
              <h3 class="pcat-card__title">{{ $cat->name }}</h3>
            </div>
            @if($cat->description)
              <p class="pcat-card__desc muted">{{ Str::limit($cat->description, 80) }}</p>
            @endif
            <div class="pcat-card__meta">
              <span class="muted">{{ $cat->menu_items_count ?? 0 }} item{{ ($cat->menu_items_count ?? 0) !== 1 ? 's' : '' }}</span>
              @if($cat->is_active)
                <span class="pcat-badge pcat-badge--on">Active</span>
              @else
                <span class="pcat-badge pcat-badge--off">Inactive</span>
              @endif
            </div>
          </div>
          <div class="pcat-card__actions">
            <button type="button" class="pcat-link"
                    style="border:none;background:transparent;cursor:pointer;padding:0;font:inherit;"
                    onclick="mcatOpenEdit({{ $cat->id }},'{{ addslashes($cat->name) }}','{{ addslashes($cat->description ?? '') }}',{{ $cat->is_active ? 'true' : 'false' }})">
              <i class="fa fa-pen"></i> Edit
            </button>
            @if(($cat->menu_items_count ?? 0) > 0)
              <span class="muted pcat-card__note">In use</span>
            @else
              <form method="POST" action="{{ route('restaurant.menu.categories.destroy', $cat) }}"
                    style="margin:0;" onsubmit="return confirm('Delete {{ addslashes($cat->name) }}?');">
                @csrf @method('DELETE')
                <button type="submit" class="pcat-btn-del" title="Delete">
                  <i class="fa fa-trash-can"></i>
                </button>
              </form>
            @endif
          </div>
        </article>
      @endforeach
    </div>
  @endif

  {{-- Add / Edit modals (only when categories exist) --}}
  @if($totalCount > 0 || true)
    {{-- Add modal --}}
    <div id="mcat-add-modal" class="pcat-modal" role="dialog" aria-modal="true"
         aria-labelledby="mcat-add-title" aria-hidden="true">
      <div class="pcat-modal__backdrop" data-mcat-close tabindex="-1"></div>
      <div class="pcat-modal__panel">
        <div class="pcat-modal__head">
          <h2 id="mcat-add-title">Add category</h2>
          <button type="button" class="pcat-modal__close" data-mcat-close aria-label="Close">&times;</button>
        </div>
        <div class="pcat-modal__body">
          <form method="POST" action="{{ route('restaurant.menu.categories.store') }}" class="pcat-form-grid">
            @csrf
            <div class="pcat-field">
              <label for="mcat-add-name">Category name</label>
              <input id="mcat-add-name" name="name" value="{{ old('name') }}" maxlength="255" required
                     placeholder="e.g. Starters">
            </div>
            <div class="pcat-field">
              <label for="mcat-add-desc">Description <span class="muted" style="font-weight:400;text-transform:none;">optional</span></label>
              <textarea id="mcat-add-desc" name="description" maxlength="1000"
                        placeholder="Short description…"></textarea>
            </div>
            <div class="pcat-active-row">
              <label class="pcat-active-row__lbl" for="mcat-add-active">Active category</label>
              <label class="pcat-switch">
                <input type="checkbox" id="mcat-add-active" name="is_active" value="1" checked>
                <span class="pcat-switch-slider"></span>
              </label>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
              <button type="button" class="pcat-btn-del"
                      style="border-color:var(--border);color:var(--muted);background:transparent;"
                      data-mcat-close>Cancel</button>
              <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Edit modal --}}
    <div id="mcat-edit-modal" class="pcat-modal" role="dialog" aria-modal="true"
         aria-labelledby="mcat-edit-title" aria-hidden="true">
      <div class="pcat-modal__backdrop" data-mcat-edit-close tabindex="-1"></div>
      <div class="pcat-modal__panel">
        <div class="pcat-modal__head">
          <h2 id="mcat-edit-title">Edit category</h2>
          <button type="button" class="pcat-modal__close" data-mcat-edit-close aria-label="Close">&times;</button>
        </div>
        <div class="pcat-modal__body">
          <form id="mcat-edit-form" method="POST" action="" class="pcat-form-grid">
            @csrf @method('PUT')
            <div class="pcat-field">
              <label for="mcat-edit-name">Category name</label>
              <input id="mcat-edit-name" name="name" maxlength="255" required>
            </div>
            <div class="pcat-field">
              <label for="mcat-edit-desc">Description <span class="muted" style="font-weight:400;text-transform:none;">optional</span></label>
              <textarea id="mcat-edit-desc" name="description" maxlength="1000"></textarea>
            </div>
            <div class="pcat-active-row">
              <label class="pcat-active-row__lbl" for="mcat-edit-active">Active category</label>
              <label class="pcat-switch">
                <input type="checkbox" id="mcat-edit-active" name="is_active" value="1">
                <span class="pcat-switch-slider"></span>
              </label>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
              <button type="button" class="pcat-btn-del"
                      style="border-color:var(--border);color:var(--muted);background:transparent;"
                      data-mcat-edit-close>Cancel</button>
              <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">Save changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif
</div>

@if($categories->isNotEmpty() && !$isFiltering)
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
@endif

<script>
/* ── Modal helpers ── */
(function () {
  var addModal  = document.getElementById('mcat-add-modal');
  var editModal = document.getElementById('mcat-edit-modal');
  var addBtn    = document.getElementById('mcat-modal-open');

  function lockScroll(on) {
    document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on));
  }

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add('pcat-modal--open');
    modal.setAttribute('aria-hidden', 'false');
    lockScroll(true);
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('pcat-modal--open');
    modal.setAttribute('aria-hidden', 'true');
    lockScroll(false);
  }

  addBtn && addBtn.addEventListener('click', function () {
    openModal(addModal);
    requestAnimationFrame(function () {
      var n = document.getElementById('mcat-add-name');
      if (n) n.focus();
    });
  });

  if (addModal) {
    addModal.querySelectorAll('[data-mcat-close]').forEach(function (el) {
      el.addEventListener('click', function () { closeModal(addModal); });
    });
  }

  if (editModal) {
    editModal.querySelectorAll('[data-mcat-edit-close]').forEach(function (el) {
      el.addEventListener('click', function () { closeModal(editModal); });
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (addModal && addModal.classList.contains('pcat-modal--open'))  closeModal(addModal);
    if (editModal && editModal.classList.contains('pcat-modal--open')) closeModal(editModal);
  });

  window.mcatOpenEdit = function (id, name, desc, active) {
    var form = document.getElementById('mcat-edit-form');
    if (form) form.action = '/restaurant/menu/categories/' + id;
    var n = document.getElementById('mcat-edit-name');
    if (n) n.value = name;
    var d = document.getElementById('mcat-edit-desc');
    if (d) d.value = desc;
    var a = document.getElementById('mcat-edit-active');
    if (a) a.checked = active;
    openModal(editModal);
    requestAnimationFrame(function () { if (n) n.focus(); });
  };
})();

/* ── Drag-and-drop reorder ── */
(function () {
  var list = document.getElementById('mcat-sort-list');
  if (!list || typeof Sortable === 'undefined') return;

  var statusEl  = document.getElementById('mcat-reorder-status');
  var reorderUrl = @json(route('restaurant.menu.categories.reorder'));

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
    var ids = Array.from(list.querySelectorAll('.pcat-card[data-category-id]'))
                   .map(function (el) { return parseInt(el.getAttribute('data-category-id'), 10); })
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
