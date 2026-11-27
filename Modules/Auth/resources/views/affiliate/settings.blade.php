@extends('theme::layouts.app', ['title' => 'Settings', 'heading' => 'Settings'])

@section('content')
@include('auth::affiliate.partials.styles')

@php
    $tabs = [
        'profile' => ['Profile', 'fa-user'],
        'contact' => ['Contact & address', 'fa-address-book'],
        'payout' => ['Payout bank', 'fa-building-columns'],
        'identity' => ['ID verification', 'fa-id-card'],
    ];
    $doneCount = count(array_filter($completion));
    $currentIdType = old('id_type', $profile->id_type ?? \Modules\Auth\Models\AffiliateProfile::ID_NIC);
    $banks = ['Bank of Ceylon', "People's Bank", 'Commercial Bank of Ceylon', 'Hatton National Bank', 'Sampath Bank', 'Seylan Bank',
        'National Development Bank', 'DFCC Bank', 'Nations Trust Bank', 'Pan Asia Bank', 'Union Bank of Colombo', 'Amana Bank',
        'Cargills Bank', 'National Savings Bank', 'HSBC', 'Standard Chartered'];
@endphp

<div class="aff-wrap">
    <div class="aff-header">
        <div>
            <h1 class="aff-title"><i class="fa fa-gear" aria-hidden="true"></i>Settings</h1>
            <p class="aff-sub">Keep your profile, contact, payout bank and ID details up to date. We use them to verify you and pay your commission.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="aff-msg"><i class="fa fa-circle-check" aria-hidden="true"></i> {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="aff-msg aff-msg-err"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i> {{ __('Please check the highlighted fields.') }}</div>
    @endif

    @if($doneCount < count($completion))
        <div class="aff-progress">
            <i class="fa fa-list-check" aria-hidden="true"></i>
            <div><strong>{{ $doneCount }} of {{ count($completion) }} sections complete.</strong> Complete every section before your first commission payout.</div>
            <div class="aff-progress-bar" aria-hidden="true"><span style="width:{{ round($doneCount / count($completion) * 100) }}%"></span></div>
        </div>
    @endif

    <div class="aff-set">
        <nav class="aff-set-tabs" role="tablist" aria-label="Settings sections">
            @foreach($tabs as $key => [$label, $icon])
                <button type="button" role="tab" class="aff-set-tab {{ $tab === $key ? 'is-active' : '' }}"
                        id="aff-set-tab-{{ $key }}" aria-controls="aff-set-panel-{{ $key }}"
                        aria-selected="{{ $tab === $key ? 'true' : 'false' }}" tabindex="{{ $tab === $key ? '0' : '-1' }}" data-aff-tab="{{ $key }}">
                    <i class="fa {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                    @if($completion[$key])
                        <i class="fa fa-circle-check aff-set-tab-state aff-set-tab-state--done" title="Complete" aria-label="Complete"></i>
                    @else
                        <span class="aff-set-tab-state aff-set-tab-state--todo" title="Needs details" aria-label="Needs details"></span>
                    @endif
                </button>
            @endforeach
        </nav>

        <div>
            {{-- ── Profile ─────────────────────────────────────────────── --}}
            <section class="aff-set-panel {{ $tab === 'profile' ? 'is-active' : '' }}" id="aff-set-panel-profile" role="tabpanel" aria-labelledby="aff-set-tab-profile">
                <h2 class="aff-set-panel-title">Profile</h2>
                <p class="aff-set-panel-sub">Your name and the email you sign in with.</p>
                <form method="POST" action="{{ route('affiliate.settings.profile') }}" novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_tab" value="profile">
                    <div class="aff-field-row">
                        <div class="aff-field">
                            <label for="set-name">Full name <span class="aff-req">*</span></label>
                            <input type="text" id="set-name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" required autocomplete="name" class="@error('name') is-invalid @enderror">
                            @error('name')<p class="aff-field-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="aff-field">
                            <label for="set-email">Login email <span class="aff-req">*</span></label>
                            <input type="email" id="set-email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" required autocomplete="email" class="@error('email') is-invalid @enderror">
                            @error('email')<p class="aff-field-err">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="aff-set-group">
                        <h3 class="aff-set-group-title">Change password</h3>
                        <p class="aff-set-group-sub">Leave these empty to keep your current password.</p>
                        <div class="aff-field-row">
                            <div class="aff-field">
                                <label for="set-password">New password</label>
                                <input type="password" id="set-password" name="password" autocomplete="new-password" class="@error('password') is-invalid @enderror">
                                <p class="aff-field-hint">At least 8 characters.</p>
                                @error('password')<p class="aff-field-err">{{ $message }}</p>@enderror
                            </div>
                            <div class="aff-field">
                                <label for="set-password-confirmation">Confirm new password</label>
                                <input type="password" id="set-password-confirmation" name="password_confirmation" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="aff-field-row">
                            <div class="aff-field">
                                <label for="set-current-password">Current password</label>
                                <input type="password" id="set-current-password" name="current_password" autocomplete="current-password" class="@error('current_password') is-invalid @enderror">
                                <p class="aff-field-hint">Needed when you change your email or password.</p>
                                @error('current_password')<p class="aff-field-err">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="aff-set-foot">
                        <button type="submit" class="aff-submit-btn"><i class="fa fa-floppy-disk" aria-hidden="true"></i> Save profile</button>
                    </div>
                </form>
            </section>

            {{-- ── Contact & address ────────────────────────────────────── --}}
            <section class="aff-set-panel {{ $tab === 'contact' ? 'is-active' : '' }}" id="aff-set-panel-contact" role="tabpanel" aria-labelledby="aff-set-tab-contact">
                <h2 class="aff-set-panel-title">Contact &amp; address</h2>
                <p class="aff-set-panel-sub">How we reach you about your referrals and payouts.</p>
                <form method="POST" action="{{ route('affiliate.settings.contact') }}" novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_tab" value="contact">
                    <div class="aff-field-row">
                        <div class="aff-field">
                            <label for="set-phone">Mobile number <span class="aff-req">*</span></label>
                            <input type="tel" id="set-phone" name="phone" value="{{ old('phone', $profile->phone) }}" maxlength="20" required autocomplete="tel" placeholder="+94 77 123 4567" class="@error('phone') is-invalid @enderror">
                            @error('phone')<p class="aff-field-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="aff-field">
                            <label for="set-phone-alt">Other phone <span class="aff-optional">(optional)</span></label>
                            <input type="tel" id="set-phone-alt" name="phone_alt" value="{{ old('phone_alt', $profile->phone_alt) }}" maxlength="20" autocomplete="tel" placeholder="WhatsApp or landline" class="@error('phone_alt') is-invalid @enderror">
                            @error('phone_alt')<p class="aff-field-err">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="aff-set-group">
                        <h3 class="aff-set-group-title">Address</h3>
                        <p class="aff-set-group-sub">Your home or office address.</p>
                        <div class="aff-field">
                            <label for="set-address1">Address line 1 <span class="aff-req">*</span></label>
                            <input type="text" id="set-address1" name="address_line1" value="{{ old('address_line1', $profile->address_line1) }}" maxlength="255" required autocomplete="address-line1" placeholder="House number and street" class="@error('address_line1') is-invalid @enderror">
                            @error('address_line1')<p class="aff-field-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="aff-field">
                            <label for="set-address2">Address line 2 <span class="aff-optional">(optional)</span></label>
                            <input type="text" id="set-address2" name="address_line2" value="{{ old('address_line2', $profile->address_line2) }}" maxlength="255" autocomplete="address-line2" placeholder="Apartment, area or landmark">
                            @error('address_line2')<p class="aff-field-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="aff-field-row aff-field-row--3">
                            <div class="aff-field">
                                <label for="set-city">City <span class="aff-req">*</span></label>
                                <input type="text" id="set-city" name="city" value="{{ old('city', $profile->city) }}" maxlength="100" required autocomplete="address-level2" class="@error('city') is-invalid @enderror">
                                @error('city')<p class="aff-field-err">{{ $message }}</p>@enderror
                            </div>
                            <div class="aff-field">
                                <label for="set-district">District / province</label>
                                <input type="text" id="set-district" name="district" value="{{ old('district', $profile->district) }}" maxlength="100" autocomplete="address-level1">
                                @error('district')<p class="aff-field-err">{{ $message }}</p>@enderror
                            </div>
                            <div class="aff-field">
                                <label for="set-postal">Postal code</label>
                                <input type="text" id="set-postal" name="postal_code" value="{{ old('postal_code', $profile->postal_code) }}" maxlength="20" autocomplete="postal-code">
                                @error('postal_code')<p class="aff-field-err">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="aff-field-row">
                            <div class="aff-field">
                                <label for="set-country">Country <span class="aff-req">*</span></label>
                                <input type="text" id="set-country" name="country" value="{{ old('country', $profile->country ?: 'Sri Lanka') }}" maxlength="100" required autocomplete="country-name" class="@error('country') is-invalid @enderror">
                                @error('country')<p class="aff-field-err">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="aff-set-foot">
                        <button type="submit" class="aff-submit-btn"><i class="fa fa-floppy-disk" aria-hidden="true"></i> Save contact details</button>
                    </div>
                </form>
            </section>

            {{-- ── Payout bank ──────────────────────────────────────────── --}}
            <section class="aff-set-panel {{ $tab === 'payout' ? 'is-active' : '' }}" id="aff-set-panel-payout" role="tabpanel" aria-labelledby="aff-set-tab-payout">
                <h2 class="aff-set-panel-title">Payout bank details</h2>
                <p class="aff-set-panel-sub">Your commission is paid by bank transfer to this account. The account holder name should match your ID.</p>
                <p class="aff-secure"><i class="fa fa-lock" aria-hidden="true"></i><span>The account number is stored encrypted. Only you and Zeebroo admins can see it.</span></p>
                <form method="POST" action="{{ route('affiliate.settings.payout') }}" novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_tab" value="payout">
                    <div class="aff-field-row">
                        <div class="aff-field">
                            <label for="set-bank">Bank <span class="aff-req">*</span></label>
                            <input type="text" id="set-bank" name="bank_name" value="{{ old('bank_name', $profile->bank_name) }}" maxlength="120" required list="aff-bank-list" autocomplete="off" placeholder="e.g. Commercial Bank of Ceylon" class="@error('bank_name') is-invalid @enderror">
                            <datalist id="aff-bank-list">
                                @foreach($banks as $bank)<option value="{{ $bank }}">@endforeach
                            </datalist>
                            @error('bank_name')<p class="aff-field-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="aff-field">
                            <label for="set-branch">Branch <span class="aff-req">*</span></label>
                            <input type="text" id="set-branch" name="bank_branch" value="{{ old('bank_branch', $profile->bank_branch) }}" maxlength="120" required placeholder="e.g. Kandy City" class="@error('bank_branch') is-invalid @enderror">
                            @error('bank_branch')<p class="aff-field-err">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="aff-field-row">
                        <div class="aff-field">
                            <label for="set-holder">Account holder name <span class="aff-req">*</span></label>
                            <input type="text" id="set-holder" name="account_holder_name" value="{{ old('account_holder_name', $profile->account_holder_name) }}" maxlength="255" required placeholder="As shown on your bank book" class="@error('account_holder_name') is-invalid @enderror">
                            @error('account_holder_name')<p class="aff-field-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="aff-field">
                            <label for="set-account">Account number <span class="aff-req">*</span></label>
                            <input type="text" id="set-account" name="account_number" value="{{ old('account_number', $profile->account_number) }}" maxlength="40" required inputmode="numeric" autocomplete="off" spellcheck="false" class="@error('account_number') is-invalid @enderror">
                            @error('account_number')<p class="aff-field-err">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="aff-set-foot">
                        <button type="submit" class="aff-submit-btn"><i class="fa fa-floppy-disk" aria-hidden="true"></i> Save bank details</button>
                    </div>
                </form>
            </section>

            {{-- ── ID verification ──────────────────────────────────────── --}}
            <section class="aff-set-panel {{ $tab === 'identity' ? 'is-active' : '' }}" id="aff-set-panel-identity" role="tabpanel" aria-labelledby="aff-set-tab-identity">
                <h2 class="aff-set-panel-title">ID verification</h2>
                <p class="aff-set-panel-sub">Upload a copy of your national ID card (both sides) or the photo page of your passport. We use it to confirm who we pay.</p>
                <p class="aff-secure"><i class="fa fa-shield-halved" aria-hidden="true"></i><span>Your ID copies are stored privately. Only you and Zeebroo admins can open them.</span></p>

                <form method="POST" action="{{ route('affiliate.settings.identity') }}" enctype="multipart/form-data" novalidate id="aff-identity-form">
                    @csrf
                    <input type="hidden" name="_tab" value="identity">

                    <div class="aff-field">
                        <label>ID type <span class="aff-req">*</span></label>
                        <div class="aff-choices" role="radiogroup" aria-label="ID type">
                            @foreach($idTypes as $value => $label)
                                <label class="aff-choice">
                                    <input type="radio" name="id_type" value="{{ $value }}" @checked($currentIdType === $value) data-aff-id-type>
                                    <i class="fa {{ $value === 'passport' ? 'fa-passport' : 'fa-id-card' }}" aria-hidden="true"></i>{{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('id_type')<p class="aff-field-err">{{ $message }}</p>@enderror
                        @if($profile->id_type)
                            <p class="aff-field-hint">Switching the ID type removes the copies of the other type when you save.</p>
                        @endif
                    </div>

                    <div class="aff-field-row">
                        <div class="aff-field">
                            <label for="set-id-number"><span data-aff-id-label="nic" @if($currentIdType !== 'nic') hidden @endif>NIC number</span><span data-aff-id-label="passport" @if($currentIdType !== 'passport') hidden @endif>Passport number</span> <span class="aff-req">*</span></label>
                            <input type="text" id="set-id-number" name="id_number" value="{{ old('id_number', $profile->id_number) }}" maxlength="30" required autocomplete="off" spellcheck="false"
                                   placeholder="{{ $currentIdType === 'passport' ? 'e.g. N1234567' : 'e.g. 199012345678' }}" data-nic-placeholder="e.g. 199012345678" data-passport-placeholder="e.g. N1234567"
                                   style="text-transform:uppercase" class="@error('id_number') is-invalid @enderror">
                            @error('id_number')<p class="aff-field-err">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @foreach($slots as $idType => $typeSlots)
                        <div class="aff-docs" data-aff-slots="{{ $idType }}" @if($currentIdType !== $idType) hidden @endif>
                            @foreach($typeSlots as $type => $label)
                                @php $doc = $documents->get($type); @endphp
                                <div class="aff-doc @error($type) is-invalid @enderror">
                                    <div class="aff-doc-title">
                                        <span>{{ $label }}</span>
                                        @if($doc)
                                            <span class="aff-status aff-status--on"><i class="fa fa-check" aria-hidden="true"></i> Uploaded</span>
                                        @else
                                            <span class="aff-status aff-status--warn">Required</span>
                                        @endif
                                    </div>

                                    @if($doc)
                                        <a class="aff-doc-preview" href="{{ route('affiliate.settings.documents.show', $type) }}" target="_blank" rel="noopener" title="Open {{ $label }}">
                                            @if($doc->isImage())
                                                <img src="{{ route('affiliate.settings.documents.show', $type) }}" alt="{{ $label }}" loading="lazy">
                                            @else
                                                <i class="fa fa-file-pdf" aria-hidden="true"></i>
                                            @endif
                                        </a>
                                        <div class="aff-doc-meta">{{ $doc->original_name }} · {{ $doc->sizeLabel() }} · Uploaded {{ $doc->updated_at->format('d M Y') }}</div>
                                        <div class="aff-doc-actions">
                                            <a href="{{ route('affiliate.settings.documents.show', $type) }}" target="_blank" rel="noopener" class="aff-btn"><i class="fa fa-up-right-from-square" aria-hidden="true"></i> Open</a>
                                            <button type="submit" form="aff-doc-delete-{{ $type }}" class="aff-btn aff-btn--danger"><i class="fa fa-trash" aria-hidden="true"></i> Remove</button>
                                        </div>
                                    @endif

                                    <div class="aff-field" style="margin:0">
                                        <label class="aff-drop" data-aff-drop>
                                            <input type="file" name="{{ $type }}" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" aria-label="{{ $doc ? 'Replace' : 'Upload' }} {{ $label }}">
                                            <i class="fa fa-cloud-arrow-up" aria-hidden="true"></i>
                                            <span><strong>{{ $doc ? 'Replace file' : 'Choose a file' }}</strong> or drag it here</span>
                                            <span>JPG, PNG, WEBP or PDF, up to {{ $documentMaxMb }} MB</span>
                                            <span class="aff-drop-name" data-aff-drop-name hidden></span>
                                        </label>
                                        @error($type)<p class="aff-field-err">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    <div class="aff-set-foot">
                        <button type="submit" class="aff-submit-btn"><i class="fa fa-floppy-disk" aria-hidden="true"></i> Save ID details</button>
                    </div>
                </form>

                {{-- Remove forms live outside the identity form (forms cannot be nested). --}}
                @foreach($documents as $type => $doc)
                    <form method="POST" action="{{ route('affiliate.settings.documents.destroy', $type) }}" id="aff-doc-delete-{{ $type }}" hidden
                          onsubmit="return confirm('Remove the {{ \Modules\Auth\Models\AffiliateDocument::labelFor($type) }} copy?')">
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            </section>
        </div>
    </div>
</div>

<script>
(function () {
    // Tabs: switch without reloading and keep ?tab= in the address bar so a refresh
    // or a validation error comes back to the same tab.
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-aff-tab]'));
    var tabBar = document.querySelector('.aff-set-tabs');
    // On phones the tab bar scrolls sideways: keep the open tab in view.
    function revealTab(btn) {
        if (!tabBar || tabBar.scrollWidth <= tabBar.clientWidth) return;
        tabBar.scrollLeft = Math.max(0, btn.offsetLeft - tabBar.offsetLeft - 12);
    }
    var current = document.querySelector('.aff-set-tab.is-active');
    if (current) revealTab(current);
    function activate(key, focus) {
        tabs.forEach(function (btn) {
            var on = btn.getAttribute('data-aff-tab') === key;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
            btn.setAttribute('tabindex', on ? '0' : '-1');
            var panel = document.getElementById(btn.getAttribute('aria-controls'));
            if (panel) panel.classList.toggle('is-active', on);
            if (on) revealTab(btn);
            if (on && focus) btn.focus();
        });
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', key);
            window.history.replaceState(null, '', url.toString());
        } catch (e) {}
    }
    tabs.forEach(function (btn, i) {
        btn.addEventListener('click', function () { activate(btn.getAttribute('data-aff-tab'), false); });
        btn.addEventListener('keydown', function (e) {
            var next = null;
            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
            if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length];
            if (next) { e.preventDefault(); activate(next.getAttribute('data-aff-tab'), true); }
        });
    });

    // ID type: show the matching upload slots, label and placeholder.
    var idNumber = document.getElementById('set-id-number');
    function showIdType(type) {
        document.querySelectorAll('[data-aff-slots]').forEach(function (el) { el.hidden = el.getAttribute('data-aff-slots') !== type; });
        document.querySelectorAll('[data-aff-id-label]').forEach(function (el) { el.hidden = el.getAttribute('data-aff-id-label') !== type; });
        if (idNumber) idNumber.placeholder = idNumber.getAttribute('data-' + type + '-placeholder') || '';
    }
    document.querySelectorAll('[data-aff-id-type]').forEach(function (radio) {
        radio.addEventListener('change', function () { if (radio.checked) showIdType(radio.value); });
    });

    // Drop zones: show the chosen file name and highlight while dragging.
    document.querySelectorAll('[data-aff-drop]').forEach(function (zone) {
        var input = zone.querySelector('input[type=file]');
        var nameEl = zone.querySelector('[data-aff-drop-name]');
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            zone.classList.toggle('has-file', !!file);
            if (nameEl) {
                nameEl.hidden = !file;
                nameEl.textContent = file ? file.name : '';
            }
        });
        ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.add('is-over'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.remove('is-over'); }); });
    });
})();
</script>
@endsection
