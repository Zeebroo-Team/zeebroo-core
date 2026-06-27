@extends('theme::layouts.app', ['title' => 'Edit '.$item->name, 'heading' => 'Restaurant'])

@section('content')
@php
  $item->load('ingredients');
  $allIngredients = \Modules\Restaurant\Models\Ingredient::where('business_id', $business->id)->orderBy('name')->get();
  $recipeRows     = $item->ingredients;
  $activeTab      = old('_tab', request('tab', 'details'));
@endphp
<style>
/* ── Fields ── */
.mie-field { margin-bottom:12px; }
.mie-field label { display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px; }
.mie-field input,.mie-field select,.mie-field textarea {
  width:100%;box-sizing:border-box;padding:9px 11px;font-size:13px;border-radius:8px;
  border:1px solid var(--border);background:var(--bg);color:var(--text);outline:none;transition:border-color .15s; }
.mie-field input:focus,.mie-field select:focus,.mie-field textarea:focus {
  border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 10%,transparent); }
.mie-field textarea { min-height:80px;resize:vertical;font-family:inherit;line-height:1.5; }
.mie-grid  { display:grid;grid-template-columns:1fr 1fr;gap:12px 16px; }
.mie-full  { grid-column:1/-1; }

/* ── Toggle switch (identical to product edit page) ── */
.product-active-row{display:flex;align-items:center;justify-content:space-between;gap:14px;width:100%;padding:11px 14px;box-sizing:border-box;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);}
.product-active-row__lbl{margin:0;font-size:13px;font-weight:600;color:var(--text);cursor:pointer;}
.product-switch{position:relative;display:inline-block;width:46px;height:26px;flex-shrink:0;}
.product-switch input{opacity:0;width:0;height:0;margin:0;position:absolute;}
.product-switch-slider{position:absolute;inset:0;cursor:pointer;background:#475569;border-radius:999px;transition:.2s;}
.product-switch-slider:before{content:"";position:absolute;height:20px;width:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.22);}
.product-switch input:checked + .product-switch-slider{background:#22c55e;}
.product-switch input:checked + .product-switch-slider:before{transform:translateX(20px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .product-switch-slider{background:color-mix(in srgb,#475569 75%,var(--border));}
.product-switch input:focus-visible + .product-switch-slider{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 45%,transparent);}

/* ── Tag pills ── */
.mie-tag-pills { display:flex;flex-wrap:wrap;gap:6px; }
.mie-tag-lbl   { display:flex;align-items:center;gap:5px;cursor:pointer; }
.mie-tag-lbl input { display:none; }
.mie-tag-lbl span { padding:5px 12px;border-radius:999px;font-size:11px;font-weight:700;
                     border:1px solid var(--border);color:var(--muted);transition:all .15s; }
.mie-tag-lbl input:checked + span { border-color:var(--primary);color:var(--primary);
  background:color-mix(in srgb,var(--primary) 10%,var(--bg)); }

/* ── Tabs (reuse ps- pattern) ── */
.ps-tabs { display:flex;flex-wrap:wrap;gap:4px;margin:0 0 18px;padding:4px;border-radius:11px;
            border:1px solid var(--border);background:color-mix(in srgb,var(--bg) 92%,var(--border) 8%);width:fit-content; }
.ps-tab  { display:inline-flex;align-items:center;gap:5px;padding:6px 13px;font-size:12px;font-weight:700;
            color:var(--muted);text-decoration:none;border-radius:8px;border:1px solid transparent;
            background:transparent;transition:all .15s;white-space:nowrap;cursor:pointer; }
.ps-tab:hover { color:var(--text); }
.ps-tab.is-active { color:var(--text);background:var(--bg);border-color:var(--border);box-shadow:0 1px 4px rgba(0,0,0,.08); }
.ps-tab__count { font-size:9px;font-weight:700;padding:1px 5px;border-radius:999px;
                  background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);
                  border:1px solid color-mix(in srgb,var(--primary) 25%,transparent); }
.ps-panel[hidden] { display:none!important; }

/* ── Section label ── */
.ps-label { font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);
             margin:0 0 10px;display:flex;align-items:center;gap:6px; }
.ps-label::after { content:'';flex:1;height:1px;background:var(--border); }

/* ── Recipe row ── */
.rcp-row { display:grid;grid-template-columns:1fr 140px 34px;gap:10px;margin-bottom:10px;align-items:end; }
.rcp-del { width:34px;height:38px;border-radius:8px;border:1px solid #fca5a5;background:transparent;
            color:#ef4444;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center; }
.rcp-del:hover { background:#fef2f2; }
.rcp-add { display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;
            border:1.5px dashed var(--border);background:transparent;color:var(--muted);
            cursor:pointer;font-size:13px;font-weight:600;margin-top:4px; }
.rcp-add:hover { border-color:var(--primary);color:var(--primary); }
</style>

<div class="card" style="max-width:100%;padding:16px;">
  @include('restaurant::partials.nav')

  @if(session('status'))
    <div class="pcat-banner pcat-banner--ok" style="margin-bottom:14px;font-weight:600;">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="pcat-banner pcat-banner--err" style="margin-bottom:14px;">{{ $errors->first() }}</div>
  @endif

  {{-- Header --}}
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
    <a href="{{ route('restaurant.menu.items.show', $item) }}"
       style="display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:8px;
              border:1px solid var(--border);color:var(--muted);text-decoration:none;font-size:12px;">
      <i class="fa fa-arrow-left"></i>
    </a>
    <div>
      <div style="font-size:11px;color:var(--muted);">
        <a href="{{ route('restaurant.menu.items.index') }}" style="color:var(--muted);text-decoration:none;">Menu</a>
        &rsaquo;
        <a href="{{ route('restaurant.menu.items.show', $item) }}" style="color:var(--muted);text-decoration:none;">{{ $item->name }}</a>
        &rsaquo; Edit
      </div>
    </div>
  </div>

  {{-- Tab bar --}}
  <nav class="ps-tabs">
    <button type="button" class="ps-tab {{ $activeTab === 'details' ? 'is-active' : '' }}"
            onclick="switchTab('details')">
      <i class="fa fa-pen-to-square"></i> Details
    </button>
    <button type="button" class="ps-tab {{ $activeTab === 'recipe' ? 'is-active' : '' }}"
            onclick="switchTab('recipe')">
      <i class="fa fa-flask"></i> Recipe
      @if($recipeRows->count())
        <span class="ps-tab__count">{{ $recipeRows->count() }}</span>
      @endif
    </button>
  </nav>

  {{-- ── Details tab ── --}}
  <section id="tab-details" class="ps-panel" @if($activeTab !== 'details') hidden @endif>
    <form method="POST" action="{{ route('restaurant.menu.items.update', $item) }}">
      @csrf @method('PUT')
      <input type="hidden" name="_tab" value="details">

      <div class="mie-grid">

        <div class="mie-field mie-full">
          <label>Item Name <span style="color:#ef4444;">*</span></label>
          <input type="text" name="name" value="{{ old('name', $item->name) }}" required maxlength="255" placeholder="e.g. Grilled Chicken">
        </div>

        <div class="mie-field mie-full">
          @include('restaurant::menu.items.partials.category-tags-field', [
              'fieldIdPrefix' => 'edit',
              'item'          => $item,
              'categories'    => $categories,
          ])
        </div>

        <div class="mie-field">
          <label>Price{{ $currency ? ' ('.$currency.')' : '' }} <span style="color:#ef4444;">*</span></label>
          <input type="number" name="price" value="{{ old('price', $item->price) }}" required min="0" step="0.01" placeholder="0.00">
        </div>

        <div class="mie-field mie-full">
          <label>Description</label>
          <textarea name="description" maxlength="3000" placeholder="Short description of the dish…">{{ old('description', $item->description) }}</textarea>
        </div>

        <div class="mie-field">
          <label>Prep Time (minutes)</label>
          <input type="number" name="prep_time_minutes" value="{{ old('prep_time_minutes', $item->prep_time_minutes) }}" min="1" max="9999" placeholder="e.g. 15">
        </div>

        <div class="mie-field" style="display:flex;align-items:flex-end;">
          <div class="product-active-row" style="width:100%;">
            <label class="product-active-row__lbl" for="is_available_toggle">Available for ordering</label>
            <label class="product-switch">
              <input type="checkbox" id="is_available_toggle" name="is_available" value="1"
                     {{ old('is_available', $item->is_available) ? 'checked' : '' }}>
              <span class="product-switch-slider"></span>
            </label>
          </div>
        </div>

        <div class="mie-field mie-full">
          <label>Dietary Tags</label>
          <div class="mie-tag-pills">
            @foreach(['vegetarian','vegan','gluten_free','halal','spicy','nut_free','dairy_free'] as $tag)
              <label class="mie-tag-lbl">
                <input type="checkbox" name="dietary_tags[]" value="{{ $tag }}"
                       {{ in_array($tag, (array)old('dietary_tags', $item->dietary_tags ?? [])) ? 'checked' : '' }}>
                <span>{{ str_replace('_', ' ', ucfirst($tag)) }}</span>
              </label>
            @endforeach
          </div>
        </div>

        <div class="mie-field mie-full">
          <label>Item Image</label>
          @include('restaurant::menu.items.partials.image-field', [
              'fileId'   => old('file_manager_file_id', $item->file_manager_file_id),
              'fileUrl'  => $item->imageFile?->publicUrl(),
              'fileName' => $item->imageFile?->original_filename,
              'fieldKey' => 'edit',
          ])
        </div>

      </div>

      <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:18px;padding-top:16px;border-top:1px solid var(--border);">
        <a href="{{ route('restaurant.menu.items.show', $item) }}"
           class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
          Cancel
        </a>
        <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">
          <i class="fa fa-floppy-disk" style="font-size:11px;"></i> Save Changes
        </button>
      </div>
    </form>
  </section>

  {{-- ── Recipe tab ── --}}
  <section id="tab-recipe" class="ps-panel" @if($activeTab !== 'recipe') hidden @endif>

    @if($allIngredients->isEmpty())
      <div style="text-align:center;padding:40px 20px;border:1.5px dashed var(--border);border-radius:10px;color:var(--muted);">
        <i class="fa fa-flask" style="font-size:28px;margin-bottom:10px;display:block;opacity:.4;"></i>
        <p style="margin:0 0 12px;font-size:13px;">No ingredients in your system yet.</p>
        <a href="{{ route('restaurant.ingredients.index') }}"
           class="linkbtn" style="padding:7px 16px;font-size:13px;text-decoration:none;">
          <i class="fa fa-plus" style="font-size:11px;"></i> Add Ingredients
        </a>
      </div>
    @else
      <p class="ps-label"><i class="fa fa-flask"></i> Recipe Ingredients</p>
      <p style="font-size:12px;color:var(--muted);margin:0 0 16px;">
        Define how much of each ingredient is used per serving. Stock will be deducted automatically when the order is served.
      </p>

      <form method="POST" action="{{ route('restaurant.menu.items.recipe', $item) }}">
        @csrf
        <input type="hidden" name="_tab" value="recipe">

        <div id="recipeRows">
          @foreach($recipeRows as $ri)
          <div class="rcp-row">
            <div class="mie-field" style="margin:0;">
              @if($loop->first)<label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;">Ingredient</label>@endif
              <select name="recipe[{{ $loop->index }}][ingredient_id]" required
                style="width:100%;box-sizing:border-box;padding:9px 11px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);">
                @foreach($allIngredients as $ing)
                  <option value="{{ $ing->id }}" {{ $ri->id == $ing->id ? 'selected' : '' }}>
                    {{ $ing->name }} ({{ strtoupper($ing->unit) }})
                  </option>
                @endforeach
              </select>
            </div>
            <div class="mie-field" style="margin:0;">
              @if($loop->first)<label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;">Qty / Serving</label>@endif
              <input type="number" name="recipe[{{ $loop->index }}][quantity_required]"
                value="{{ $ri->pivot->quantity_required }}" min="0.001" step="any" required
                style="width:100%;box-sizing:border-box;padding:9px 11px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);">
            </div>
            <div style="{{ $loop->first ? 'padding-top:22px;' : '' }}">
              <button type="button" class="rcp-del" onclick="this.closest('.rcp-row').remove()">
                <i class="fa fa-xmark"></i>
              </button>
            </div>
          </div>
          @endforeach
        </div>

        <button type="button" class="rcp-add" onclick="addRecipeRow()">
          <i class="fa fa-plus"></i> Add Ingredient
        </button>

        <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
          <a href="{{ route('restaurant.menu.items.show', ['menuItem' => $item, 'tab' => 'ingredients']) }}"
             class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
            Cancel
          </a>
          <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">
            <i class="fa fa-floppy-disk" style="font-size:11px;"></i> Save Recipe
          </button>
        </div>
      </form>
    @endif
  </section>

</div>

<script>
var _allIngredients = @json($allIngredients->map(fn($i) => ['id' => $i->id, 'name' => $i->name, 'unit' => strtoupper($i->unit)]));
var _rcpIdx = {{ $recipeRows->count() }};

function switchTab(tab) {
    document.querySelectorAll('.ps-tab').forEach(function(b) { b.classList.remove('is-active'); });
    document.querySelectorAll('.ps-panel').forEach(function(p) { p.hidden = true; });
    document.querySelector('.ps-tab[onclick*="' + tab + '"]').classList.add('is-active');
    document.getElementById('tab-' + tab).hidden = false;
}

function addRecipeRow() {
    var idx  = _rcpIdx++;
    var hasRows = document.querySelectorAll('#recipeRows .rcp-row').length > 0;
    var opts = _allIngredients.map(function(i) {
        return '<option value="' + i.id + '">' + i.name + ' (' + i.unit + ')</option>';
    }).join('');

    var fldStyle = 'width:100%;box-sizing:border-box;padding:9px 11px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);';

    var row = document.createElement('div');
    row.className = 'rcp-row';
    row.innerHTML =
        '<div class="mie-field" style="margin:0;">'
        + '<select name="recipe[' + idx + '][ingredient_id]" required style="' + fldStyle + '">' + opts + '</select>'
        + '</div>'
        + '<div class="mie-field" style="margin:0;">'
        + '<input type="number" name="recipe[' + idx + '][quantity_required]" min="0.001" step="any" required placeholder="0" style="' + fldStyle + '">'
        + '</div>'
        + '<div>'
        + '<button type="button" class="rcp-del" onclick="this.closest(\'.rcp-row\').remove()"><i class="fa fa-xmark"></i></button>'
        + '</div>';
    document.getElementById('recipeRows').appendChild(row);
}
</script>
@endsection
