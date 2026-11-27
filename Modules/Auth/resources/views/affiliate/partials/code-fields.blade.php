{{-- "New code" fields, shared by the inline first-code form and the modal. Expects $idPrefix. --}}
<div class="aff-field-row">
    <div class="aff-field">
        <label for="{{ $idPrefix }}-code">Custom code <span class="aff-optional">(optional)</span></label>
        <input type="text" id="{{ $idPrefix }}-code" name="code" value="{{ old('code') }}" maxlength="20"
               class="aff-input-code @error('code') is-invalid @enderror"
               placeholder="e.g. NIMAL2026" autocomplete="off" spellcheck="false" autocapitalize="characters">
        <p class="aff-field-hint">4 to 20 letters, numbers or dashes. Leave it empty for a random code.</p>
        @error('code')<p class="aff-field-err">{{ $message }}</p>@enderror
    </div>
    <div class="aff-field">
        <label for="{{ $idPrefix }}-label">Label <span class="aff-optional">(optional)</span></label>
        <input type="text" id="{{ $idPrefix }}-label" name="label" value="{{ old('label') }}" maxlength="100"
               class="@error('label') is-invalid @enderror" placeholder="e.g. Facebook page">
        <p class="aff-field-hint">A note for where you share this code. Sign-ups don't see it.</p>
        @error('label')<p class="aff-field-err">{{ $message }}</p>@enderror
    </div>
</div>
