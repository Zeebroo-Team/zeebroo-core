@extends('theme::layouts.app', ['title' => 'Menu', 'heading' => 'Restaurant'])

@section('content')
@php
  $tagMeta = [
    'vegetarian'  => ['label'=>'Vegetarian',   'color'=>'#16a34a', 'icon'=>'fa-leaf'],
    'vegan'        => ['label'=>'Vegan',         'color'=>'#15803d', 'icon'=>'fa-seedling'],
    'gluten_free'  => ['label'=>'Gluten Free',   'color'=>'#b45309', 'icon'=>'fa-wheat-awn'],
    'halal'        => ['label'=>'Halal',         'color'=>'#0891b2', 'icon'=>'fa-star-and-crescent'],
    'spicy'        => ['label'=>'Spicy',         'color'=>'#dc2626', 'icon'=>'fa-pepper-hot'],
    'nut_free'     => ['label'=>'Nut Free',      'color'=>'#d97706', 'icon'=>'fa-ban'],
    'dairy_free'   => ['label'=>'Dairy Free',    'color'=>'#7c3aed', 'icon'=>'fa-droplet'],
  ];
@endphp

<style>
.mn-wrap { max-width:100%; }

/* ── Page header ── */
.mn-header { display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:22px; }
.mn-header__icon { width:48px;height:48px;border-radius:14px;flex-shrink:0;
                   background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 70%,#000));
                   display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;
                   box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 25%,transparent); }
.mn-header__text { flex:1;min-width:0; }
.mn-header__title { margin:0 0 2px;font-size:20px;font-weight:900;letter-spacing:-.2px; }
.mn-header__sub   { margin:0;font-size:12px;color:var(--muted); }
.mn-header__actions { display:flex;gap:8px;flex-shrink:0; }

/* ── Stats row ── */
.mn-stats { display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap; }
.mn-stat  { flex:1;min-width:100px;background:var(--bg);border:1px solid var(--border);
             border-radius:12px;padding:12px 16px;display:flex;flex-direction:column;gap:2px; }
.mn-stat__val   { font-size:22px;font-weight:900;line-height:1; }
.mn-stat__label { font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px; }

/* ── Toolbar ── */
.mn-toolbar { display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px; }
.mn-search   { display:flex;gap:6px;flex:1;min-width:180px; }
.mn-search input { flex:1;padding:8px 12px;border-radius:9px;border:1px solid var(--border);
                    background:var(--bg);color:var(--text);font-size:13px;outline:none; }
.mn-search input:focus { border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 12%,transparent); }

/* ── Filter pills ── */
.mn-pills { display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px;align-items:center; }
.mn-pill  { padding:5px 14px;border-radius:999px;font-size:12px;font-weight:700;border:1.5px solid var(--border);
             text-decoration:none;color:var(--muted);background:var(--bg);transition:all .15s;cursor:pointer;
             white-space:nowrap;display:inline-flex;align-items:center;gap:5px; }
.mn-pill:hover    { border-color:var(--primary);color:var(--primary); }
.mn-pill--active  { background:var(--text);color:var(--bg) !important;border-color:var(--text); }
.mn-pill--cat     { }
.mn-pills-divider { width:1px;height:20px;background:var(--border);flex-shrink:0;margin:0 2px; }

/* ── List ── */
.mn-list { background:var(--bg);border:1px solid var(--border);border-radius:14px;overflow:hidden; }
.mn-row  { display:flex;align-items:center;gap:14px;padding:11px 16px;border-bottom:1px solid var(--border); }
.mn-row:last-child { border-bottom:none; }
.mn-row:hover { background:color-mix(in srgb,var(--primary) 3%,var(--bg)); }

/* thumbnail */
.mn-row__thumb { width:44px;height:44px;border-radius:10px;flex-shrink:0;overflow:hidden;
                  background:color-mix(in srgb,var(--border) 30%,var(--bg));
                  display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:16px; }
.mn-row__thumb img { width:100%;height:100%;object-fit:cover; }

