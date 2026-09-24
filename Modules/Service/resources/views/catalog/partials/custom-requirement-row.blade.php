@php
    $types = ['text' => 'Text', 'textarea' => 'Textarea', 'number' => 'Number', 'date' => 'Date', 'checkbox' => 'Checkbox', 'select' => 'Dropdown', 'radio' => 'Radio'];
    $showOptions = in_array($f['type'], ['select', 'radio'], true);
@endphp
<div class="svc-creq-row" data-creq-row>
    <div class="svc-creq-row__main">
        <input type="text" class="svcf-input" name="custom_requirement_fields[{{ $i }}][label]"
               value="{{ $f['label'] }}" maxlength="255" placeholder="Field label, e.g. Preferred date"
               data-creq-label>
        <select class="svcf-input" name="custom_requirement_fields[{{ $i }}][type]" data-creq-type>
            @foreach($types as $val => $label)
                <option value="{{ $val }}" @selected($f['type'] === $val)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="button" class="svc-creq-remove" data-creq-remove aria-label="Remove field">
            <i class="fa fa-times" aria-hidden="true"></i>
        </button>
    </div>
    <div class="svc-creq-row__options" data-creq-options-wrap @if(!$showOptions) hidden @endif>
        <input type="text" class="svcf-input" name="custom_requirement_fields[{{ $i }}][options_csv]"
               value="{{ $f['options_csv'] }}" placeholder="Option 1, Option 2, Option 3"
               data-creq-options>
    </div>
</div>
