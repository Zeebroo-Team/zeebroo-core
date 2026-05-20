@php
    $row = $supplier ?? null;
    $toggleId = $toggleId ?? 'supplier-active';
    $fieldIdPrefix = $fieldIdPrefix ?? '';
    $activeOld = old('is_active', $row ? ($row->is_active ? '1' : '0') : '1');
@endphp
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="{{ $fieldIdPrefix }}supplier-name">Supplier name</label>
    <input id="{{ $fieldIdPrefix }}supplier-name" name="name" value="{{ old('name', $row?->name) }}" maxlength="255" required placeholder="e.g. ABC Wholesale">
    @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field">
    <label for="{{ $fieldIdPrefix }}supplier-contact">Contact name</label>
    <input id="{{ $fieldIdPrefix }}supplier-contact" name="contact_name" value="{{ old('contact_name', $row?->contact_name) }}" maxlength="255" placeholder="Optional">
</div>
<div class="pcat-field">
    <label for="{{ $fieldIdPrefix }}supplier-phone">Phone</label>
    <input id="{{ $fieldIdPrefix }}supplier-phone" name="phone" value="{{ old('phone', $row?->phone) }}" maxlength="60" placeholder="Optional">
</div>
<div class="pcat-field">
    <label for="supplier-email">Email</label>
    <input id="supplier-email" type="email" name="email" value="{{ old('email', $row?->email) }}" maxlength="255" placeholder="Optional">
</div>
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="{{ $fieldIdPrefix }}supplier-notes">Notes</label>
    <textarea id="{{ $fieldIdPrefix }}supplier-notes" name="notes" maxlength="5000" placeholder="Payment terms, address…">{{ old('notes', $row?->notes) }}</textarea>
</div>
<div class="pcat-field" style="grid-column:1/-1;">
    <span class="muted" style="display:block;margin-bottom:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Status</span>
    <input type="hidden" name="is_active" value="0">
    <div class="pcat-active-row">
        <label for="{{ $toggleId }}" class="pcat-active-row__lbl">Active supplier</label>
        <label class="pcat-switch">
            <input type="checkbox" name="is_active" id="{{ $toggleId }}" value="1" role="switch" @checked($activeOld === '1')>
            <span class="pcat-switch-slider" aria-hidden="true"></span>
        </label>
    </div>
</div>
