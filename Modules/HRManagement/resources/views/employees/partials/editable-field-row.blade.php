@php
    $displayValue = $displayValue ?? $value;
    $fieldId = 'emp-edit-'.preg_replace('/[^a-z0-9_-]/i', '-', $field);
    $type = $type ?? 'text';
    $fullWidth = $fullWidth ?? false;
    $numberStep = $numberStep ?? '0.01';
    $isTextarea = ($type === 'textarea');
    $isEditing = old('field') === $field;
    $required = ($required ?? true) !== false;
@endphp
<div class="emp-show__row emp-show__row--editable {{ $fullWidth ? 'emp-show__row--full' : '' }} @if($isEditing) is-editing @endif">
    <div class="emp-show__row-head">
        <span class="emp-show__dt">{{ $label }}</span>
        <button type="button" class="emp-show__mini-edit" data-emp-field-edit aria-label="{{ __('Edit') }}" title="{{ __('Edit') }}" @if($isEditing) hidden @endif>
            <i class="fa fa-pen" aria-hidden="true"></i>
        </button>
    </div>
    <div class="emp-show__row-body">
        @if($type === 'select' && is_array($selectOptions ?? null))
            <p class="emp-show__dd emp-show__view" @if($isEditing) hidden @endif>{{ $selectOptions[$value] ?? $displayValue ?? '—' }}</p>
        @elseif($type === 'email' && filled($value))
            <p class="emp-show__dd emp-show__view" @if($isEditing) hidden @endif><a href="mailto:{{ $value }}">{{ $displayValue }}</a></p>
        @elseif($isTextarea)
            <div class="emp-show__dd emp-show__view" @if($isEditing) hidden @endif>
                @if(filled($displayValue))
                    {!! nl2br(e($displayValue)) !!}
                @else
                    —
                @endif
            </div>
        @else
            <p class="emp-show__dd emp-show__view" @if($isEditing) hidden @endif>{{ filled($displayValue) || $displayValue === '0' ? $displayValue : '—' }}</p>
        @endif
        <form method="post" action="{{ route('hr.employees.update', $employee) }}" class="emp-show__edit-form" @unless($isEditing) hidden @endunless>
            @csrf
            @method('PATCH')
            <input type="hidden" name="field" value="{{ $field }}">
            <input type="hidden" name="_panel" value="{{ $panel }}">
            @if($isTextarea)
                <textarea name="{{ $field }}" id="{{ $fieldId }}" class="emp-show__input emp-show__input--textarea" rows="4" @if($required) required @endif>{{ $isEditing ? old($field, $value) : $value }}</textarea>
            @elseif($type === 'select' && is_array($selectOptions ?? null))
                <select name="{{ $field }}" id="{{ $fieldId }}" class="emp-show__input emp-show__input--select" @if($required) required @endif>
                    @foreach($selectOptions as $optVal => $optLabel)
                        <option value="{{ $optVal }}" @selected((string) ($isEditing ? old($field) : $value) === (string) $optVal)>{{ $optLabel }}</option>
                    @endforeach
                </select>
            @elseif($type === 'number')
                <input type="number" name="{{ $field }}" id="{{ $fieldId }}" class="emp-show__input" value="{{ $isEditing ? old($field, $value) : $value }}" step="{{ $numberStep }}" min="0" @if($required) required @endif>
            @elseif($type === 'date')
                <input type="date" name="{{ $field }}" id="{{ $fieldId }}" class="emp-show__input" value="{{ $isEditing ? old($field, $value) : $value }}" @if($required) required @endif>
            @else
                <input type="{{ $type === 'email' ? 'email' : ($type === 'tel' ? 'tel' : 'text') }}" name="{{ $field }}" id="{{ $fieldId }}" class="emp-show__input" value="{{ $isEditing ? old($field, $value) : $value }}" @if($required) required @endif>
            @endif
            @error($field)
                <p class="emp-show__field-err" role="alert">{{ $message }}</p>
            @enderror
            <div class="emp-show__edit-actions">
                <button type="submit" class="emp-show__btn-save">{{ __('Update') }}</button>
                <button type="button" class="emp-show__btn-cancel" data-emp-field-cancel>{{ __('Cancel') }}</button>
            </div>
        </form>
    </div>
</div>
