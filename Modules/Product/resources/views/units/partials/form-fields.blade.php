@php
    $pfx = $fieldIdPrefix ?? '';
    $idName = $pfx !== '' ? $pfx . '-name' : 'punit-name';
    $idAbbr = $pfx !== '' ? $pfx . '-abbr' : 'punit-abbr';
    $idSort = $pfx !== '' ? $pfx . '-sort' : 'punit-sort';
    $idActive = $pfx !== '' ? $pfx . '-active' : 'punit-active';
    $row = $unit ?? null;
@endphp
<div class="pcat-field">
    <label for="{{ $idName }}">Unit name</label>
    <input id="{{ $idName }}" name="name" value="{{ old('name', $row?->name) }}" maxlength="40" required placeholder="e.g. Piece">
    @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field">
    <label for="{{ $idAbbr }}">Abbreviation <span class="muted" style="font-weight:400;text-transform:none;">optional</span></label>
    <input id="{{ $idAbbr }}" name="abbreviation" value="{{ old('abbreviation', $row?->abbreviation) }}" maxlength="20" placeholder="pcs">
    @error('abbreviation')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field">
    <label for="{{ $idSort }}">Sort order</label>
    <input id="{{ $idSort }}" type="number" name="sort_order" value="{{ old('sort_order', $row?->sort_order ?? 0) }}" min="0" max="9999" step="1">
</div>
@include('product::partials.active-toggle', ['toggleId' => $idActive, 'model' => $row, 'label' => 'Active unit'])
