@php
    $submitLabel = $submitLabel ?? 'Create employee';
    $formBannerClass = $formBannerClass ?? 'emp-inline-form__banner';
    $showFormErrorBanner = filter_var($showFormErrorBanner ?? true, FILTER_VALIDATE_BOOLEAN);
    $pickNewRow = \Modules\HRManagement\Models\Employee::SELECT_NEW_ROW;
    $allowanceTypes = $allowanceTypes ?? collect();
    $salaryCurrency = trim((string) get_settings('business.currency', '', $business ?? null));
    $salaryCurrencySuffix = $salaryCurrency !== '' ? ' ('.$salaryCurrency.')' : '';
@endphp
@if($errors->any() && $showFormErrorBanner)
    <div class="{{ $formBannerClass }}" role="alert">{{ $errors->first() }}</div>
@endif
<form method="post" action="{{ route('hr.employees.store') }}" class="emp-form-grid" enctype="multipart/form-data">
    @csrf
    <fieldset class="emp-fieldset">
        <legend class="emp-legend">1. Personal details</legend>
        <div class="emp-form-rows">
            <div class="emp-field emp-field--full">
                <label for="emp-full-name">Full name <span class="emp-req">*</span></label>
                <input type="text" name="full_name" id="emp-full-name" value="{{ old('full_name') }}" required maxlength="255" autocomplete="name" placeholder="Legal name">
            </div>
            <div class="emp-field emp-field--full">
                <label for="emp-profile-photo-create">Profile photo <span class="muted" style="font-weight:600;text-transform:none;letter-spacing:0;">(optional)</span></label>
                <input type="file" name="profile_photo" id="emp-profile-photo-create" accept="image/*">
            </div>
            <div class="emp-field">
                <label for="emp-dob">Date of birth <span class="emp-req">*</span></label>
                <input type="date" name="date_of_birth" id="emp-dob" value="{{ old('date_of_birth') }}" required autocomplete="bday">
            </div>
            <div class="emp-field">
                <label for="emp-nic">NIC / Passport number <span class="emp-req">*</span></label>
                <input type="text" name="nic_passport_number" id="emp-nic" value="{{ old('nic_passport_number') }}" required maxlength="64" autocomplete="off" placeholder="Unique ID">
            </div>
            <div class="emp-field emp-field--full">
                <label for="emp-permanent-addr">Permanent address <span class="emp-req">*</span></label>
                <textarea name="permanent_address" id="emp-permanent-addr" rows="3" required maxlength="5000" placeholder="Home address as on legal documents">{{ old('permanent_address') }}</textarea>
            </div>
            <div class="emp-field emp-field--full">
                <label for="emp-current-addr">Current address <span class="emp-req">*</span></label>
                <textarea name="current_address" id="emp-current-addr" rows="3" required maxlength="5000" placeholder="Current place of residence">{{ old('current_address') }}</textarea>
            </div>
            <div class="emp-field">
                <label for="emp-phone">Phone number <span class="emp-req">*</span></label>
                <input type="tel" name="phone_number" id="emp-phone" value="{{ old('phone_number') }}" required maxlength="40" autocomplete="tel" placeholder="Primary contact">
            </div>
            <div class="emp-field">
                <label for="emp-email">Personal email <span class="emp-req">*</span></label>
                <input type="email" name="personal_email" id="emp-email" value="{{ old('personal_email') }}" required maxlength="255" autocomplete="email" placeholder="Active email">
            </div>
        </div>
    </fieldset>

    <fieldset class="emp-fieldset">
        <legend class="emp-legend">2. Employment details</legend>
        <div class="emp-form-rows">
            <div class="emp-field">
                <label for="emp-employee-id">Employee ID <span class="emp-req">*</span></label>
                <input type="text" name="employee_id" id="emp-employee-id" value="{{ old('employee_id') }}" required maxlength="64" autocomplete="off" placeholder="Internal company ID">
            </div>
            <div class="emp-field emp-field--full">
                <label for="emp-job-title-id">Job title / designation <span class="emp-req">*</span></label>
                <select name="job_title_id" id="emp-job-title-id" required>
                    <option value="" disabled @selected(blank(old('job_title_id')))>Choose …</option>
                    @foreach($jobTitles as $jt)
                        <option value="{{ $jt->id }}" @selected((string) old('job_title_id') === (string) $jt->id)>{{ $jt->name }}</option>
                    @endforeach
                    <option value="{{ $pickNewRow }}" @selected((string) old('job_title_id') === $pickNewRow)>+ New job title…</option>
                </select>
                <div id="emp-new-job-title-wrap" class="emp-field" style="margin-top:10px;padding-top:12px;border-top:1px dashed color-mix(in srgb,var(--border) 90%,transparent);{{ (string) old('job_title_id') === $pickNewRow ? '' : ' display:none;' }}">
                    <label for="emp-new-job-title-name">New job title <span class="emp-req">*</span></label>
                    <input type="text" name="new_job_title_name" id="emp-new-job-title-name" value="{{ old('new_job_title_name') }}" maxlength="255" placeholder="e.g. Senior Accountant">
                </div>
            </div>
            <div class="emp-field emp-field--full">
                <label for="emp-dept-id">Department <span class="emp-req">*</span></label>
                <select name="department_id" id="emp-dept-id" required>
                    <option value="" disabled @selected(blank(old('department_id')))>Choose …</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" @selected((string) old('department_id') === (string) $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                    <option value="{{ $pickNewRow }}" @selected((string) old('department_id') === $pickNewRow)>+ New department…</option>
                </select>
                <div id="emp-new-dept-wrap" class="emp-field" style="margin-top:10px;padding-top:12px;border-top:1px dashed color-mix(in srgb,var(--border) 90%,transparent);{{ (string) old('department_id') === $pickNewRow ? '' : ' display:none;' }}">
                    <label for="emp-new-dept-name">New department name <span class="emp-req">*</span></label>
                    <input type="text" name="new_department_name" id="emp-new-dept-name" value="{{ old('new_department_name') }}" maxlength="255" placeholder="e.g. Finance, Operations">
                </div>
            </div>
            <div class="emp-field">
                <label for="emp-doj">Date of joining <span class="emp-req">*</span></label>
                <input type="date" name="date_of_joining" id="emp-doj" value="{{ old('date_of_joining') }}" required>
            </div>
            <div class="emp-field emp-field--full">
                <label for="emp-emp-type">Employment type <span class="emp-req">*</span></label>
                <select name="employment_type" id="emp-emp-type" required>
                    <option value="" disabled @selected(blank(old('employment_type')))>Choose …</option>
                    @foreach($employmentTypeLabels as $value => $label)
                        <option value="{{ $value }}" @selected(old('employment_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset class="emp-fieldset">
        <legend class="emp-legend">3. Salary &amp; compensation</legend>
        <p class="muted" style="margin:-4px 0 12px;font-size:12px;line-height:1.45;grid-column:1/-1;">
            {{ __('Monthly gross must equal basic salary plus the sum of all allowance amounts you enter.') }}
            @if(Route::has('hr.allowance-types.index'))
                <a href="{{ route('hr.allowance-types.index') }}" style="color:var(--primary);font-weight:600;">{{ __('Manage allowance types') }}</a>
            @endif
        </p>
        <div class="emp-form-rows">
            <div class="emp-field">
                <label for="emp-basic-salary">{{ __('Basic salary') }} <span class="emp-req">*</span>{{ $salaryCurrencySuffix }}</label>
                <input type="number" name="basic_salary" id="emp-basic-salary" value="{{ old('basic_salary') }}" required min="0" step="0.01" inputmode="decimal" placeholder="0.00" class="emp-salary-calc">
            </div>
            <div class="emp-field">
                <label for="emp-monthly-salary">{{ __('Monthly gross salary') }} <span class="emp-req">*</span>{{ $salaryCurrencySuffix }}</label>
                <input type="number" name="salary" id="emp-monthly-salary" value="{{ old('salary') }}" required min="0" step="0.01" inputmode="decimal" placeholder="0.00" class="emp-salary-calc">
            </div>
            @foreach($allowanceTypes as $at)
                <div class="emp-field">
                    <label for="emp-allow-{{ $at->id }}">{{ __('Allowance: :name', ['name' => $at->name]) }}</label>
                    <input
                        type="number"
                        name="allowances[{{ $at->id }}]"
                        id="emp-allow-{{ $at->id }}"
                        value="{{ old('allowances.'.$at->id) }}"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="0.00"
                        class="emp-salary-calc emp-allow-input"
                        data-allowance-id="{{ $at->id }}"
                    >
                    @error('allowances.'.$at->id)
                        <span style="display:block;margin-top:4px;font-size:12px;color:color-mix(in srgb,#f87171 90%,var(--text));">{{ $message }}</span>
                    @enderror
                </div>
            @endforeach
            <div class="emp-field emp-field--full">
                <p id="emp-salary-sum-hint" class="muted" style="margin:0;font-size:12px;line-height:1.45;" aria-live="polite"></p>
            </div>
        </div>
    </fieldset>

    <fieldset class="emp-fieldset">
        <legend class="emp-legend">4. Emergency contact</legend>
        <div class="emp-form-rows">
            <div class="emp-field">
                <label for="emp-ec-name">Primary contact name <span class="emp-req">*</span></label>
                <input type="text" name="emergency_contact_name" id="emp-ec-name" value="{{ old('emergency_contact_name') }}" required maxlength="255" placeholder="Contact person">
            </div>
            <div class="emp-field">
                <label for="emp-ec-rel">Relationship <span class="emp-req">*</span></label>
                <input type="text" name="emergency_contact_relationship" id="emp-ec-rel" value="{{ old('emergency_contact_relationship') }}" required maxlength="120" placeholder="e.g. Spouse, Parent">
            </div>
            <div class="emp-field emp-field--full">
                <label for="emp-ec-phone">Emergency contact phone <span class="emp-req">*</span></label>
                <input type="tel" name="emergency_contact_phone" id="emp-ec-phone" value="{{ old('emergency_contact_phone') }}" required maxlength="40" placeholder="Contact phone">
            </div>
        </div>
    </fieldset>

    <fieldset class="emp-fieldset">
        <legend class="emp-legend">5. Bank account details</legend>
        <div class="emp-form-rows">
            <div class="emp-field emp-field--full">
                <label for="emp-bank-holder">Account holder name <span class="emp-req">*</span></label>
                <input type="text" name="bank_account_holder_name" id="emp-bank-holder" value="{{ old('bank_account_holder_name') }}" required maxlength="255" placeholder="As on bank statement">
            </div>
            <div class="emp-field">
                <label for="emp-bank-id">Bank name <span class="emp-req">*</span></label>
                @if($banks->isEmpty())
                    <p class="muted" style="margin:0;font-size:13px;line-height:1.45;">No banks in the directory yet. Run <code style="font-size:12px;">php artisan db:seed</code> (Account seeders include banks), then refresh this page.</p>
                @else
                    <select name="bank_id" id="emp-bank-id" required>
                        <option value="" disabled @selected(blank(old('bank_id')))>Choose …</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}" @selected((string) old('bank_id') === (string) $bank->id)>{{ $bank->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="emp-field">
                <label for="emp-bank-branch">Branch <span class="emp-req">*</span></label>
                <input type="text" name="bank_branch" id="emp-bank-branch" value="{{ old('bank_branch') }}" required maxlength="255" placeholder="Branch name or code">
            </div>
            <div class="emp-field emp-field--full">
                <label for="emp-bank-acct">Account number <span class="emp-req">*</span></label>
                <input type="text" name="bank_account_number" id="emp-bank-acct" value="{{ old('bank_account_number') }}" required maxlength="64" autocomplete="off" placeholder="Full account number">
            </div>
        </div>
    </fieldset>

    <fieldset class="emp-fieldset">
        <legend class="emp-legend">6. Statutory / tax (optional)</legend>
        <div class="emp-form-rows">
            <div class="emp-field">
                <label for="emp-epf">EPF number</label>
                <input type="text" name="epf_number" id="emp-epf" value="{{ old('epf_number') }}" maxlength="80">
            </div>
            <div class="emp-field">
                <label for="emp-etf">ETF number</label>
                <input type="text" name="etf_number" id="emp-etf" value="{{ old('etf_number') }}" maxlength="80">
            </div>
            <div class="emp-field emp-field--full">
                <label for="emp-tin">Tax ID (TIN)</label>
                <input type="text" name="tax_tin" id="emp-tin" value="{{ old('tax_tin') }}" maxlength="80">
            </div>
        </div>
    </fieldset>

    <div class="emp-form-actions">
        <button type="submit" class="linkbtn" @disabled($banks->isEmpty())>{{ $submitLabel }}</button>
    </div>
</form>
<script>
(function () {
    var sentinel = '{{ $pickNewRow }}';

    function wire(selId, wrapId, inpId) {
        var sel = document.getElementById(selId);
        var wrap = document.getElementById(wrapId);
        var inp = document.getElementById(inpId);
        if (!sel || !wrap || !inp) return;

        function sync() {
            var isNew = sel.value === sentinel;
            wrap.style.display = isNew ? 'block' : 'none';
            inp.required = isNew;
            if (!isNew) inp.value = '';
        }

        sel.addEventListener('change', sync);
        sync();
    }

    wire('emp-dept-id', 'emp-new-dept-wrap', 'emp-new-dept-name');
    wire('emp-job-title-id', 'emp-new-job-title-wrap', 'emp-new-job-title-name');

    function parseMoney(v) {
        if (v === '' || v === null || v === undefined) return 0;
        var n = parseFloat(String(v).replace(',', '.'));
        return isNaN(n) ? 0 : Math.max(0, n);
    }
    function fmt2(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }
    var basicEl = document.getElementById('emp-basic-salary');
    var grossEl = document.getElementById('emp-monthly-salary');
    var hintEl = document.getElementById('emp-salary-sum-hint');
    var allowInputs = document.querySelectorAll('.emp-allow-input');
    function recalcSalaryHint() {
        if (!hintEl) return;
        var basic = basicEl ? parseMoney(basicEl.value) : 0;
        var sumAllow = 0;
        allowInputs.forEach(function (inp) { sumAllow += parseMoney(inp.value); });
        var expected = basic + sumAllow;
        var gross = grossEl ? parseMoney(grossEl.value) : 0;
        var cur = @json($salaryCurrency !== '' ? $salaryCurrency.' ' : '');
        var msg = @json(__('Expected monthly gross (basic + allowances): ')) + cur + fmt2(expected);
        var mismatch = grossEl && grossEl.value !== '' && Math.abs(gross - expected) > 0.02;
        if (mismatch) {
            msg += ' — ' + @json(__('does not match the monthly gross field.'));
        }
        hintEl.textContent = msg;
        hintEl.classList.toggle('emp-salary-hint--bad', Boolean(mismatch));
    }
    [basicEl, grossEl].forEach(function (el) { el && el.addEventListener('input', recalcSalaryHint); el && el.addEventListener('change', recalcSalaryHint); });
    allowInputs.forEach(function (el) {
        el.addEventListener('input', recalcSalaryHint);
        el.addEventListener('change', recalcSalaryHint);
    });
    recalcSalaryHint();
})();
</script>
