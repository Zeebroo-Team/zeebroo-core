@if($errors->any())
    <div class="loan-alert loan-alert--err {{ $loanFormErrorBannerClass ?? '' }}" role="alert">
        <i class="fa fa-circle-exclamation"></i>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

<aside id="loan-preview" class="loan-preview loan-preview--idle" aria-live="polite">
    <div class="loan-preview__head">
        <div class="loan-preview__title"><i class="fa fa-chart-line"></i> Live preview</div>
        <span id="loan-preview-source" class="loan-preview__badge"></span>
    </div>
    <div id="loan-preview-grid" class="loan-preview__grid">
        <div class="loan-prev-dial loan-prev-dial--hero">
            <div class="loan-prev-dial__lab">Payment per period</div>
            <div id="loan-pv-payment" class="loan-prev-dial__val">—</div>
        </div>
        <div class="loan-prev-dial">
            <div class="loan-prev-dial__lab">Principal</div>
            <div id="loan-pv-principal" class="loan-prev-dial__val">—</div>
        </div>
        <div class="loan-prev-dial">
            <div class="loan-prev-dial__lab">Interest (total)</div>
            <div id="loan-pv-interest-total" class="loan-prev-dial__val">—</div>
        </div>
        <div class="loan-prev-dial">
            <div class="loan-prev-dial__lab">Full repayment</div>
            <div id="loan-pv-total-repay" class="loan-prev-dial__val">—</div>
        </div>
        <div class="loan-prev-dial">
            <div class="loan-prev-dial__lab">Periods (n)</div>
            <div id="loan-pv-n" class="loan-prev-dial__val">—</div>
        </div>
        <div class="loan-prev-dial">
            <div class="loan-prev-dial__lab">Rate / period</div>
            <div id="loan-pv-periodic-rate" class="loan-prev-dial__val">—</div>
        </div>
    </div>
    <div id="loan-preview-foot" class="loan-preview__foot"></div>
</aside>

