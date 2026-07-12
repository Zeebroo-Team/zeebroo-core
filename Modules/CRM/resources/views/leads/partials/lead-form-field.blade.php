@php
    $lead = $lead ?? null;
    $customFields = $customFields ?? collect();
    $fieldKey = $block['field'] ?? 'name';
    $label = $block['label'] ?: ucfirst($fieldKey);
    $required = (bool) ($block['required'] ?? false);
    $placeholder = $block['placeholder'] ?? '';
    $helpText = $block['help_text'] ?? '';
    $customField = str_starts_with($fieldKey, 'custom:') ? ($customFields[(int) substr($fieldKey, 7)] ?? null) : null;

    $existingValue = null;
    if ($lead) {
        if (in_array($fieldKey, ['company', 'email', 'phone'], true)) {
            $existingValue = $lead->{$fieldKey};
        } elseif ($customField) {
            $existingValue = optional($lead->customFieldValues->firstWhere('custom_field_id', $customField->id))->value;
        }
    }
    $oldValue = old($path, $existingValue);
@endphp
@if($customField && $customField->type === \Modules\CRM\Models\LeadCustomField::TYPE_CHECKBOX)
    <div class="pcat-field">
        <div class="pcat-active-row">
            <label class="pcat-active-row__lbl" for="bf-{{ $path }}">{{ $label }}</label>
            <label class="pcat-switch">
                <input type="checkbox" id="bf-{{ $path }}" name="{{ $path }}" value="1" @checked($oldValue == '1')>
                <span class="pcat-switch-slider"></span>
            </label>
        </div>
        @error($path)<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
@else
    <div class="pcat-field" @if($customField && $customField->type === \Modules\CRM\Models\LeadCustomField::TYPE_TEXTAREA) style="grid-column:1/-1;" @endif>
        <label for="bf-{{ $path }}">{{ $label }} @if($required)<span style="color:#ef4444;">*</span>@endif</label>
        @if($customField && $customField->type === \Modules\CRM\Models\LeadCustomField::TYPE_SELECT)
            <select id="bf-{{ $path }}" name="{{ $path }}" @if($required) required @endif>
                <option value="">{{ $placeholder ?: '— Select —' }}</option>
                @foreach($customField->optionList() as $opt)
                    <option value="{{ $opt }}" @selected($oldValue === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        @elseif($customField && $customField->type === \Modules\CRM\Models\LeadCustomField::TYPE_TEXTAREA)
            <textarea id="bf-{{ $path }}" name="{{ $path }}" placeholder="{{ $placeholder }}" @if($required) required @endif>{{ $oldValue }}</textarea>
        @elseif($fieldKey === 'email')
            <input id="bf-{{ $path }}" type="email" name="{{ $path }}" placeholder="{{ $placeholder }}" value="{{ $oldValue }}" @if($required) required @endif>
        @elseif($customField && $customField->type === \Modules\CRM\Models\LeadCustomField::TYPE_NUMBER)
            <input id="bf-{{ $path }}" type="number" step="any" name="{{ $path }}" placeholder="{{ $placeholder }}" value="{{ $oldValue }}" @if($required) required @endif>
        @elseif($customField && $customField->type === \Modules\CRM\Models\LeadCustomField::TYPE_DATE)
            <input id="bf-{{ $path }}" type="date" name="{{ $path }}" placeholder="{{ $placeholder }}" value="{{ $oldValue }}" @if($required) required @endif>
        @else
            <input id="bf-{{ $path }}" type="text" name="{{ $path }}" placeholder="{{ $placeholder }}" value="{{ $oldValue }}" @if($required) required @endif>
        @endif
        @if($helpText)
            <div class="muted" style="font-size:11px;margin-top:4px;">{{ $helpText }}</div>
        @endif
        @error($path)<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
@endif
