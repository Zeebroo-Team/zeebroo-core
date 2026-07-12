@php $pathPrefix = $pathPrefix ?? ''; @endphp
@foreach($blocks as $i => $block)
    @php
        $type = $block['type'] ?? 'text';
        $path = $pathPrefix === '' ? (string) $i : $pathPrefix . '-' . $i;
    @endphp

    @if($type === 'heading')
        @if(($block['size'] ?? 'lg') === 'lg')
            <h1 class="pf-heading-lg">{{ $block['text'] ?? '' }}</h1>
        @else
            <h2 class="pf-heading-md">{{ $block['text'] ?? '' }}</h2>
        @endif
    @elseif($type === 'text')
        <p class="pf-text">{{ $block['text'] ?? '' }}</p>
    @elseif($type === 'image' && filled($block['url'] ?? null))
        <img src="{{ $block['url'] }}" alt="{{ $block['alt'] ?? '' }}" class="pf-image">
    @elseif($type === 'divider')
        <hr class="pf-divider">
    @elseif($type === 'row')
        <div class="pf-row">
            @foreach(($block['blocks'] ?? []) as $colIndex => $column)
                <div class="pf-col">
                    @include('crm::leads.forms.partials.public-blocks', ['blocks' => $column['blocks'] ?? [], 'pathPrefix' => $path . '-' . $colIndex, 'customFields' => $customFields])
                </div>
            @endforeach
        </div>
    @elseif($type === 'field')
        @php
            $fieldKey = $block['field'] ?? 'name';
            $label = $block['label'] ?: ucfirst($fieldKey);
            $required = (bool) ($block['required'] ?? false);
            $placeholder = $block['placeholder'] ?? '';
            $helpText = $block['help_text'] ?? '';
            $customField = str_starts_with($fieldKey, 'custom:') ? ($customFields[(int) substr($fieldKey, 7)] ?? null) : null;
            $inputName = $path;
        @endphp
        <div class="pf-field">
            @if($customField && $customField->type === 'checkbox')
                <label class="pf-checkbox">
                    <input type="checkbox" name="{{ $inputName }}" value="1">
                    {{ $label }}@if($required) *@endif
                </label>
            @else
                <label for="pf-field-{{ $path }}">{{ $label }}@if($required) <span style="color:#ef4444;">*</span>@endif</label>
                @if($customField && $customField->type === 'select')
                    <select id="pf-field-{{ $path }}" name="{{ $inputName }}" @if($required) required @endif>
                        <option value="">{{ $placeholder ?: '— Select —' }}</option>
                        @foreach($customField->optionList() as $opt)
                            <option value="{{ $opt }}" @selected(old($inputName) === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                @elseif($customField && $customField->type === 'textarea')
                    <textarea id="pf-field-{{ $path }}" name="{{ $inputName }}" placeholder="{{ $placeholder }}" @if($required) required @endif>{{ old($inputName) }}</textarea>
                @elseif($fieldKey === 'email')
                    <input type="email" id="pf-field-{{ $path }}" name="{{ $inputName }}" placeholder="{{ $placeholder }}" value="{{ old($inputName) }}" @if($required) required @endif>
                @elseif($fieldKey === 'phone')
                    <input type="tel" id="pf-field-{{ $path }}" name="{{ $inputName }}" placeholder="{{ $placeholder }}" value="{{ old($inputName) }}" @if($required) required @endif>
                @elseif($customField && $customField->type === 'number')
                    <input type="number" id="pf-field-{{ $path }}" name="{{ $inputName }}" placeholder="{{ $placeholder }}" value="{{ old($inputName) }}" @if($required) required @endif>
                @elseif($customField && $customField->type === 'date')
                    <input type="date" id="pf-field-{{ $path }}" name="{{ $inputName }}" placeholder="{{ $placeholder }}" value="{{ old($inputName) }}" @if($required) required @endif>
                @else
                    <input type="text" id="pf-field-{{ $path }}" name="{{ $inputName }}" placeholder="{{ $placeholder }}" value="{{ old($inputName) }}" @if($required) required @endif>
                @endif
            @endif
            @if($helpText)
                <div class="pf-help">{{ $helpText }}</div>
            @endif
            @error($inputName)<div class="pf-error">{{ $message }}</div>@enderror
        </div>
    @endif
@endforeach
