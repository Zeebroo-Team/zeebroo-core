@php $scope = $scope ?? 'add'; @endphp
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="tpl-{{ $scope }}-name">Template name</label>
    <input type="text" id="tpl-{{ $scope }}-name" name="name" maxlength="150" required placeholder="e.g. Follow-up after demo">
    @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="tpl-{{ $scope }}-subject">Subject</label>
    <input type="text" id="tpl-{{ $scope }}-subject" name="subject" maxlength="200" required>
    @error('subject')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field" style="grid-column:1/-1;">
    <label for="tpl-{{ $scope }}-body">Message</label>
    <textarea id="tpl-{{ $scope }}-body" name="body" rows="8" required></textarea>
    @error('body')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