/* info */
.mn-row__info { flex:1;min-width:0; }
.mn-row__name { font-size:13px;font-weight:800;text-decoration:none;color:var(--text);
                 white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block; }
.mn-row__name:hover { color:var(--primary); }
.mn-row__meta { display:flex;align-items:center;gap:6px;margin-top:2px;flex-wrap:wrap; }
.mn-row__cat  { font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;
                 background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary); }
.mn-row__tag  { font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;
                 display:inline-flex;align-items:center;gap:3px; }
.mn-row__prep { font-size:10px;color:var(--muted);font-weight:600;
                 display:inline-flex;align-items:center;gap:3px; }

/* price */
.mn-row__price { font-size:15px;font-weight:900;color:var(--primary);letter-spacing:-.3px;white-space:nowrap; }

/* status */
.mn-row__status { font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;white-space:nowrap; }

/* actions */
.mn-row__acts { display:flex;gap:4px;flex-shrink:0; }
.mn-card__act { width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:var(--bg);
                 cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;
                 color:var(--muted);text-decoration:none;transition:all .15s; }
.mn-card__act:hover { border-color:var(--primary);color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,var(--bg)); }
.mn-card__act--del:hover { border-color:#ef4444;color:#ef4444;background:color-mix(in srgb,#ef4444 6%,var(--bg)); }

/* ── Empty state ── */
.mn-empty { text-align:center;padding:60px 20px;border:2px dashed var(--border);border-radius:16px; }
.mn-empty__icon { font-size:40px;color:var(--muted);opacity:.4;margin-bottom:14px; }
.mn-empty__title { font-size:16px;font-weight:800;margin:0 0 6px; }
.mn-empty__sub   { font-size:13px;color:var(--muted);margin:0 0 20px; }

/* ── Add item modal ── */
.mn-modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;
                     display:none;align-items:center;justify-content:center;padding:20px; }
.mn-modal-overlay.open { display:flex; }
.mn-modal { background:var(--bg);border-radius:16px;width:100%;max-width:580px;
             max-height:90vh;overflow:hidden;display:flex;flex-direction:column;
             box-shadow:0 20px 70px rgba(0,0,0,.22); }
.mn-modal__head { display:flex;align-items:center;gap:12px;padding:16px 20px;
                   border-bottom:1px solid var(--border);flex-shrink:0; }
.mn-modal__head-icon { width:36px;height:36px;border-radius:10px;flex-shrink:0;
                        background:color-mix(in srgb,var(--primary) 12%,transparent);
                        color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:15px; }
.mn-modal__head h3 { margin:0;font-size:15px;font-weight:800;flex:1; }
.mn-modal__close { width:32px;height:32px;border-radius:8px;border:1px solid var(--border);
                    background:var(--bg);cursor:pointer;display:flex;align-items:center;
                    justify-content:center;color:var(--muted);font-size:13px; }
.mn-modal__close:hover { border-color:#ef4444;color:#ef4444; }
.mn-modal__body { flex:1;overflow-y:auto;padding:20px; }

/* ── Form fields ── */
.mn-field { margin-bottom:14px; }
.mn-field label { display:block;font-size:12px;font-weight:700;margin-bottom:5px;color:var(--text); }
.mn-field input,.mn-field select,.mn-field textarea {
  width:100%;padding:9px 12px;border-radius:9px;border:1.5px solid var(--border);
  background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;outline:none;transition:border .15s; }
.mn-field input:focus,.mn-field select:focus,.mn-field textarea:focus {
  border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 10%,transparent); }
