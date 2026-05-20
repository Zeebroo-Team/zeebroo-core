@php
    $editing = $editingBill ?? null;
    $departmentsForBill = $departmentsForBill ?? collect();
    $branchesForBill = $branchesForBill ?? collect();
    $propertiesForBill = $propertiesForBill ?? collect();
    $employeesForBill = $employeesForBill ?? collect();
    $modificationsForBill = $modificationsForBill ?? collect();
    $rentalsForBillLink = $rentalsForBillLink ?? collect();
    $rpRelatedShow = (bool) old('rental_property_related', $editing?->rental_property_related ?? false);
    $pmOld = old('payment_mode', $editing?->payment_mode ?? \Modules\Account\Models\Bill::PAYMENT_MODE_RECURRING);
    $catOld = old('bill_category', $editing?->bill_category ?? \Modules\Account\Models\Bill::CATEGORY_OTHER);
    $varyUsageChecked = filter_var(old('amount_varies_by_usage', $editing?->amount_varies_by_usage ?? false), FILTER_VALIDATE_BOOLEAN);
    $allowSplitChecked = filter_var(old('allow_split_payment', $editing?->allow_split_payment ?? true), FILTER_VALIDATE_BOOLEAN);
    $assignmentTypeOld = old('assignment_type');
    if ($assignmentTypeOld === null) {
        if ($rpRelatedShow) {
            $assignmentTypeOld = 'rental';
        } elseif (!empty($editing?->modification_id)) {
            $assignmentTypeOld = 'modification';
        } elseif (!empty($editing?->employee_id)) {
            $assignmentTypeOld = 'employee';
        } elseif (!empty($editing?->property_id)) {
            $assignmentTypeOld = 'property';
        } elseif (!empty($editing?->department_id)) {
            $assignmentTypeOld = 'department';
        } elseif (!empty($editing?->branch_id)) {
            $assignmentTypeOld = 'branch';
        } else {
            $assignmentTypeOld = 'none';
        }
    }
@endphp
@include('account::bills.partials.bill-rental-field-styles')
@if($errors->any())
    <div class="rental-alert rental-alert--err {{ $billFormErrorBannerClass ?? '' }}" role="alert">
        <i class="fa fa-circle-exclamation"></i>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

