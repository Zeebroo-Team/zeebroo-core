@php
    $toggleId = $toggleId ?? 'pcat-active';
    $activeOld = old('is_active', isset($model) && $model ? ($model->is_active ? '1' : '0') : '1');
@endphp
<div class="pcat-field" style="grid-column:1/-1;">
    <span class="muted" style="display:block;margin-bottom:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Status</span>
    <input type="hidden" name="is_active" value="0">
    <div class="pcat-active-row">
        <label for="{{ $toggleId }}" class="pcat-active-row__lbl">{{ $label ?? 'Active' }}</label>
        <label class="pcat-switch">
            <input type="checkbox" name="is_active" id="{{ $toggleId }}" value="1" role="switch" aria-checked="{{ $activeOld === '1' ? 'true' : 'false' }}" @checked($activeOld === '1')>
            <span class="pcat-switch-slider" aria-hidden="true"></span>
        </label>
    </div>
</div>
