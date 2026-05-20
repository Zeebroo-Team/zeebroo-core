@php
    $fieldKey = 'employee_allowance';
    $isEditing = old('field') === $fieldKey && (int) old('employee_allowance_id') === (int) $ea->id;
@endphp
<div class="emp-show__row emp-show__row--editable @if($isEditing) is-editing @endif">
    <div class="emp-show__row-head">
        <span class="emp-show__dt">{{ __('Allowance: :name', ['name' => $ea->allowanceType?->name ?? __('Type')]) }}</span>
        <button type="button" class="emp-show__mini-edit" data-emp-field-edit aria-label="{{ __('Edit') }}" title="{{ __('Edit') }}" @if($isEditing) hidden @endif>
            <i class="fa fa-pen" aria-hidden="true"></i>
        </button>
    </div>
    <div class="emp-show__row-body">
        <p class="emp-show__dd emp-show__view" @if($isEditing) hidden @endif>
            {{ $bizCurrency !== '' ? $bizCurrency.' ' : '' }}{{ number_format((float) $ea->amount, abs((float) $ea->amount - round((float) $ea->amount)) < 0.0001 ? 0 : 2) }}
        </p>
        <form method="post" action="{{ route('hr.employees.update', $employee) }}" class="emp-show__edit-form" @unless($isEditing) hidden @endunless>
            @csrf
            @method('PATCH')
            <input type="hidden" name="field" value="{{ $fieldKey }}">
            <input type="hidden" name="_panel" value="salary">
            <input type="hidden" name="employee_allowance_id" value="{{ $ea->id }}">
            <input type="number" name="allowance_amount" class="emp-show__input" step="0.01" min="0" required
                value="{{ $isEditing ? old('allowance_amount', $ea->amount) : $ea->amount }}">
            @error('allowance_amount')
                <p class="emp-show__field-err" role="alert">{{ $message }}</p>
            @enderror
            @error('employee_allowance_id')
                <p class="emp-show__field-err" role="alert">{{ $message }}</p>
            @enderror
            <div class="emp-show__edit-actions">
                <button type="submit" class="emp-show__btn-save">{{ __('Update') }}</button>
                <button type="button" class="emp-show__btn-cancel" data-emp-field-cancel>{{ __('Cancel') }}</button>
            </div>
        </form>
    </div>
</div>