<form id="{{ $billFormId ?? 'bill-form' }}" method="post" action="{{ $billFormAction ?? route('account.bills.store') }}" class="rental-fields">
    @csrf
    @isset($billFormMethod)
        @method($billFormMethod)
    @endisset
    <div class="rental-form-section">
        <div class="rental-form-section__head"><i class="fa fa-file-invoice-dollar"></i> Bill</div>
        <div class="rental-fields-grid">
            <div class="rental-field rental-field--full">
                <label for="bill-name">Name</label>
                <input id="bill-name" type="text" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="255" placeholder="Short label on your dashboard">
                @error('name')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
            <div class="rental-field">
                <label for="bill-category">Bill type</label>
                <select id="bill-category" name="bill_category" class="rental-select" required>
                    @foreach($billCategories as $value => $label)
                        <option value="{{ $value }}" @selected((string) $catOld === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('bill_category')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
            <div class="rental-field rental-field--full" id="bill-category-other-wrap" style="{{ (string) $catOld === \Modules\Account\Models\Bill::CATEGORY_OTHER ? '' : 'display:none;' }}">
                <label for="bill-category-other">Specify type <span class="rental-hint">when Other</span></label>
                <input id="bill-category-other" type="text" name="bill_category_other" value="{{ old('bill_category_other', $editing?->bill_category_other) }}" maxlength="255" placeholder="e.g. Security, Software subscription">
                @error('bill_category_other')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
            <div class="rental-field rental-field--full">
                <label for="bill-description">Description <span class="rental-hint">optional</span></label>
                <textarea id="bill-description" name="description" rows="2" maxlength="2000" placeholder="Vendor, account number, or service notes">{{ old('description', $editing?->description) }}</textarea>
                @error('description')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
            <div class="rental-field rental-field--full bill-pay-pattern">
                <span class="bill-pay-pattern__legend" id="bill-payment-pattern-heading">Payment pattern</span>
                <p class="bill-pay-pattern__hint">Recurring bills repeat on a schedule you control. One-time is a single payment with a due or anchor date.</p>
                <div class="bill-pay-pattern__choices" role="radiogroup" aria-labelledby="bill-payment-pattern-heading">
                    <label class="bill-pay-pattern__option">
                        <span class="bill-pay-pattern__option-inner">
                            <span class="bill-pay-pattern__ico" aria-hidden="true"><i class="fa fa-rotate"></i></span>
                            <span class="bill-pay-pattern__body">
                                <span class="bill-pay-pattern__title">{{ $paymentModes[\Modules\Account\Models\Bill::PAYMENT_MODE_RECURRING] ?? 'Recurring' }}</span>
                                <span class="bill-pay-pattern__desc">Cycles until the schedule end year · set cadence (month / year / day)</span>
                            </span>
                            <span class="bill-pay-pattern__radio">
                                <input type="radio" name="payment_mode" value="{{ \Modules\Account\Models\Bill::PAYMENT_MODE_RECURRING }}" @checked((string) $pmOld === \Modules\Account\Models\Bill::PAYMENT_MODE_RECURRING)>
                            </span>
                        </span>
                    </label>
                    <label class="bill-pay-pattern__option">
                        <span class="bill-pay-pattern__option-inner">
                            <span class="bill-pay-pattern__ico" aria-hidden="true"><i class="fa fa-money-bill-wave"></i></span>
                            <span class="bill-pay-pattern__body">
                                <span class="bill-pay-pattern__title">{{ $paymentModes[\Modules\Account\Models\Bill::PAYMENT_MODE_ONE_TIME] ?? 'One-time' }}</span>
                                <span class="bill-pay-pattern__desc">One payment total · requires a due date or first installment date</span>
                            </span>
                            <span class="bill-pay-pattern__radio">
                                <input type="radio" name="payment_mode" value="{{ \Modules\Account\Models\Bill::PAYMENT_MODE_ONE_TIME }}" @checked((string) $pmOld === \Modules\Account\Models\Bill::PAYMENT_MODE_ONE_TIME)>
                            </span>
                        </span>
                    </label>
                </div>
                @error('payment_mode')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
            <div class="rental-field" id="bill-agreement-year-field">
                <label for="bill-agreement-year">Schedule through (year)</label>
                <input id="bill-agreement-year" type="number" name="agreement_valid_until_year" value="{{ old('agreement_valid_until_year', $editing?->agreement_valid_until_year ?? ((int) date('Y') + 1)) }}" min="2000" max="2100" step="1" inputmode="numeric"
                    @if((string) $pmOld !== \Modules\Account\Models\Bill::PAYMENT_MODE_RECURRING) disabled @else required @endif>
                @error('agreement_valid_until_year')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    @if($business)
        <div class="rental-form-section">
            <div class="rental-form-section__head"><i class="fa fa-diagram-project" aria-hidden="true"></i> Assignment</div>
            <p class="bill-rental-field__lead">Choose one assignment target. Then select the matching item from the dropdown.</p>
            <div class="rental-fields-grid">
                <div class="rental-field rental-field--full">
                    <label for="bill-assignment-type">Assign to</label>
                    <select id="bill-assignment-type" name="assignment_type" class="rental-select">
                        <option value="none" @selected((string) $assignmentTypeOld === 'none')>None</option>
                        <option value="branch" @selected((string) $assignmentTypeOld === 'branch')>Branch</option>
                        <option value="department" @selected((string) $assignmentTypeOld === 'department')>Department</option>
                        <option value="property" @selected((string) $assignmentTypeOld === 'property')>Property</option>
                        <option value="employee" @selected((string) $assignmentTypeOld === 'employee')>Employee</option>
                        <option value="modification" @selected((string) $assignmentTypeOld === 'modification')>Modification</option>
                        <option value="rental" @selected((string) $assignmentTypeOld === 'rental')>Rental property</option>
                    </select>
                    @error('assignment_type')<span class="rental-field-err">{{ $message }}</span>@enderror
                </div>
                <div class="rental-field rental-field--full" id="bill-assign-branch-wrap" @if((string) $assignmentTypeOld !== 'branch') hidden @endif>
                    <label for="bill-branch-id">Select branch</label>
                    <select id="bill-branch-id" name="branch_id" class="rental-select" @if((string) $assignmentTypeOld !== 'branch') disabled @endif>
                        <option value="">No branch</option>
                        @foreach($branchesForBill as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id', $editing?->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')<span class="rental-field-err">{{ $message }}</span>@enderror
                </div>
                <div class="rental-field rental-field--full" id="bill-assign-department-wrap" @if((string) $assignmentTypeOld !== 'department') hidden @endif>
                    <label for="bill-department-id">Select department</label>
                    <select id="bill-department-id" name="department_id" class="rental-select" @if((string) $assignmentTypeOld !== 'department') disabled @endif>
                        <option value="">No department</option>
                        @foreach($departmentsForBill as $dept)
                            <option value="{{ $dept->id }}" @selected((string) old('department_id', $editing?->department_id) === (string) $dept->id)>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    @error('department_id')<span class="rental-field-err">{{ $message }}</span>@enderror
                </div>
                <div class="rental-field rental-field--full" id="bill-assign-property-wrap" @if((string) $assignmentTypeOld !== 'property') hidden @endif>
                    <label for="bill-property-id">Assign to property</label>
                    <select id="bill-property-id" name="property_id" class="rental-select" @if((string) $assignmentTypeOld !== 'property') disabled @endif>
                        <option value="">No property</option>
                        @foreach($propertiesForBill as $prop)
                            <option value="{{ $prop->id }}" @selected((string) old('property_id', $editing?->property_id) === (string) $prop->id)>
                                {{ $prop->property_name }} · {{ $prop->property_type }}
                            </option>
                        @endforeach
                    </select>
                    @error('property_id')<span class="rental-field-err">{{ $message }}</span>@enderror
                </div>
                <div class="rental-field rental-field--full" id="bill-assign-employee-wrap" @if((string) $assignmentTypeOld !== 'employee') hidden @endif>
                    <label for="bill-employee-id">Assign to employee</label>
                    <select id="bill-employee-id" name="employee_id" class="rental-select" @if((string) $assignmentTypeOld !== 'employee') disabled @endif>
                        <option value="">No employee</option>
                        @foreach($employeesForBill as $emp)
                            <option value="{{ $emp->id }}" @selected((string) old('employee_id', $editing?->employee_id) === (string) $emp->id)>
                                {{ $emp->full_name }}@if($emp->employee_id) · {{ $emp->employee_id }}@endif
                            </option>
                        @endforeach
                    </select>
                    @error('employee_id')<span class="rental-field-err">{{ $message }}</span>@enderror
                </div>
                <div class="rental-field rental-field--full" id="bill-assign-modification-wrap" @if((string) $assignmentTypeOld !== 'modification') hidden @endif>
                    <label for="bill-modification-id">Select modification</label>
                    <select id="bill-modification-id" name="modification_id" class="rental-select" @if((string) $assignmentTypeOld !== 'modification') disabled @endif>
                        <option value="">No modification</option>
                        @foreach($modificationsForBill as $mod)
                            <option value="{{ $mod->id }}" @selected((string) old('modification_id', $editing?->modification_id) === (string) $mod->id)>
                                {{ $mod->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('modification_id')<span class="rental-field-err">{{ $message }}</span>@enderror
                </div>
                <div class="rental-field rental-field--full" id="bill-assign-rental-wrap" @if((string) $assignmentTypeOld !== 'rental') hidden @endif>
                    <label for="bill-rental-id">Select rental property</label>
                    <select id="bill-rental-id" name="rental_id" class="rental-select" @if((string) $assignmentTypeOld !== 'rental') disabled @endif>
                        <option value="">Select a rental…</option>
                        @foreach($rentalsForBillLink as $r)
                            <option value="{{ $r->id }}" @selected((string) old('rental_id', $editing?->rental_id) === (string) $r->id)>
                                {{ $r->property_type }}@if($r->warehouse) · {{ $r->warehouse->name }}@endif
                            </option>
                        @endforeach
                    </select>
                    @error('rental_id')<span class="rental-field-err">{{ $message }}</span>@enderror
                    @error('rental_property_related')<span class="rental-field-err">{{ $message }}</span>@enderror
                </div>
                <input type="hidden" name="rental_property_related" id="bill-rental-related-flag" value="{{ (string) $assignmentTypeOld === 'rental' ? '1' : '0' }}">
            </div>
        </div>
    @endif

    <div class="rental-form-section">
        <div class="rental-form-section__head"><i class="fa fa-wallet"></i> Payment</div>
        <div class="rental-fields-grid">
            <div class="rental-field rental-field--full">
                <label for="bill-deduct">Preferred debit account</label>
                <select id="bill-deduct" name="deduct_account_id" class="rental-select">
                    <option value="">None — pick when recording each payment</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected((string) old('deduct_account_id', $editing?->deduct_account_id) === (string) $acc->id)>{{ $acc->deductOptionLabel() }}</option>
                    @endforeach
                </select>
                @error('deduct_account_id')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
            <div class="rental-field">
                <label for="bill-recurring-cost"><span id="bill-amount-label">Amount</span> <span class="rental-hint" id="bill-amount-hint">per billing cycle</span></label>
                <input id="bill-recurring-cost" type="number" name="recurring_cost" value="{{ old('recurring_cost', $editing !== null ? number_format((float) $editing->recurring_cost, 2, '.', '') : '') }}" min="0" step="0.01" inputmode="decimal" placeholder="0.00" @unless($varyUsageChecked) required @endunless>
                @error('recurring_cost')<span class="rental-field-err">{{ $message }}</span>@enderror
                <small id="bill-recurring-cost-help" style="display:block;margin-top:6px;font-size:11px;color:var(--muted);font-weight:500;line-height:1.4;"></small>
            </div>
            <div class="rental-field rental-field--full">
                <input type="hidden" name="amount_varies_by_usage" value="0">
                <label for="bill-amount-varies" style="margin:0;font-weight:600;display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
                    <span style="padding-top:2px;"><input type="checkbox" name="amount_varies_by_usage" id="bill-amount-varies" value="1" @checked($varyUsageChecked)></span>
                    <span style="flex:1;min-width:0;">
                        <span style="display:block;">Amount varies by usage or invoice</span>
                        <span style="display:block;margin-top:3px;font-size:11px;font-weight:500;color:var(--muted);">For water, utilities, or any bill where each period total is confirmed when you receive the invoice. You declare the charge when recording the first payment for that billing date.</span>
                    </span>
                </label>
                @error('amount_varies_by_usage')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
            <div class="rental-field rental-field--full">
                <input type="hidden" name="allow_split_payment" value="0">
                <label for="bill-allow-split" style="margin:0;font-weight:600;display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
                    <span style="padding-top:2px;"><input type="checkbox" name="allow_split_payment" id="bill-allow-split" value="1" @checked($allowSplitChecked)></span>
                    <span style="flex:1;min-width:0;">
                        <span style="display:block;">Allow splitting one payment across multiple debit accounts</span>
                        <span style="display:block;margin-top:3px;font-size:11px;font-weight:500;color:var(--muted);">Turn off when you always pay each bill from a single account.</span>
                    </span>
                </label>
                @error('allow_split_payment')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
            <div class="rental-field" id="bill-recurring-type-field">
                <label for="bill-recurring-type">Billing cadence</label>
                <select id="bill-recurring-type" name="recurring_type" class="rental-select"
                    @if((string) $pmOld !== \Modules\Account\Models\Bill::PAYMENT_MODE_RECURRING) disabled @else required @endif>
                    @foreach($recurringTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('recurring_type', $editing?->recurring_type ?? \Modules\Account\Models\Bill::RECURRING_PER_MONTH) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('recurring_type')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
            <div class="rental-field rental-field--full">
                <label for="bill-remind-before">Remind before <span class="rental-hint">days before period end — delivery TBD</span></label>
                <input id="bill-remind-before" type="number" name="remind_before_days" value="{{ old('remind_before_days', $editing?->remind_before_days) }}" min="0" max="366" step="1" inputmode="numeric" placeholder="e.g. 7 — blank for none">
                @error('remind_before_days')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
            <div class="rental-field">
                <label for="bill-due-date">Due date <span class="rental-hint" id="bill-due-hint">optional · schedule anchor</span></label>
                <input id="bill-due-date" type="date" name="due_date" value="{{ old('due_date', $editing?->due_date?->format('Y-m-d')) }}">
                @error('due_date')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
            <div class="rental-field">
                <label for="bill-first-installment">First installment due <span class="rental-hint">optional — alternative anchor</span></label>
                <input id="bill-first-installment" type="date" name="first_installment_due_date" value="{{ old('first_installment_due_date', $editing?->first_installment_due_date?->format('Y-m-d')) }}">
                @error('first_installment_due_date')<span class="rental-field-err">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    <div class="rental-form-section">
        <div class="rental-form-section__head"><i class="fa fa-note-sticky"></i> Notes</div>
        <div class="rental-field rental-field--full">
            <label for="bill-notes">Internal notes</label>
            <textarea id="bill-notes" name="notes" rows="3" maxlength="5000" placeholder="Reference numbers or reminders">{{ old('notes', $editing?->notes) }}</textarea>
            @error('notes')<span class="rental-field-err">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="rental-submit-wrap">
        <button type="submit" class="linkbtn"><i class="fa fa-check"></i>{{ $billSubmitLabel ?? 'Save bill' }}</button>
        <span class="rental-submit-note">Uses the business selected in the top navigation.</span>
    </div>
</form>

<script>
(function(){
    var cat = document.getElementById('bill-category');
    var otherWrap = document.getElementById('bill-category-other-wrap');
    function syncCategoryOther(){
        if(!cat || !otherWrap) return;
        otherWrap.style.display = cat.value === 'other' ? '' : 'none';
    }
    cat && cat.addEventListener('change', function(){
        syncCategoryOther();
        if(cat.value === waterCat && variesCb && !variesCb.checked){
            variesCb.checked = true;
            syncAmountVariesUi();
        }
    });

    var recurring = @json(\Modules\Account\Models\Bill::PAYMENT_MODE_RECURRING);
    var oneTime = @json(\Modules\Account\Models\Bill::PAYMENT_MODE_ONE_TIME);
    var radios = document.querySelectorAll('input[name="payment_mode"]');
    var yearInp = document.getElementById('bill-agreement-year');
    var yearField = document.getElementById('bill-agreement-year-field');
    var cadenceSel = document.getElementById('bill-recurring-type');
    var cadenceField = document.getElementById('bill-recurring-type-field');
    var amtLabel = document.getElementById('bill-amount-label');
    var amtHint = document.getElementById('bill-amount-hint');
    var dueHint = document.getElementById('bill-due-hint');
    var variesCb = document.getElementById('bill-amount-varies');
    var recurringCostEl = document.getElementById('bill-recurring-cost');
    var costHelpEl = document.getElementById('bill-recurring-cost-help');
    var waterCat = @json(\Modules\Account\Models\Bill::CATEGORY_WATER);

    function syncAmountVariesUi(){
        if(!recurringCostEl) return;
        var on = variesCb ? variesCb.checked : false;
        recurringCostEl.required = !on;
        if(costHelpEl){
            costHelpEl.textContent = on
                ? 'Optional typical amount shown on reminders; the actual total is locked in when you enter it on payment.'
                : '';
        }
    }

    variesCb && variesCb.addEventListener('change', syncAmountVariesUi);
    syncAmountVariesUi();

    function syncPaymentMode(){
        var mode = recurring;
        radios.forEach(function(r){ if(r.checked) mode = r.value; });
        var isRec = mode === recurring;
        if(yearInp){
            yearInp.disabled = !isRec;
            yearInp.required = isRec;
        }
        if(cadenceSel){
            cadenceSel.disabled = !isRec;
            cadenceSel.required = isRec;
        }
        if(yearField) yearField.style.opacity = isRec ? '' : '0.55';
        if(cadenceField) cadenceField.style.opacity = isRec ? '' : '0.55';
        if(amtLabel) amtLabel.textContent = isRec ? 'Amount' : 'Payment amount';
        if(amtHint) amtHint.textContent = isRec ? 'per billing cycle' : 'one-time total';
        if(dueHint) dueHint.textContent = isRec ? 'optional · schedule anchor' : 'required for one-time (or use first installment)';
    }
    radios.forEach(function(r){ r.addEventListener('change', syncPaymentMode); });
    syncPaymentMode();
    syncCategoryOther();

    var assignmentTypeSel = document.getElementById('bill-assignment-type');
    var assignmentBranchWrap = document.getElementById('bill-assign-branch-wrap');
    var assignmentDepartmentWrap = document.getElementById('bill-assign-department-wrap');
    var assignmentPropertyWrap = document.getElementById('bill-assign-property-wrap');
    var assignmentEmployeeWrap = document.getElementById('bill-assign-employee-wrap');
    var assignmentModificationWrap = document.getElementById('bill-assign-modification-wrap');
    var assignmentRentalWrap = document.getElementById('bill-assign-rental-wrap');
    var assignmentBranchSel = document.getElementById('bill-branch-id');
    var assignmentDepartmentSel = document.getElementById('bill-department-id');
    var assignmentPropertySel = document.getElementById('bill-property-id');
    var assignmentEmployeeSel = document.getElementById('bill-employee-id');
    var assignmentModificationSel = document.getElementById('bill-modification-id');
    var assignmentRentalSel = document.getElementById('bill-rental-id');
    var rentalRelatedFlag = document.getElementById('bill-rental-related-flag');

    function syncAssignmentSelection(){
        if(!assignmentTypeSel) return;
        var type = assignmentTypeSel.value || 'none';

        if(assignmentBranchWrap) assignmentBranchWrap.hidden = type !== 'branch';
        if(assignmentDepartmentWrap) assignmentDepartmentWrap.hidden = type !== 'department';
        if(assignmentPropertyWrap) assignmentPropertyWrap.hidden = type !== 'property';
        if(assignmentEmployeeWrap) assignmentEmployeeWrap.hidden = type !== 'employee';
        if(assignmentModificationWrap) assignmentModificationWrap.hidden = type !== 'modification';
        if(assignmentRentalWrap) assignmentRentalWrap.hidden = type !== 'rental';

        if(assignmentBranchSel) assignmentBranchSel.disabled = type !== 'branch';
        if(assignmentDepartmentSel) assignmentDepartmentSel.disabled = type !== 'department';
        if(assignmentPropertySel) assignmentPropertySel.disabled = type !== 'property';
        if(assignmentEmployeeSel) assignmentEmployeeSel.disabled = type !== 'employee';
        if(assignmentModificationSel) assignmentModificationSel.disabled = type !== 'modification';
        if(assignmentRentalSel) assignmentRentalSel.disabled = type !== 'rental';

        if(rentalRelatedFlag) rentalRelatedFlag.value = type === 'rental' ? '1' : '0';
    }
    assignmentTypeSel && assignmentTypeSel.addEventListener('change', syncAssignmentSelection);
    syncAssignmentSelection();
})();
</script>
