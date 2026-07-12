@php $scope = $scope ?? 'add'; @endphp
<div class="pcat-field">
    <label for="mf-{{ $scope }}-field">If</label>
    <select id="mf-{{ $scope }}-field" name="field">
        @foreach($fields as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="pcat-field">
    <label for="mf-{{ $scope }}-value">Contains</label>
    <input type="text" id="mf-{{ $scope }}-value" name="value" maxlength="190" required placeholder="e.g. newsletter@example.com">
    @error('value')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="mf-{{ $scope }}-action">Then</label>
    <select id="mf-{{ $scope }}-action" name="action">
        @foreach($actions as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="pcat-active-row">
    <label class="pcat-active-row__lbl" for="mf-{{ $scope }}-active">Active</label>
    <label class="pcat-switch">
        <input type="checkbox" id="mf-{{ $scope }}-active" name="is_active" value="1" checked>
        <span class="pcat-switch-slider"></span>
    </label>
</div>
