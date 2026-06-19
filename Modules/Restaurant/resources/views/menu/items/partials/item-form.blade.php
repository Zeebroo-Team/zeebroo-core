@php $editing = isset($item) && $item !== null; @endphp
<form method="POST"
      action="{{ $editing ? route('restaurant.menu.items.update', $item) : route('restaurant.menu.items.store') }}"
      style="display:flex;flex-direction:column;gap:12px;">
    @csrf
    @if($editing) @method('PUT') @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div style="grid-column:1/-1;">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Name <span style="color:#ef4444;">*</span></label>
            <input type="text" name="name" value="{{ old('name', $item?->name) }}" required maxlength="255"
                   style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Category</label>
            <select name="menu_category_id"
                    style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
                <option value="">— None —</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ old('menu_category_id', $item?->menu_category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Price{{ $currency ? ' ('.$currency.')' : '' }} <span style="color:#ef4444;">*</span></label>
            <input type="number" name="price" value="{{ old('price', $item?->price) }}" required min="0" step="0.01"
                   style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Prep time (minutes)</label>
            <input type="number" name="prep_time_minutes" value="{{ old('prep_time_minutes', $item?->prep_time_minutes) }}" min="1" max="9999"
                   style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
        </div>
        <div style="grid-column:1/-1;">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Description</label>
            <textarea name="description" rows="2" maxlength="3000"
                      style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;resize:vertical;box-sizing:border-box;">{{ old('description', $item?->description) }}</textarea>
        </div>
        <div style="grid-column:1/-1;">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Dietary tags</label>
            @php
                $allTags     = ['vegetarian','vegan','gluten_free','halal','spicy','nut_free','dairy_free'];
                $selectedTags = old('dietary_tags', $item?->dietary_tags ?? []);
            @endphp
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                @foreach($allTags as $tag)
                    <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;">
                        <input type="checkbox" name="dietary_tags[]" value="{{ $tag }}"
                               {{ in_array($tag, (array)$selectedTags) ? 'checked' : '' }}>
                        {{ str_replace('_', ' ', ucfirst($tag)) }}
                    </label>
                @endforeach
            </div>
        </div>
        @if($editing)
        <div style="grid-column:1/-1;display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="is_available" value="1" id="itemAvail" {{ old('is_available', $item?->is_available ?? true) ? 'checked' : '' }}>
            <label for="itemAvail" style="font-size:13px;">Available</label>
        </div>
        @endif
    </div>

    <div style="display:flex;gap:8px;justify-content:flex-end;padding-top:4px;">
        @if(!$editing)
            <button type="reset" style="padding:8px 18px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:13px;cursor:pointer;">Reset</button>
        @endif
        <button type="submit" class="linkbtn" style="padding:8px 22px;font-size:13px;">
            {{ $editing ? 'Update item' : 'Add to menu' }}
        </button>
    </div>
</form>