<form id="loan-form" method="post" action="{{ route('account.loans.store') }}" class="loan-fields" style="max-width:none;">
    @csrf
    <div class="loan-form-section">
        <div class="loan-form-section__head"><i class="fa fa-heading"></i> Identity</div>
        <div class="loan-fields loan-fields--2">
            <div class="loan-field">
                <label for="loan-name">Facility name</label>
                <input id="loan-name" type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Working capital facility">
                @error('name')<span style="color:#f87171;font-size:12px;margin-top:4px;display:block;">{{ $message }}</span>@enderror
            </div>
            <div class="loan-field">
                <label for="loan-bank">Lender bank</label>
                <select id="loan-bank" name="bank_id" required>
                    <option value="">Select bank</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}" @selected(old('bank_id') == $bank->id)>{{ $bank->name }}</option>
                    @endforeach
                </select>
                @error('bank_id')<span style="color:#f87171;font-size:12px;margin-top:4px;display:block;">{{ $message }}</span>@enderror
            </div>
            <div class="loan-field" style="grid-column:1/-1;">
                <label for="loan-description">Description <span class="loan-muted-hint">optional notes</span></label>
                <textarea id="loan-description" name="description" maxlength="5000" rows="3" placeholder="Terms, collateral, internal notes…">{{ old('description') }}</textarea>
                @error('description')<span style="color:#f87171;font-size:12px;margin-top:4px;display:block;">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    <div class="loan-form-section">
        <div class="loan-form-section__head"><i class="fa fa-percent"></i> Amount & rates</div>
        <div class="loan-fields loan-fields--2">
            <div class="loan-field">
                <label for="loan-principal">Borrowed amount</label>
                <input id="loan-principal" type="number" name="borrowed_amount" value="{{ old('borrowed_amount') }}" step="0.01" min="0" required inputmode="decimal" placeholder="0.00">
                @error('borrowed_amount')<span style="color:#f87171;font-size:12px;margin-top:4px;display:block;">{{ $message }}</span>@enderror
            </div>
            <div class="loan-field">
                <label for="loan-rate-type">Interest type</label>
                <select id="loan-rate-type" name="interest_rate_type" required>
                    @foreach($interestRateTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('interest_rate_type', 'percentage') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('interest_rate_type')<span style="color:#f87171;font-size:12px;margin-top:4px;display:block;">{{ $message }}</span>@enderror
            </div>
            <div class="loan-field">
                <label for="loan-rate">Interest rate</label>
                <input id="loan-rate" type="number" name="interest_rate" value="{{ old('interest_rate') }}" step="0.0001" min="0" required placeholder="e.g. 12.5 for %">
                @error('interest_rate')<span style="color:#f87171;font-size:12px;margin-top:4px;display:block;">{{ $message }}</span>@enderror
            </div>
            <div class="loan-field">
                <label for="loan-recurring">Recurring cadence</label>
                <select id="loan-recurring" name="recurring_type" required>
                    @foreach($recurringTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('recurring_type', 'per_month') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('recurring_type')<span style="color:#f87171;font-size:12px;margin-top:4px;display:block;">{{ $message }}</span>@enderror
            </div>
            <div class="loan-field">
                <label for="loan-preview-periods">Estimated periods <span class="loan-muted-hint">overrides assumed term below</span></label>
                <input id="loan-preview-periods" type="number" min="1" step="1" inputmode="numeric" placeholder="e.g. 12" autocomplete="off">
            </div>
        </div>
    </div>

    <div class="loan-form-section">
        <div class="loan-form-section__head"><i class="fa fa-arrows-rotate"></i> Recurring Settings</div>
        <div class="loan-fields loan-fields--2">
            <div class="loan-field">
                <label for="loan-first-due">First installment due</label>
                <input id="loan-first-due" type="date" name="first_installment_due_date" value="{{ old('first_installment_due_date') }}">
                @error('first_installment_due_date')<span style="color:#f87171;font-size:12px;margin-top:4px;display:block;">{{ $message }}</span>@enderror
            </div>
            <div class="loan-field">
                <label for="loan-end">Loan ending date <span class="loan-muted-hint">last installment · auto-filled from cadence</span></label>
                <input id="loan-end" type="date" name="loan_ending_date" value="{{ old('loan_ending_date') }}" autocomplete="off">
                <p style="margin:4px 0 0;font-size:10px;line-height:1.4;color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0;">Picking first installment fills this based on recurring cadence. If periods is blank we assume 12 (monthly), 30 (daily), or 5 (yearly). Change this field anytime to freeze it.</p>
                @error('loan_ending_date')<span style="color:#f87171;font-size:12px;margin-top:4px;display:block;">{{ $message }}</span>@enderror
            </div>
            <div class="loan-field" style="grid-column:1/-1;">
                <label for="loan-deduct">Deduct from account</label>
                <select id="loan-deduct" name="deduct_account_id" title="Each line: account · bank · type · current balance">
                    <option value="">None — no deduction account</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected((string) old('deduct_account_id') === (string) $acc->id)>
                            {{ $acc->deductOptionLabel() }}
                        </option>
                    @endforeach
                </select>
                @error('deduct_account_id')<span style="color:#f87171;font-size:12px;margin-top:4px;display:block;">{{ $message }}</span>@enderror
            </div>
            <div class="loan-field" style="grid-column:1/-1;">
                <label for="loan-remind-before">Remind before <span class="loan-muted-hint">days ahead of installment due date</span></label>
                <input id="loan-remind-before" type="number" name="remind_before_days" value="{{ old('remind_before_days') }}" min="0" max="365" step="1" inputmode="numeric" placeholder="e.g. 7 — leave blank for no reminder" autocomplete="off">
                <p style="margin:4px 0 0;font-size:10px;line-height:1.4;color:var(--muted);text-transform:none;letter-spacing:0;font-weight:400;">
                    Enter how many calendar days ahead you want us to notify you before each installment (e.g. 7 = one week ahead). Applies once reminder delivery is wired up.
                </p>
                @error('remind_before_days')<span style="color:#f87171;font-size:12px;margin-top:4px;display:block;">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    <div class="loan-submit-wrap">
        <button type="submit" class="linkbtn"><i class="fa fa-check"></i> Save loan</button>
        <span class="loan-submit-note">Uses the business selected in the top navigation.</span>
    </div>
</form>
