@php
    $pfx = $fieldIdPrefix ?? '';
    $idName = $pfx !== '' ? $pfx . '-name' : 'pbrand-name';
    $idDesc = $pfx !== '' ? $pfx . '-desc' : 'pbrand-desc';
    $idWeb = $pfx !== '' ? $pfx . '-web' : 'pbrand-web';
    $idActive = $pfx !== '' ? $pfx . '-active' : 'pbrand-active';
    $row = $brand ?? null;
@endphp
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="{{ $idName }}">Brand name</label>
    <input id="{{ $idName }}" name="name" value="{{ old('name', $row?->name) }}" maxlength="255" required placeholder="e.g. Acme Co.">
    @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="{{ $idDesc }}">Description <span class="muted" style="font-weight:400;text-transform:none;">optional</span></label>
    <textarea id="{{ $idDesc }}" name="description" maxlength="5000">{{ old('description', $row?->description) }}</textarea>
</div>
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="{{ $idWeb }}">Website <span class="muted" style="font-weight:400;text-transform:none;">optional</span></label>
    <input id="{{ $idWeb }}" type="url" name="website" value="{{ old('website', $row?->website) }}" maxlength="500" placeholder="https://">
    @error('website')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
@include('product::partials.active-toggle', ['toggleId' => $idActive, 'model' => $row, 'label' => 'Active brand'])
