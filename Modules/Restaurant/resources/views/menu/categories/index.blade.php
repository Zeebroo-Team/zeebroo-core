@extends('theme::layouts.app', ['title' => 'Menu Categories', 'heading' => 'Restaurant'])

@section('content')
<style>
/* ── Page header ── */
.mcat-header { display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:22px; }
.mcat-header__icon { width:48px;height:48px;border-radius:14px;flex-shrink:0;
                      background:linear-gradient(135deg,#8b5cf6,#7c3aed);
                      display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;
                      box-shadow:0 4px 14px color-mix(in srgb,#8b5cf6 25%,transparent); }
.mcat-header__text  { flex:1;min-width:0; }
.mcat-header__title { margin:0 0 2px;font-size:20px;font-weight:900;letter-spacing:-.2px; }
.mcat-header__sub   { margin:0;font-size:12px;color:var(--muted); }

/* ── Card grid ── */
.mcat-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px; }

/* ── Category card ── */
.mcat-card { background:var(--bg);border:1px solid var(--border);border-radius:14px;overflow:hidden;
              display:flex;flex-direction:column;transition:box-shadow .2s,transform .15s; }
.mcat-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.09);transform:translateY(-2px); }
.mcat-card__accent { height:4px;width:100%; }
.mcat-card__body   { padding:16px;flex:1;display:flex;gap:12px;align-items:flex-start; }
.mcat-card__badge  { width:42px;height:42px;border-radius:12px;flex-shrink:0;
                      background:color-mix(in srgb,#8b5cf6 12%,transparent);
                      display:flex;align-items:center;justify-content:center;
                      color:#8b5cf6;font-size:17px; }
.mcat-card__info   { flex:1;min-width:0; }
.mcat-card__name   { font-size:14px;font-weight:900;margin-bottom:3px;
                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.mcat-card__desc   { font-size:12px;color:var(--muted);line-height:1.4;
                      display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
.mcat-card__foot   { display:flex;align-items:center;justify-content:space-between;
                      padding:10px 16px;border-top:1px solid var(--border);
                      background:color-mix(in srgb,var(--border) 15%,var(--bg)); }
.mcat-card__meta   { display:flex;align-items:center;gap:8px; }
.mcat-card__count  { font-size:11px;font-weight:700;color:var(--muted);
                      display:flex;align-items:center;gap:4px; }
.mcat-card__status { font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px; }
.mcat-card__acts   { display:flex;gap:4px; }
.mcat-card__act    { width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:var(--bg);
                      cursor:pointer;display:flex;align-items:center;justify-content:center;
                      font-size:12px;color:var(--muted);transition:all .15s; }
.mcat-card__act:hover     { border-color:var(--primary);color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,var(--bg)); }
.mcat-card__act--del:hover { border-color:#ef4444;color:#ef4444;background:color-mix(in srgb,#ef4444 6%,var(--bg)); }

/* ── Empty state ── */
.mcat-empty { text-align:center;padding:60px 20px;border:2px dashed var(--border);border-radius:16px; }
.mcat-empty__icon  { font-size:38px;color:var(--muted);opacity:.4;margin-bottom:14px; }
.mcat-empty__title { font-size:16px;font-weight:800;margin:0 0 6px; }
.mcat-empty__sub   { font-size:13px;color:var(--muted);margin:0 0 20px; }

/* ── Modal ── */
.mcat-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;
                 display:none;align-items:center;justify-content:center;padding:20px; }
.mcat-overlay.open { display:flex; }
.mcat-modal  { background:var(--bg);border-radius:16px;width:100%;max-width:440px;
                overflow:hidden;box-shadow:0 20px 70px rgba(0,0,0,.22);display:flex;flex-direction:column; }
.mcat-modal__head { display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid var(--border); }
.mcat-modal__head-icon { width:34px;height:34px;border-radius:10px;flex-shrink:0;
                          background:color-mix(in srgb,#8b5cf6 12%,transparent);
                          color:#8b5cf6;display:flex;align-items:center;justify-content:center;font-size:14px; }
.mcat-modal__head h3 { margin:0;font-size:15px;font-weight:800;flex:1; }
.mcat-modal__close { width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:var(--bg);
                      cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:12px; }
.mcat-modal__close:hover { border-color:#ef4444;color:#ef4444; }
.mcat-modal__body { padding:20px; }
.mcat-modal__foot { padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end; }
.mcat-field { margin-bottom:14px; }
.mcat-field label { display:block;font-size:12px;font-weight:700;margin-bottom:5px; }
.mcat-field input,.mcat-field textarea {
  width:100%;padding:9px 12px;border-radius:9px;border:1.5px solid var(--border);
  background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;outline:none;transition:border .15s; }
.mcat-field input:focus,.mcat-field textarea:focus {
  border-color:#8b5cf6;box-shadow:0 0 0 3px color-mix(in srgb,#8b5cf6 10%,transparent); }
.mcat-field textarea { resize:vertical; }
.mcat-toggle { display:flex;align-items:center;gap:9px;padding:10px 12px;border-radius:9px;
                border:1.5px solid var(--border);background:color-mix(in srgb,var(--border) 15%,var(--bg));cursor:pointer; }
.mcat-toggle input { width:15px;height:15px;cursor:pointer;accent-color:#8b5cf6; }
.mcat-toggle span { font-size:13px;font-weight:600; }
.mcat-btn-cancel { padding:9px 20px;border-radius:9px;border:1.5px solid var(--border);background:var(--bg);
                    color:var(--text);font-size:13px;font-weight:700;cursor:pointer; }
.mcat-btn-cancel:hover { border-color:var(--muted); }
.mcat-btn-submit { padding:9px 24px;border-radius:9px;border:none;cursor:pointer;font-size:13px;font-weight:800;
                    color:#fff;background:linear-gradient(135deg,#8b5cf6,#7c3aed); }
</style>

<div style="max-width:100%;">
  @include('restaurant::partials.nav')

  @if(session('status'))
    <div class="pcat-banner pcat-banner--ok" style="margin-bottom:16px;font-weight:600;">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="pcat-banner pcat-banner--err" style="margin-bottom:16px;">{{ $errors->first() }}</div>
  @endif

  {{-- Header --}}
  <div class="mcat-header">
    <div class="mcat-header__icon"><i class="fa fa-layer-group"></i></div>
    <div class="mcat-header__text">
      <h1 class="mcat-header__title">Menu Categories</h1>
      <p class="mcat-header__sub">Organise your menu into sections</p>
    </div>
    <button type="button" class="linkbtn" style="padding:9px 18px;font-size:13px;flex-shrink:0;"
            onclick="document.getElementById('addCatModal').classList.add('open')">
      <i class="fa fa-plus" style="font-size:11px;"></i> New Category
    </button>
  </div>

  {{-- Category cards --}}
  @if($categories->isEmpty())
    <div class="mcat-empty">
      <div class="mcat-empty__icon"><i class="fa fa-layer-group"></i></div>
      <h3 class="mcat-empty__title">No categories yet</h3>
      <p class="mcat-empty__sub">Add categories to organise your menu items into sections.</p>
      <button type="button" class="linkbtn" style="padding:10px 24px;font-size:14px;"
              onclick="document.getElementById('addCatModal').classList.add('open')">
        <i class="fa fa-plus"></i> Add First Category
      </button>
    </div>
  @else
    <div class="mcat-grid">
      @foreach($categories as $cat)
        <div class="mcat-card">
          <div class="mcat-card__accent" style="background:{{ $cat->is_active ? '#8b5cf6' : '#9ca3af' }};"></div>
          <div class="mcat-card__body">
            <div class="mcat-card__badge"><i class="fa fa-layer-group"></i></div>
            <div class="mcat-card__info">
              <div class="mcat-card__name" title="{{ $cat->name }}">{{ $cat->name }}</div>
              @if($cat->description)
                <div class="mcat-card__desc">{{ $cat->description }}</div>
              @else
                <div class="mcat-card__desc" style="opacity:.4;font-style:italic;">No description</div>
              @endif
            </div>
          </div>
          <div class="mcat-card__foot">
            <div class="mcat-card__meta">
              <span class="mcat-card__count">
                <i class="fa fa-utensils" style="font-size:9px;"></i>
                {{ $cat->menu_items_count ?? 0 }} item{{ ($cat->menu_items_count ?? 0) !== 1 ? 's' : '' }}
              </span>
              <span class="mcat-card__status"
                    style="background:{{ $cat->is_active ? 'color-mix(in srgb,#22c55e 12%,transparent)' : 'color-mix(in srgb,#9ca3af 12%,transparent)' }};
                           color:{{ $cat->is_active ? '#16a34a' : '#6b7280' }};">
                {{ $cat->is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
            <div class="mcat-card__acts">
              <button type="button" class="mcat-card__act" title="Edit"
                      onclick="openEditCat({{ $cat->id }},'{{ addslashes($cat->name) }}','{{ addslashes($cat->description ?? '') }}',{{ $cat->is_active ? 'true' : 'false' }})">
                <i class="fa fa-pen"></i>
              </button>
              <form method="POST" action="{{ route('restaurant.menu.categories.destroy', $cat) }}" style="display:contents;"
                    onsubmit="return confirm('Delete {{ addslashes($cat->name) }}? Items will be uncategorised.')">
                @csrf @method('DELETE')
                <button type="submit" class="mcat-card__act mcat-card__act--del" title="Delete">
                  <i class="fa fa-trash"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>

{{-- Add category modal --}}
<div id="addCatModal" class="mcat-overlay" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="mcat-modal">
    <div class="mcat-modal__head">
      <div class="mcat-modal__head-icon"><i class="fa fa-plus"></i></div>
      <h3>New Category</h3>
      <button type="button" class="mcat-modal__close" onclick="document.getElementById('addCatModal').classList.remove('open')">
        <i class="fa fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="{{ route('restaurant.menu.categories.store') }}">
      @csrf
      <div class="mcat-modal__body">
        <div class="mcat-field">
          <label>Name <span style="color:#ef4444;">*</span></label>
          <input type="text" name="name" required maxlength="255" placeholder="e.g. Starters, Main Course, Desserts" autofocus>
        </div>
        <div class="mcat-field">
          <label>Description</label>
          <textarea name="description" rows="2" maxlength="1000" placeholder="Optional short description…"></textarea>
        </div>
      </div>
      <div class="mcat-modal__foot">
        <button type="button" class="mcat-btn-cancel"
                onclick="document.getElementById('addCatModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="mcat-btn-submit">Add Category</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit category modal --}}
<div id="editCatModal" class="mcat-overlay" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="mcat-modal">
    <div class="mcat-modal__head">
      <div class="mcat-modal__head-icon"><i class="fa fa-pen"></i></div>
      <h3>Edit Category</h3>
      <button type="button" class="mcat-modal__close" onclick="document.getElementById('editCatModal').classList.remove('open')">
        <i class="fa fa-xmark"></i>
      </button>
    </div>
    <form id="editCatForm" method="POST" action="">
      @csrf @method('PUT')
      <div class="mcat-modal__body">
        <div class="mcat-field">
          <label>Name <span style="color:#ef4444;">*</span></label>
          <input type="text" id="editCatName" name="name" required maxlength="255">
        </div>
        <div class="mcat-field">
          <label>Description</label>
          <textarea id="editCatDesc" name="description" rows="2" maxlength="1000"></textarea>
        </div>
        <label class="mcat-toggle">
          <input type="checkbox" id="editCatActive" name="is_active" value="1">
          <span>Category is Active</span>
        </label>
      </div>
      <div class="mcat-modal__foot">
        <button type="button" class="mcat-btn-cancel"
                onclick="document.getElementById('editCatModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="mcat-btn-submit">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditCat(id, name, desc, active) {
  document.getElementById('editCatForm').action = '/restaurant/menu/categories/' + id;
  document.getElementById('editCatName').value   = name;
  document.getElementById('editCatDesc').value   = desc;
  document.getElementById('editCatActive').checked = active;
  document.getElementById('editCatModal').classList.add('open');
}
</script>
@endsection
