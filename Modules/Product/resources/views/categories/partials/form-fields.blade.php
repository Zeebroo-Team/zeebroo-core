@php
    $pfx = $fieldIdPrefix ?? '';
    $idName = $pfx !== '' ? $pfx . '-name' : 'pcat-name';
    $idDesc = $pfx !== '' ? $pfx . '-desc' : 'pcat-desc';
    $idSort = $pfx !== '' ? $pfx . '-sort' : 'pcat-sort';
    $idParent = $pfx !== '' ? $pfx . '-parent' : 'pcat-parent';
    $idActive = $pfx !== '' ? $pfx . '-active' : 'pcat-active';
    $row = $category ?? null;
    $parentOptions = $parentOptions ?? collect();
    $selectedParentId = old('parent_id', $row?->parent_id ?? ($presetParentId ?? null));
    $selectedParentId = ($selectedParentId === '' || $selectedParentId === '0') ? null : (int) $selectedParentId;
@endphp
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="{{ $idParent }}">Parent category</label>
    <select id="{{ $idParent }}" name="parent_id">
        <option value="" @selected($selectedParentId === null)>— Top-level category —</option>
        @foreach($parentOptions as $parent)
            <option value="{{ $parent->id }}" @selected((int) $selectedParentId === (int) $parent->id)>{{ str_repeat(' ', (int) ($parent->depth ?? 0)) }}{{ $parent->label ?? $parent->name }}</option>
        @endforeach
    </select>
    <p class="muted" style="margin:6px 0 0;font-size:11px;line-height:1.4;">Choose a parent for a subcategory. You can nest subcategories without limit (e.g. Electronics › Phones › Accessories).</p>
    @error('parent_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="{{ $idName }}">Category name</label>
    <input id="{{ $idName }}" name="name" value="{{ old('name', $row?->name) }}" maxlength="255" required placeholder="e.g. Office supplies">
    @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="{{ $idDesc }}">Description <span class="muted" style="font-weight:400;text-transform:none;">optional</span></label>
    <textarea id="{{ $idDesc }}" name="description" maxlength="5000" placeholder="Grouping notes…">{{ old('description', $row?->description) }}</textarea>
</div>
@if($showSortOrder ?? true)
<div class="pcat-field">
    <label for="{{ $idSort }}">Sort order</label>
    <input id="{{ $idSort }}" type="number" name="sort_order" value="{{ old('sort_order', $row?->sort_order ?? 0) }}" min="0" max="9999" step="1">
    <p class="muted" style="margin:6px 0 0;font-size:11px;line-height:1.4;">On the categories list you can drag rows to reorder within each level.</p>
    @error('sort_order')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
@endif
@include('product::partials.active-toggle', ['toggleId' => $idActive, 'model' => $row, 'label' => 'Active category'])
