@php
    $isEdit   = isset($item) && $item instanceof \Modules\Service\Models\ServiceItem;
    $action   = $isEdit ? route('service.catalog.update', $item) : route('service.catalog.store');
    $method   = $isEdit ? 'PUT' : 'POST';
@endphp

<form method="POST" action="{{ $action }}" class="pcat-form-grid pcat-form-grid--2">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="svc-item-name">Service name <span style="color:#ef4444;">*</span></label>
        <input id="svc-item-name" type="text" name="name" value="{{ old('name', $item->name ?? '') }}" maxlength="255" required placeholder="e.g. Oil Change, Web Design, Haircut…">
        @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="svc-item-price">Price{{ isset($currency) && $currency ? ' ('.$currency.')' : '' }}</label>
        <input id="svc-item-price" type="number" name="price" value="{{ old('price', $item->price ?? '') }}" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
        @error('price')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="svc-item-duration">Duration (minutes)</label>
        <input id="svc-item-duration" type="number" name="duration_minutes" value="{{ old('duration_minutes', $item->duration_minutes ?? '') }}" min="1" max="99999" placeholder="e.g. 60">
        @error('duration_minutes')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="svc-item-category">Category</label>
        <input id="svc-item-category" type="text" name="category" value="{{ old('category', $item->category ?? '') }}" maxlength="120" placeholder="e.g. Maintenance, Design…">
        @error('category')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="svc-item-active">Status</label>
        <select id="svc-item-active" name="is_active">
            <option value="1" @selected(old('is_active', $item->is_active ?? true) == '1')>Active</option>
            <option value="0" @selected(old('is_active', $item->is_active ?? true) == '0')>Inactive</option>
        </select>
    </div>

    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="svc-item-description">Description</label>
        <textarea id="svc-item-description" name="description" maxlength="5000" rows="3" placeholder="What does this service include?">{{ old('description', $item->description ?? '') }}</textarea>
        @error('description')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;">
        @if($isEdit)
            <a href="{{ route('service.catalog.show', $item) }}" class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Cancel</a>
        @endif
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">{{ $isEdit ? 'Save changes' : 'Add service' }}</button>
    </div>
</form>
