<form method="post" action="{{ route('account.investments.store') }}" class="js-inv-type-form">
    @csrf
    <div class="inv-form-section">
        <div class="inv-fields inv-fields--2">
            <div class="inv-field">
                <label for="inv-name">Name</label>
                <input type="text" name="name" id="inv-name" maxlength="255" required value="{{ old('name') }}" placeholder="e.g. Fixed deposit — HNB">
            </div>
            <div class="inv-field">
                <label for="inv-type">Type</label>
                <select name="investment_type" id="inv-type" required>
                    @foreach($investmentTypes as $key => $label)
                        <option value="{{ $key }}" @selected(old('investment_type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="inv-field js-inv-type-other-wrap" style="display:none;margin-top:10px;">
            <label for="inv-type-other">Describe this investment type</label>
            <input type="text" name="investment_type_other" id="inv-type-other" maxlength="255" value="{{ old('investment_type_other') }}">
        </div>
        <div class="inv-fields inv-fields--2" style="margin-top:10px;">
            <div class="inv-field">
                <label for="inv-provider">Provider / institution</label>
                <input type="text" name="provider" id="inv-provider" maxlength="255" value="{{ old('provider') }}">
            </div>
            <div class="inv-field">
                <label for="inv-reference">Reference number</label>
                <input type="text" name="reference_number" id="inv-reference" maxlength="255" value="{{ old('reference_number') }}">
            </div>
        </div>
        <div class="inv-field" style="margin-top:10px;">
            <label for="inv-description">Description</label>
            <textarea name="description" id="inv-description" maxlength="2000">{{ old('description') }}</textarea>
        </div>
    </div>

    <div class="inv-form-section">
        <div class="inv-fields inv-fields--2">
            <div class="inv-field">
                <label for="inv-payment-mode">Contribution schedule</label>
                <select name="payment_mode" id="inv-payment-mode" required>
                    <option value="recurring" @selected(old('payment_mode', 'recurring') === 'recurring')>Recurring</option>
                    <option value="one_time" @selected(old('payment_mode') === 'one_time')>One-time</option>
                </select>
            </div>
            <div class="inv-field">
                <label for="inv-contribution-amount">Contribution amount</label>
                <input type="number" step="0.01" min="0.01" name="contribution_amount" id="inv-contribution-amount" required value="{{ old('contribution_amount') }}">
            </div>
        </div>
        <div class="inv-fields inv-fields--2 js-inv-recurring-wrap" style="margin-top:10px;">
            <div class="inv-field">
                <label for="inv-recurring-type">Cadence</label>
                <select name="recurring_type" id="inv-recurring-type">
                    <option value="per_month" @selected(old('recurring_type', 'per_month') === 'per_month')>Per month</option>
                    <option value="per_day" @selected(old('recurring_type') === 'per_day')>Per day</option>
                    <option value="per_year" @selected(old('recurring_type') === 'per_year')>Per year</option>
                </select>
            </div>
            <div class="inv-field">
                <label for="inv-schedule-until">Schedule valid until (year)</label>
                <input type="number" min="2000" max="2100" name="schedule_valid_until_year" id="inv-schedule-until" value="{{ old('schedule_valid_until_year', now()->addYears(5)->year) }}">
            </div>
        </div>
        <div class="inv-fields inv-fields--2" style="margin-top:10px;">
            <div class="inv-field">
                <label for="inv-start-date">Start date</label>
                <input type="date" name="start_date" id="inv-start-date" required value="{{ old('start_date', now()->toDateString()) }}">
            </div>
            <div class="inv-field">
                <label for="inv-maturity-date">Maturity date (optional)</label>
                <input type="date" name="maturity_date" id="inv-maturity-date" value="{{ old('maturity_date') }}">
            </div>
        </div>
    </div>

    <div class="inv-form-section">
        <div class="inv-fields inv-fields--2">
            <div class="inv-field">
                <label for="inv-expected-return">Expected annual return (%)</label>
                <input type="number" step="0.01" min="0" max="1000" name="expected_return_rate" id="inv-expected-return" value="{{ old('expected_return_rate') }}">
            </div>
            <div class="inv-field">
                <label for="inv-target-amount">Target / goal amount</label>
                <input type="number" step="0.01" min="0" name="target_amount" id="inv-target-amount" value="{{ old('target_amount') }}">
            </div>
        </div>
        <div class="inv-fields inv-fields--2" style="margin-top:10px;">
            <div class="inv-field">
                <label for="inv-deduct-account">Default debit account</label>
                <select name="deduct_account_id" id="inv-deduct-account">
                    <option value="">— choose per contribution —</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected((int) old('deduct_account_id') === (int) $acc->id)>{{ $acc->deductOptionLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="inv-field">
                <label for="inv-remind-days">Remind before (days)</label>
                <input type="number" min="0" max="366" name="remind_before_days" id="inv-remind-days" value="{{ old('remind_before_days') }}">
            </div>
        </div>
        <div class="inv-field" style="margin-top:10px;">
            <label for="inv-notes">Notes</label>
            <textarea name="notes" id="inv-notes" maxlength="5000">{{ old('notes') }}</textarea>
        </div>
    </div>

    <button type="submit" class="inv-btn--primary" style="width:100%;justify-content:center;"><i class="fa fa-plus"></i> Save investment</button>
</form>
