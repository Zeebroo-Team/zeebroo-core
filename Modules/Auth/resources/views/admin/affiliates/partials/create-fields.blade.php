{{-- "New affiliate" login fields, shared by the inline form and the modal. Expects $idPrefix. --}}
<div class="aff-field-row">
    <div class="aff-field">
        <label for="{{ $idPrefix }}-name">Full name</label>
        <input type="text" id="{{ $idPrefix }}-name" name="name" value="{{ old('name') }}" maxlength="255" required
               class="@error('name') is-invalid @enderror" placeholder="e.g. Nimal Perera" autocomplete="off">
        @error('name')<p class="aff-field-err">{{ $message }}</p>@enderror
    </div>
    <div class="aff-field">
        <label for="{{ $idPrefix }}-email">Login email</label>
        <input type="email" id="{{ $idPrefix }}-email" name="email" value="{{ old('email') }}" maxlength="255" required
               class="@error('email') is-invalid @enderror" placeholder="name@example.com" autocomplete="off">
        @error('email')<p class="aff-field-err">{{ $message }}</p>@enderror
    </div>
</div>
<div class="aff-field-row">
    <div class="aff-field">
        <label for="{{ $idPrefix }}-password">Password</label>
        <input type="password" id="{{ $idPrefix }}-password" name="password" minlength="8" required
               class="@error('password') is-invalid @enderror" autocomplete="new-password">
        <p class="aff-field-hint">At least 8 characters. Share it with the affiliate securely.</p>
        @error('password')<p class="aff-field-err">{{ $message }}</p>@enderror
    </div>
    <div class="aff-field">
        <label for="{{ $idPrefix }}-password-confirmation">Confirm password</label>
        <input type="password" id="{{ $idPrefix }}-password-confirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
    </div>
</div>
<div class="aff-field">
    <label for="{{ $idPrefix }}-code">First referral code <span class="aff-optional">(optional)</span></label>
    <input type="text" id="{{ $idPrefix }}-code" name="code" value="{{ old('code') }}" maxlength="20"
           class="aff-input-code @error('code') is-invalid @enderror"
           placeholder="e.g. NIMAL2026" autocomplete="off" spellcheck="false" autocapitalize="characters">
    <p class="aff-field-hint">4 to 20 letters, numbers or dashes. Leave it empty for a random code. The affiliate can create more codes later.</p>
    @error('code')<p class="aff-field-err">{{ $message }}</p>@enderror
</div>
