@if($errors->any())
    <div class="property-alert property-alert--err {{ $propertyFormErrorBannerClass ?? '' }}" role="alert">
        <i class="fa fa-circle-exclamation"></i>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

@php
    $propertyTypeOptions = \Modules\Account\Models\Property::typeOptions();
    $propertyTypeOld = (string) old('property_type', '');
    $propertyTypeOtherOld = (string) old('property_type_other', '');
@endphp

<form id="{{ $propertyFormId ?? 'property-form' }}" method="post" action="{{ $propertyFormAction ?? route('account.properties.store') }}">
    @csrf
    <div class="property-grid">
        <div class="property-field">
            <label for="property-name">Property name</label>
            <input id="property-name" type="text" name="property_name" value="{{ old('property_name') }}" required maxlength="255" placeholder="e.g. Main office building">
            @error('property_name')<span class="property-field-err">{{ $message }}</span>@enderror
        </div>
        <div class="property-field">
            <label for="property-type">Property type</label>
            <select id="property-type" name="property_type" required data-property-type-select>
                <option value="">Select property type</option>
                @if($propertyTypeOld !== '' && !array_key_exists($propertyTypeOld, $propertyTypeOptions))
                    <option value="{{ $propertyTypeOld }}" selected>{{ $propertyTypeOld }}</option>
                @endif
                @foreach($propertyTypeOptions as $value => $label)
                    <option value="{{ $value }}" @selected($propertyTypeOld === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('property_type')<span class="property-field-err">{{ $message }}</span>@enderror
        </div>
        <div class="property-field" data-property-type-other-wrap @if($propertyTypeOld !== 'other') hidden @endif>
            <label for="property-type-other">Other property type</label>
            <input id="property-type-other" type="text" name="property_type_other" value="{{ $propertyTypeOtherOld }}" maxlength="255" @if($propertyTypeOld !== 'other') disabled @endif placeholder="e.g. Industrial tools">
            @error('property_type_other')<span class="property-field-err">{{ $message }}</span>@enderror
        </div>
        <div class="property-field">
            <label for="property-cost">Cost</label>
            <input id="property-cost" type="number" name="cost" value="{{ old('cost') }}" required min="0" step="0.01" inputmode="decimal" placeholder="0.00">
            @error('cost')<span class="property-field-err">{{ $message }}</span>@enderror
        </div>
        <div class="property-field property-field--full">
            <label for="property-expire-toggle">Expiry</label>
            <label class="property-switch-row" for="property-expire-toggle">
                <input id="property-expire-toggle" class="property-switch-input" type="checkbox" name="has_expiry" value="1" @checked(old('has_expiry'))>
                <span class="property-switch-ui" aria-hidden="true">
                    <span class="property-switch-knob"></span>
                </span>
                <span class="property-switch-text">Property has expiry date</span>
            </label>
            @error('has_expiry')<span class="property-field-err">{{ $message }}</span>@enderror
        </div>
        <div class="property-field" id="property-expire-date-wrap">
            <label for="property-expire-date">Expire date</label>
            <input id="property-expire-date" type="date" name="expire_date" value="{{ old('expire_date') }}">
            @error('expire_date')<span class="property-field-err">{{ $message }}</span>@enderror
        </div>
        <div class="property-field property-field--full">
            <label for="property-description">Description</label>
            <textarea id="property-description" name="description" rows="3" maxlength="5000" placeholder="Add property details, terms, and notes">{{ old('description') }}</textarea>
            @error('description')<span class="property-field-err">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="property-submit-wrap">
        <button type="submit" class="linkbtn"><i class="fa fa-check"></i>Save property</button>
        <span class="property-submit-note">Saved against the business selected in the top navbar.</span>
    </div>
</form>