.mn-field textarea { resize:vertical; }
.mn-field-grid { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
.mn-field-full { grid-column:1/-1; }
.mn-tag-pills { display:flex;flex-wrap:wrap;gap:6px; }
.mn-tag-pill-label { display:flex;align-items:center;gap:5px;cursor:pointer; }
.mn-tag-pill-label input { display:none; }
.mn-tag-pill-label span { padding:5px 12px;border-radius:999px;font-size:11px;font-weight:700;
                           border:1.5px solid var(--border);color:var(--muted);transition:all .15s; }
.mn-tag-pill-label input:checked + span { border-color:var(--primary);color:var(--primary);
  background:color-mix(in srgb,var(--primary) 10%,var(--bg)); }
.mn-modal__foot { padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;flex-shrink:0; }
.mn-btn-cancel { padding:9px 20px;border-radius:9px;border:1.5px solid var(--border);background:var(--bg);
                  color:var(--text);font-size:13px;font-weight:700;cursor:pointer; }
.mn-btn-cancel:hover { border-color:var(--muted); }
.mn-btn-submit { padding:9px 24px;border-radius:9px;border:none;cursor:pointer;font-size:13px;font-weight:800;
                  color:#fff;background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 70%,#000)); }
</style>

<div class="mn-wrap">
  @include('restaurant::partials.nav')

  @if(session('status'))
    <div class="pcat-banner pcat-banner--ok" style="margin-bottom:16px;font-weight:600;">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="pcat-banner pcat-banner--err" style="margin-bottom:16px;">{{ $errors->first() }}</div>
  @endif

  {{-- Page header --}}
  <div class="mn-header">
    <div class="mn-header__icon"><i class="fa fa-utensils"></i></div>
    <div class="mn-header__text">
      <h1 class="mn-header__title">Menu</h1>
      <p class="mn-header__sub">Manage your dishes, drinks, and offerings</p>
    </div>
    <div class="mn-header__actions">
      <button type="button" class="linkbtn" style="padding:9px 18px;font-size:13px;"
              onclick="document.getElementById('addItemModal').classList.add('open')">
        <i class="fa fa-plus" style="font-size:11px;"></i> New Item
      </button>
    </div>
  </div>

  @if(!$hasItems)
    {{-- First-run empty state with inline form --}}
    <div class="mn-empty">
      <div class="mn-empty__icon"><i class="fa fa-utensils"></i></div>
      <h3 class="mn-empty__title">Build your menu</h3>
      <p class="mn-empty__sub">Add your first dish, drink, or special to get started.</p>
      <button type="button" class="linkbtn" style="padding:10px 24px;font-size:14px;"
              onclick="document.getElementById('addItemModal').classList.add('open')">
        <i class="fa fa-plus"></i> Add First Item
      </button>
    </div>

  @else

    {{-- Stats row --}}
    @php
      $totalItems     = $items->total();
      $availableCount = \Modules\Restaurant\Models\MenuItem::where('business_id', $business->id)->where('is_available', true)->count();
      $catCount       = $categories->count();
    @endphp
    <div class="mn-stats">
      <div class="mn-stat">
        <span class="mn-stat__val" style="color:var(--primary);">{{ $totalItems }}</span>
        <span class="mn-stat__label">Total Items</span>
      </div>
      <div class="mn-stat">
        <span class="mn-stat__val" style="color:#22c55e;">{{ $availableCount }}</span>
        <span class="mn-stat__label">Available</span>
      </div>
      <div class="mn-stat">
        <span class="mn-stat__val" style="color:#8b5cf6;">{{ $catCount }}</span>
        <span class="mn-stat__label">Categories</span>
      </div>
    </div>

    {{-- Toolbar --}}
    <div class="mn-toolbar">
      <form method="GET" action="{{ route('restaurant.menu.items.index') }}" class="mn-search">
        @if($categoryId)<input type="hidden" name="category" value="{{ $categoryId }}">@endif
        <input type="text" name="q" value="{{ $search }}" placeholder="Search menu items…">
        <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:13px;">
          <i class="fa fa-magnifying-glass" style="font-size:11px;"></i>
        </button>
        @if($search || $categoryId)
          <a href="{{ route('restaurant.menu.items.index') }}"
             style="padding:8px 12px;border-radius:9px;border:1.5px solid var(--border);color:var(--muted);
                    text-decoration:none;font-size:13px;display:flex;align-items:center;gap:4px;">
            <i class="fa fa-xmark" style="font-size:11px;"></i> Clear
          </a>
        @endif
      </form>
    </div>

    {{-- Filter pills: status + categories --}}
    <div class="mn-pills">
      {{-- Status --}}
      <a href="{{ route('restaurant.menu.items.index', array_merge(request()->except('status','page'), ['category'=>$categoryId,'q'=>$search])) }}"
         class="mn-pill {{ (!$status || $status==='all') ? 'mn-pill--active' : '' }}">All</a>
      <a href="{{ route('restaurant.menu.items.index', array_merge(request()->except('status','page'), ['status'=>'available','q'=>$search,'category'=>$categoryId])) }}"
         class="mn-pill {{ $status==='available' ? 'mn-pill--active' : '' }}">
        <i class="fa fa-circle-check" style="font-size:9px;color:#22c55e;"></i> Available
      </a>
      <a href="{{ route('restaurant.menu.items.index', array_merge(request()->except('status','page'), ['status'=>'unavailable','q'=>$search,'category'=>$categoryId])) }}"
         class="mn-pill {{ $status==='unavailable' ? 'mn-pill--active' : '' }}">
        <i class="fa fa-circle-xmark" style="font-size:9px;color:#9ca3af;"></i> Unavailable
      </a>

      @if($categories->isNotEmpty())
        <div class="mn-pills-divider"></div>
        @foreach($categories as $cat)
          <a href="{{ route('restaurant.menu.items.index', array_merge(request()->except('category','page'), ['category'=>$cat->id,'q'=>$search,'status'=>$status])) }}"
             class="mn-pill mn-pill--cat {{ $categoryId===$cat->id ? 'mn-pill--active' : '' }}">
            {{ $cat->name }}
          </a>
        @endforeach
      @endif
    </div>

    {{-- List --}}
    @if($items->isEmpty())
      <div class="mn-empty">
        <div class="mn-empty__icon"><i class="fa fa-magnifying-glass"></i></div>
        <h3 class="mn-empty__title">No items found</h3>
        <p class="mn-empty__sub">Try adjusting your search or filters.</p>
      </div>
    @else
      <div class="mn-list">
        @foreach($items as $item)
          @php
            $available = $item->is_available;
            $tags = (array) ($item->dietary_tags ?? []);
          @endphp
          <div class="mn-row">

            {{-- Thumbnail --}}
            <div class="mn-row__thumb">
              @if($item->imageFile)
                <img src="{{ $item->imageFile->publicUrl() }}" alt="{{ $item->name }}">
              @else
                <i class="fa fa-utensils"></i>
              @endif
            </div>

            {{-- Name + meta --}}
            <div class="mn-row__info">
              <a href="{{ route('restaurant.menu.items.show', $item) }}" class="mn-row__name">{{ $item->name }}</a>
              <div class="mn-row__meta">
                @foreach($item->categories as $cat)
                  <span class="mn-row__cat">{{ $cat->name }}</span>
                @endforeach
                @foreach($tags as $tag)
                  @if(isset($tagMeta[$tag]))
                    @php $tm = $tagMeta[$tag]; @endphp
                    <span class="mn-row__tag"
                          style="background:color-mix(in srgb,{{ $tm['color'] }} 10%,transparent);color:{{ $tm['color'] }};">
                      <i class="fa {{ $tm['icon'] }}" style="font-size:8px;"></i>{{ $tm['label'] }}
                    </span>
                  @endif
                @endforeach
                @if($item->prep_time_minutes)
                  <span class="mn-row__prep"><i class="fa fa-clock" style="font-size:9px;"></i>{{ $item->prepLabel() }}</span>
                @endif
              </div>
            </div>

            {{-- Price --}}
            <span class="mn-row__price">{{ $currency }}{{ number_format((float)$item->price, 2) }}</span>

            {{-- Status --}}
            <span class="mn-row__status"
                  style="background:{{ $available ? 'color-mix(in srgb,#22c55e 12%,transparent)' : 'color-mix(in srgb,#9ca3af 12%,transparent)' }};
                         color:{{ $available ? '#16a34a' : '#6b7280' }};">
              <i class="fa {{ $available ? 'fa-circle-check' : 'fa-circle-xmark' }}" style="font-size:9px;margin-right:3px;"></i>
              {{ $available ? 'Available' : 'Unavailable' }}
            </span>

            {{-- Actions --}}
            <div class="mn-row__acts">
              <a href="{{ route('restaurant.menu.items.edit', $item) }}" class="mn-card__act" title="Edit">
                <i class="fa fa-pen"></i>
              </a>
              <form method="POST" action="{{ route('restaurant.menu.items.destroy', $item) }}" style="display:contents;"
                    onsubmit="return confirm('Delete {{ addslashes($item->name) }}?')">
                @csrf @method('DELETE')
                <button type="submit" class="mn-card__act mn-card__act--del" title="Delete">
                  <i class="fa fa-trash"></i>
                </button>
              </form>
            </div>

          </div>
        @endforeach
      </div>

      <div style="margin-top:20px;">{{ $items->withQueryString()->links() }}</div>
    @endif

  @endif
</div>

{{-- Add item modal --}}
<div id="addItemModal" class="mn-modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="mn-modal">
    <div class="mn-modal__head">
      <div class="mn-modal__head-icon"><i class="fa fa-utensils"></i></div>
      <h3>New Menu Item</h3>
      <button type="button" class="mn-modal__close" onclick="document.getElementById('addItemModal').classList.remove('open')">
        <i class="fa fa-xmark"></i>
      </button>
    </div>
    <div class="mn-modal__body">
      <form method="POST" action="{{ route('restaurant.menu.items.store') }}">
        @csrf
        <div class="mn-field-grid">
          <div class="mn-field mn-field-full">
            <label>Name <span style="color:#ef4444;">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="e.g. Grilled Chicken">
          </div>
          <div class="mn-field mn-field-full">
            @include('restaurant::menu.items.partials.category-tags-field', [
                'fieldIdPrefix' => 'create',
                'item'          => null,
                'categories'    => $categories,
            ])
          </div>
          <div class="mn-field">
            <label>Price{{ $currency ? ' ('.$currency.')' : '' }} <span style="color:#ef4444;">*</span></label>
            <input type="number" name="price" value="{{ old('price') }}" required min="0" step="0.01" placeholder="0.00">
          </div>
          <div class="mn-field mn-field-full">
            <label>Description</label>
            <textarea name="description" rows="2" maxlength="3000" placeholder="Short description of the dish…">{{ old('description') }}</textarea>
          </div>
          <div class="mn-field">
            <label>Prep time (minutes)</label>
            <input type="number" name="prep_time_minutes" value="{{ old('prep_time_minutes') }}" min="1" max="9999" placeholder="e.g. 15">
          </div>
          <div class="mn-field mn-field-full">
            <label>Dietary tags</label>
            <div class="mn-tag-pills">
              @foreach(['vegetarian','vegan','gluten_free','halal','spicy','nut_free','dairy_free'] as $tag)
                <label class="mn-tag-pill-label">
                  <input type="checkbox" name="dietary_tags[]" value="{{ $tag }}"
                         {{ in_array($tag, (array)old('dietary_tags',[])) ? 'checked' : '' }}>
                  <span>{{ str_replace('_',' ',ucfirst($tag)) }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div class="mn-field mn-field-full">
            <label>Item image</label>
            @include('restaurant::menu.items.partials.image-field', [
                'fileId'   => old('file_manager_file_id'),
                'fileUrl'  => null,
                'fileName' => null,
                'fieldKey' => 'create',
            ])
          </div>
        </div>
        <div class="mn-modal__foot" style="padding:0;border:none;margin-top:4px;">
          <button type="button" class="mn-btn-cancel"
                  onclick="document.getElementById('addItemModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="mn-btn-submit">Add to Menu</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection
