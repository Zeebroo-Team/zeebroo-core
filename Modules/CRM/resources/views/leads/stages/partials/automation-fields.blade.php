@php $scope = $scope ?? 'add'; @endphp
<div class="pcat-field">
    <label for="auto-{{ $scope }}-recipient">Send to</label>
    <select id="auto-{{ $scope }}-recipient" name="recipient_type" data-auto-recipient-select="{{ $scope }}">
        @foreach($recipientTypes as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
    </select>
    @error('recipient_type')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field" data-auto-recipient-email-wrap="{{ $scope }}" hidden>
    <label for="auto-{{ $scope }}-recipient-email">Email address</label>
    <input type="email" id="auto-{{ $scope }}-recipient-email" name="recipient_email" placeholder="someone@example.com">
    @error('recipient_email')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field">
    <label for="auto-{{ $scope }}-subject">Subject</label>
    <input type="text" id="auto-{{ $scope }}-subject" name="subject" maxlength="200" required placeholder="e.g. You're a great fit — next steps">
    @error('subject')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="pcat-field">
    <label for="auto-{{ $scope }}-body">Message</label>
    <textarea id="auto-{{ $scope }}-body" name="body" rows="6" required placeholder="Hi @{{lead.name}}, ..."></textarea>
    @error('body')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<p class="auto-merge-help">
    Merge fields: <code>@{{lead.name}}</code> <code>@{{lead.company}}</code> <code>@{{lead.email}}</code>
    <code>@{{lead.phone}}</code> <code>@{{lead.estimated_value}}</code> <code>@{{lead.stage_name}}</code>
    <code>@{{assigned_to.name}}</code> <code>@{{project.name}}</code>
</p>
<div class="pcat-active-row">
    <label class="pcat-active-row__lbl" for="auto-{{ $scope }}-active">Active</label>
    <label class="pcat-switch">
        <input type="checkbox" id="auto-{{ $scope }}-active" name="is_active" value="1" checked>
        <span class="pcat-switch-slider"></span>
    </label>
</div>
