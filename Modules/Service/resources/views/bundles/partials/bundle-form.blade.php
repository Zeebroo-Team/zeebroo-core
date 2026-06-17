@php
    $isEdit  = isset($bundle) && $bundle instanceof \Modules\Service\Models\ServiceBundle;
    $action  = $isEdit ? route('service.bundles.update', $bundle) : route('service.bundles.store');
    $prefix  = $isEdit ? 'edit' : 'new';
@endphp

<form method="POST" action="{{ $action }}" class="pcat-form-grid pcat-form-grid--2">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="{{ $prefix }}-bname">Bundle name <span style="color:#ef4444;">*</span></label>
        <input id="{{ $prefix }}-bname" type="text" name="name"
               value="{{ old('name', $bundle->name ?? '') }}" maxlength="255" required
               placeholder="e.g. Full Car Service, Starter Package…">
        @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="{{ $prefix }}-bprice">Bundle price{{ isset($currency) && $currency ? ' ('.$currency.')' : '' }} <span style="color:var(--muted);font-weight:400;font-size:11px;">(optional)</span></label>
        <input id="{{ $prefix }}-bprice" type="number" name="price"
               value="{{ old('price', $bundle->price ?? '') }}" min="0" step="0.01"
               inputmode="decimal" placeholder="Leave blank to auto-show total">
        @error('price')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="{{ $prefix }}-bactive">Status</label>
        <select id="{{ $prefix }}-bactive" name="is_active">
            <option value="1" @selected(old('is_active', $bundle->is_active ?? true) == '1')>Active</option>
            <option value="0" @selected(old('is_active', $bundle->is_active ?? true) == '0')>Inactive</option>
        </select>
    </div>

    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="{{ $prefix }}-bdesc">Description <span style="color:var(--muted);font-weight:400;font-size:11px;">(optional)</span></label>
        <textarea id="{{ $prefix }}-bdesc" name="description" maxlength="5000" rows="2"
                  placeholder="What's included in this bundle?">{{ old('description', $bundle->description ?? '') }}</textarea>
        @error('description')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div style="grid-column:1/-1;">
        @include('service::bundles.partials.service-picker', [
            'fieldPrefix' => $prefix,
            'allServices' => $allServices ?? collect(),
            'bundle'      => $bundle ?? null,
        ])
    </div>

    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;margin-top:4px;">
        @if($isEdit)
            <a href="{{ route('service.bundles.show', $bundle) }}" class="linkbtn"
               style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Cancel</a>
        @endif
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">
            {{ $isEdit ? 'Save changes' : 'Create bundle' }}
        </button>
    </div>
</form>
