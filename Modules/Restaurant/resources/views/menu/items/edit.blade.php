@extends('theme::layouts.app', ['title' => 'Edit '.$item->name, 'heading' => 'Restaurant'])

@section('content')
@php
  $tagMeta = [
    'vegetarian'  => ['label'=>'Vegetarian',  'color'=>'#16a34a'],
    'vegan'        => ['label'=>'Vegan',        'color'=>'#15803d'],
    'gluten_free'  => ['label'=>'Gluten Free',  'color'=>'#b45309'],
    'halal'        => ['label'=>'Halal',        'color'=>'#0891b2'],
    'spicy'        => ['label'=>'Spicy',        'color'=>'#dc2626'],
    'nut_free'     => ['label'=>'Nut Free',     'color'=>'#d97706'],
    'dairy_free'   => ['label'=>'Dairy Free',   'color'=>'#7c3aed'],
  ];
@endphp
<style>
.mi-edit-wrap { max-width:680px; }
.mi-edit-card { background:var(--bg);border:1px solid var(--border);border-radius:16px;overflow:hidden; }
.mi-edit-head { padding:18px 24px;border-bottom:1px solid var(--border);
                 display:flex;align-items:center;gap:12px;
                 background:color-mix(in srgb,var(--primary) 3%,var(--bg)); }
.mi-edit-head__icon { width:40px;height:40px;border-radius:12px;flex-shrink:0;
                       background:color-mix(in srgb,var(--primary) 12%,transparent);
                       color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:16px; }
.mi-edit-head__text h2 { margin:0 0 2px;font-size:16px;font-weight:900; }
.mi-edit-head__text p  { margin:0;font-size:12px;color:var(--muted); }
.mi-edit-body { padding:24px; }
.mi-field { margin-bottom:16px; }
.mi-field label { display:block;font-size:12px;font-weight:700;margin-bottom:5px;color:var(--text);
                   text-transform:uppercase;letter-spacing:.3px; }
.mi-field input,.mi-field select,.mi-field textarea {
  width:100%;padding:10px 13px;border-radius:10px;border:1.5px solid var(--border);
  background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;outline:none;transition:border .15s; }
.mi-field input:focus,.mi-field select:focus,.mi-field textarea:focus {
  border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 10%,transparent); }
.mi-field textarea { resize:vertical; }
.mi-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
.mi-full { grid-column:1/-1; }
.mi-tag-pills { display:flex;flex-wrap:wrap;gap:6px; }
.mi-tag-label { display:flex;align-items:center;gap:5px;cursor:pointer; }
.mi-tag-label input { display:none; }
.mi-tag-label span { padding:5px 13px;border-radius:999px;font-size:11px;font-weight:700;
                      border:1.5px solid var(--border);color:var(--muted);transition:all .15s; }
.mi-tag-label input:checked + span { border-color:var(--primary);color:var(--primary);
  background:color-mix(in srgb,var(--primary) 10%,var(--bg)); }
.mi-toggle { display:flex;align-items:center;gap:9px;padding:11px 13px;border-radius:10px;
              border:1.5px solid var(--border);background:color-mix(in srgb,var(--border) 15%,var(--bg));cursor:pointer; }
.mi-toggle input { width:16px;height:16px;cursor:pointer;accent-color:var(--primary); }
.mi-toggle span { font-size:13px;font-weight:600; }
.mi-edit-foot { padding:16px 24px;border-top:1px solid var(--border);
                 display:flex;align-items:center;justify-content:space-between;gap:10px;
                 background:color-mix(in srgb,var(--border) 10%,var(--bg)); }
</style>

<div class="mi-edit-wrap">
  @include('restaurant::partials.nav')

  @if($errors->any())
    <div class="pcat-banner pcat-banner--err" style="margin-bottom:16px;">{{ $errors->first() }}</div>
  @endif

  {{-- Back link --}}
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
    <a href="{{ route('restaurant.menu.items.index') }}"
       style="display:flex;align-items:center;gap:6px;padding:7px 12px;border-radius:9px;
              border:1px solid var(--border);color:var(--muted);text-decoration:none;font-size:13px;">
      <i class="fa fa-arrow-left"></i>
    </a>
    <div>
      <span style="font-size:12px;color:var(--muted);">Menu /</span>
      <span style="font-size:12px;font-weight:700;"> {{ $item->name }}</span>
    </div>
  </div>

  <div class="mi-edit-card">
    <div class="mi-edit-head">
      <div class="mi-edit-head__icon"><i class="fa fa-pen-to-square"></i></div>
      <div class="mi-edit-head__text">
        <h2>Edit Menu Item</h2>
        <p>{{ $item->name }}</p>
      </div>
    </div>

    <form method="POST" action="{{ route('restaurant.menu.items.update', $item) }}">
      @csrf @method('PUT')
      <div class="mi-edit-body">
        <div class="mi-grid">
          <div class="mi-field mi-full">
            <label>Item Name <span style="color:#ef4444;">*</span></label>
            <input type="text" name="name" value="{{ old('name', $item->name) }}" required maxlength="255">
          </div>
          <div class="mi-field">
            <label>Category</label>
            <select name="menu_category_id">
              <option value="">— None —</option>
              @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ old('menu_category_id', $item->menu_category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mi-field">
            <label>Price{{ $currency ? ' ('.$currency.')' : '' }} <span style="color:#ef4444;">*</span></label>
            <input type="number" name="price" value="{{ old('price', $item->price) }}" required min="0" step="0.01">
          </div>
          <div class="mi-field mi-full">
            <label>Description</label>
            <textarea name="description" rows="3" maxlength="3000">{{ old('description', $item->description) }}</textarea>
          </div>
          <div class="mi-field">
            <label>Prep Time (minutes)</label>
            <input type="number" name="prep_time_minutes" value="{{ old('prep_time_minutes', $item->prep_time_minutes) }}" min="1" max="9999">
          </div>
          <div class="mi-field" style="display:flex;align-items:flex-end;">
            <label class="mi-toggle" style="width:100%;margin:0;">
              <input type="checkbox" name="is_available" value="1"
                     {{ old('is_available', $item->is_available) ? 'checked' : '' }}>
              <span>Available for ordering</span>
            </label>
          </div>
          <div class="mi-field mi-full">
            <label>Dietary Tags</label>
            <div class="mi-tag-pills">
              @foreach(['vegetarian','vegan','gluten_free','halal','spicy','nut_free','dairy_free'] as $tag)
                <label class="mi-tag-label">
                  <input type="checkbox" name="dietary_tags[]" value="{{ $tag }}"
                         {{ in_array($tag, (array)old('dietary_tags', $item->dietary_tags ?? [])) ? 'checked' : '' }}>
                  <span>{{ str_replace('_',' ',ucfirst($tag)) }}</span>
                </label>
              @endforeach
            </div>
          </div>
        </div>
      </div>
      <div class="mi-edit-foot">
        <a href="{{ route('restaurant.menu.items.show', $item) }}"
           style="padding:9px 18px;border-radius:9px;border:1.5px solid var(--border);color:var(--text);
                  text-decoration:none;font-size:13px;font-weight:600;">Cancel</a>
        <button type="submit" class="linkbtn" style="padding:10px 28px;font-size:13px;font-weight:800;">
          <i class="fa fa-floppy-disk" style="font-size:12px;"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
