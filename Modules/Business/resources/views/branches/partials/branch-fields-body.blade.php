{{-- Inline branch fields only (wrapped by parent form/grid). Optionally pass fieldIdPrefix for unique IDs in modals/onboarding. --}}
@php
    $requireBranchName = $requireName ?? true;
    $pfxRaw = isset($fieldIdPrefix) ? (string) $fieldIdPrefix : '';
    $idName = $pfxRaw !== '' ? $pfxRaw . '-name' : 'branch-name';
    $idDesc = $pfxRaw !== '' ? $pfxRaw . '-desc' : 'branch-desc';
    $idAddress = $pfxRaw !== '' ? $pfxRaw . '-address' : 'branch-address';
    $idPhone = $pfxRaw !== '' ? $pfxRaw . '-phone' : 'branch-phone';
    $idEmail = $pfxRaw !== '' ? $pfxRaw . '-email' : 'branch-email';
    $idActive = $pfxRaw !== '' ? $pfxRaw . '-active' : 'branch-active';
@endphp
<div class="branch-field" style="grid-column:1/-1;">
    <label for="{{ $idName }}">Branch name</label>
    <input id="{{ $idName }}" name="name" value="{{ old('name') }}" maxlength="255" placeholder="e.g. Colombo HQ"@if($requireBranchName) required @endif>
    @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="branch-field" style="grid-column:1/-1;">
    <label for="{{ $idDesc }}">Description</label>
    <textarea id="{{ $idDesc }}" name="description" maxlength="5000" placeholder="Notes, hours, responsibilities…">{{ old('description') }}</textarea>
</div>
<div class="branch-field" style="grid-column:1/-1;">
    <label for="{{ $idAddress }}">Address</label>
    <textarea id="{{ $idAddress }}" name="address" maxlength="2000" rows="2" placeholder="Street, city, postal code">{{ old('address') }}</textarea>
</div>
<div class="branch-field">
    <label for="{{ $idPhone }}">Phone</label>
    <input id="{{ $idPhone }}" name="phone" value="{{ old('phone') }}" maxlength="40" inputmode="tel" placeholder="+94 …">
</div>
<div class="branch-field">
    <label for="{{ $idEmail }}">Email</label>
    <input id="{{ $idEmail }}" type="email" name="email" value="{{ old('email') }}" maxlength="255" placeholder="branch@example.com">
</div>
<div class="branch-field" style="grid-column:1/-1;">
    <span class="muted" style="display:block;margin-bottom:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Status</span>
    <input type="hidden" name="is_active" value="0">
    <div class="branch-active-row">
        <label for="{{ $idActive }}" class="branch-active-row__lbl">Active branch</label>
        <label class="branch-switch">
            <input type="checkbox" name="is_active" id="{{ $idActive }}" value="1" role="switch" aria-checked="{{ old('is_active', '1') === '1' ? 'true' : 'false' }}" @checked(old('is_active', '1') === '1')>
            <span class="branch-switch-slider" aria-hidden="true"></span>
        </label>
    </div>
</div>
