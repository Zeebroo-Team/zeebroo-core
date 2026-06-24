@once
<style>
.mod-page,.mod-modal,.mod-inline-create,.mod-empty{--mod-r:12px;--mod-r-sm:9px;}
.mod-form-section{margin-bottom:14px;padding:14px 16px;border-radius:var(--mod-r-sm,9px);border:1px solid color-mix(in srgb,var(--border) 88%,transparent);background:linear-gradient(180deg,color-mix(in srgb,var(--card) 97%,transparent),color-mix(in srgb,var(--card) 92%,transparent));box-shadow:0 8px 24px -22px rgba(0,0,0,.2);}
.mod-form-section__head{display:flex;align-items:center;gap:10px;margin-bottom:12px;font-size:13px;font-weight:800;color:var(--text);letter-spacing:-.01em;}
.mod-form-section__head i{color:var(--primary);width:20px;text-align:center;}
.mod-fields-grid{display:grid;gap:12px;}
@media(min-width:580px){.mod-fields-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 18px;}}
.mod-field--full{grid-column:1/-1;}
.mod-field label,.mod-lbl{display:block;margin-bottom:5px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.mod-field input,.mod-field select,.mod-field textarea,.mod-select{width:100%;box-sizing:border-box;padding:10px 12px;font-size:14px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:var(--text);transition:border-color .15s ease,box-shadow .15s ease;font-family:inherit;}
.mod-field textarea{min-height:76px;resize:vertical;line-height:1.45;}
.mod-field input:focus,.mod-field select:focus,.mod-field textarea:focus{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));outline:none;box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 16%,transparent);}
.mod-field-err{display:block;color:#f87171;font-size:12px;margin-top:5px;line-height:1.35;font-weight:600;}
</style>
@endonce

@php
    $propertiesForAssignment = $propertiesForAssignment ?? collect();
    $propertyTypeOptions = \Modules\Account\Models\Property::typeOptions();
    $renovationOptions = \Modules\Modification\Models\Modification::renovationTypeLabels();
    $propertyWorkTypeOptions = \Modules\Modification\Models\Modification::propertyWorkTypeLabels();
    $assignmentType = (string) old('assignment_type', 'renovation');
    $assignmentRef = (string) old('assignment_reference', '');
    $propertyWorkType = (string) old('property_work_type', \Modules\Modification\Models\Modification::PROPERTY_WORK_TYPE_REPAIR);
    $propertyWorkTypeOther = (string) old('property_work_type_other', '');
    $customRenovationOption = ($assignmentType === 'renovation'
        && $assignmentRef !== ''
        && ! array_key_exists($assignmentRef, $renovationOptions))
        ? $assignmentRef
        : null;
@endphp

{{-- Section 1: Basic info --}}
<div class="mod-form-section">
    <div class="mod-form-section__head"><i class="fa fa-circle-info"></i>{{ __('Modification details') }}</div>
    <div class="mod-fields-grid">
        <div class="mod-field mod-field--full">
            <label>{{ __('Name') }}</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   placeholder="{{ __('e.g. Kitchen renovation 2026') }}">
            @error('name')<span class="mod-field-err">{{ $message }}</span>@enderror
        </div>
        <div class="mod-field">
            <label>{{ __('Assign to') }}</label>
            <select name="assignment_type" required data-mod-assignment-type>
                <option value="renovation" @selected(old('assignment_type', 'renovation') === 'renovation')>{{ __('Renovation') }}</option>
                <option value="property" @selected(old('assignment_type') === 'property')>{{ __('Property') }}</option>
                <option value="other" @selected(old('assignment_type') === 'other')>{{ __('Other') }}</option>
            </select>
            @error('assignment_type')<span class="mod-field-err">{{ $message }}</span>@enderror
        </div>
        <div class="mod-field">
            <label>{{ __('Estimated cost') }}</label>
            <input type="number" name="estimated_cost" min="0" step="0.01"
                   value="{{ old('estimated_cost') }}" required placeholder="0.00">
            @error('estimated_cost')<span class="mod-field-err">{{ $message }}</span>@enderror
        </div>
    </div>
</div>

{{-- Section 2: Assignment reference (conditional) --}}
<div class="mod-form-section">
    <div class="mod-form-section__head"><i class="fa fa-link"></i>{{ __('Assignment reference') }}</div>
    <div class="mod-fields-grid">
        <div class="mod-field" style="display:{{ $assignmentType !== 'renovation' ? 'none' : 'block' }};" data-mod-ref-renovation>
            <label>{{ __('Select renovation') }}</label>
            <select name="assignment_reference" data-mod-renovation-select
                    @if($assignmentType !== 'renovation') disabled @endif>
                <option value="">{{ __('Select renovation item') }}</option>
                @if($customRenovationOption !== null)
                    <option value="{{ $customRenovationOption }}" selected>{{ $customRenovationOption }}</option>
                @endif
                @foreach($renovationOptions as $value => $label)
                    <option value="{{ $value }}" @selected($assignmentType === 'renovation' && $assignmentRef === $value)>{{ $label }}</option>
                @endforeach
                <option value="__add_renovation__">{{ __('+ Add custom renovation...') }}</option>
            </select>
            @error('assignment_reference')<span class="mod-field-err">{{ $message }}</span>@enderror
        </div>

        <div class="mod-field" style="display:{{ $assignmentType !== 'property' ? 'none' : 'block' }};" data-mod-ref-property>
            <label>{{ __('Select property') }}</label>
            <select name="assignment_reference" data-mod-property-select
                    @if($assignmentType !== 'property') disabled @endif>
                <option value="">{{ __('Select property') }}</option>
                @foreach($propertiesForAssignment as $property)
                    <option value="{{ $property->id }}" @selected($assignmentType === 'property' && (string) $assignmentRef === (string) $property->id)>
                        {{ $property->property_name }} · {{ $property->property_type }}
                    </option>
                @endforeach
                <option value="__add_property__">{{ __('+ Add new property...') }}</option>
            </select>
            @error('assignment_reference')<span class="mod-field-err">{{ $message }}</span>@enderror
        </div>

        <div class="mod-field" style="display:{{ $assignmentType !== 'property' ? 'none' : 'block' }};" data-mod-property-worktype>
            <label>{{ __('Renovation type') }}</label>
            <select name="property_work_type" data-mod-property-worktype-select
                    @if($assignmentType !== 'property') disabled @endif>
                @foreach($propertyWorkTypeOptions as $value => $label)
                    <option value="{{ $value }}" @selected($assignmentType === 'property' && $propertyWorkType === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('property_work_type')<span class="mod-field-err">{{ $message }}</span>@enderror
        </div>

        <div class="mod-field" style="display:{{ !($assignmentType === 'property' && $propertyWorkType === \Modules\Modification\Models\Modification::PROPERTY_WORK_TYPE_OTHER) ? 'none' : 'block' }};" data-mod-property-worktype-other>
            <label>{{ __('Other renovation type') }}</label>
            <input type="text" name="property_work_type_other" value="{{ $propertyWorkTypeOther }}"
                   @if(!($assignmentType === 'property' && $propertyWorkType === \Modules\Modification\Models\Modification::PROPERTY_WORK_TYPE_OTHER)) disabled @endif
                   placeholder="{{ __('Describe the renovation...') }}">
            @error('property_work_type_other')<span class="mod-field-err">{{ $message }}</span>@enderror
        </div>

        <div class="mod-field" style="display:{{ !in_array($assignmentType, ['other', '']) ? 'none' : 'block' }};" data-mod-ref-other>
            <label>{{ __('Reference') }}</label>
            <input type="text" name="assignment_reference" value="{{ old('assignment_reference') }}"
                   @if(!in_array($assignmentType, ['other', ''])) disabled @endif
                   placeholder="{{ __('e.g. Building A, Floor 2') }}">
        </div>
    </div>
</div>

{{-- Section 3: Additional details --}}
<div class="mod-form-section">
    <div class="mod-form-section__head"><i class="fa fa-clipboard-list"></i>{{ __('Additional details') }}</div>
    <div class="mod-fields-grid">
        <div class="mod-field">
            <label>{{ __('Duration') }}</label>
            <input type="text" name="duration" value="{{ old('duration') }}"
                   placeholder="{{ __('e.g. 3 months') }}">
            @error('duration')<span class="mod-field-err">{{ $message }}</span>@enderror
        </div>
        <div class="mod-field mod-field--full">
            <label>{{ __('Description') }}</label>
            <textarea name="description" rows="3"
                      placeholder="{{ __('Add notes and details about this modification...') }}">{{ old('description') }}</textarea>
            @error('description')<span class="mod-field-err">{{ $message }}</span>@enderror
        </div>
    </div>
</div>

{{-- Quick renovation mini-modal --}}
<div id="modQuickRenovationModal" hidden style="position:fixed;inset:0;z-index:140;display:flex;align-items:flex-start;justify-content:center;padding:16px;">
    <button type="button" tabindex="-1" data-mod-quick-overlay
            style="position:fixed;inset:0;border:0;padding:0;margin:0;cursor:default;background:rgba(15,23,42,.52);backdrop-filter:blur(3px);"></button>
    <div role="dialog" aria-modal="true"
         style="position:relative;z-index:1;max-width:420px;width:100%;margin-top:10vh;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;box-shadow:0 28px 64px rgba(0,0,0,.4);">
        <h3 style="margin:0 0 6px;font-size:15px;font-weight:800;">{{ __('Custom renovation') }}</h3>
        <p style="margin:0 0 14px;line-height:1.5;font-size:13px;color:var(--muted);">{{ __('Describe the renovation. It will appear in the list.') }}</p>
        <div class="mod-field" style="margin-bottom:0;">
            <label>{{ __('Description') }}</label>
            <input type="text" id="modQuickRenovationInput" maxlength="255" autocomplete="off"
                   placeholder="{{ __('e.g. Kitchen island demolition') }}">
        </div>
        <div id="modQuickRenovationErr" style="margin-top:8px;color:#f87171;font-weight:600;font-size:12px;display:none;"></div>
        <div style="display:flex;gap:8px;margin-top:14px;">
            <button type="button" id="modQuickRenovationSave"
                    style="padding:9px 16px;font-size:13px;font-weight:700;border-radius:10px;border:0;background:var(--primary);color:#fff;cursor:pointer;">{{ __('Add') }}</button>
            <button type="button" id="modQuickRenovationCancel"
                    style="padding:9px 14px;font-size:13px;font-weight:600;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;">{{ __('Cancel') }}</button>
        </div>
    </div>
</div>

{{-- Quick property mini-modal --}}
<div id="modQuickPropertyModal" hidden style="position:fixed;inset:0;z-index:140;display:flex;align-items:flex-start;justify-content:center;padding:16px;overflow:auto;">
    <button type="button" tabindex="-1" data-mod-quick-overlay
            style="position:fixed;inset:0;border:0;padding:0;margin:0;cursor:default;background:rgba(15,23,42,.52);backdrop-filter:blur(3px);"></button>
    <div role="dialog" aria-modal="true"
         style="position:relative;z-index:1;max-width:480px;width:100%;margin:5vh auto 16px;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;box-shadow:0 28px 64px rgba(0,0,0,.4);">
        <h3 style="margin:0 0 6px;font-size:15px;font-weight:800;">{{ __('New property') }}</h3>
        <p style="margin:0 0 14px;line-height:1.5;font-size:13px;color:var(--muted);">{{ __('Creates a property for this business and selects it automatically.') }}</p>
        <div id="modQuickPropertyErr"
             style="margin:0 0 12px;padding:10px 12px;border-radius:8px;border:1px solid #fca5a5;background:color-mix(in srgb,#ef4444 8%,transparent);color:#b91c1c;font-size:12px;font-weight:600;display:none;"></div>
        <div style="display:grid;gap:12px;">
            <div class="mod-field">
                <label>{{ __('Property name') }}</label>
                <input type="text" id="modQuickPropertyName" maxlength="255">
            </div>
            <div class="mod-field">
                <label>{{ __('Property type') }}</label>
                <select id="modQuickPropertyType">
                    <option value="">{{ __('Select property type') }}</option>
                    @foreach($propertyTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mod-field" id="modQuickPropertyTypeOtherWrap" hidden>
                <label>{{ __('Other property type') }}</label>
                <input type="text" id="modQuickPropertyTypeOther" maxlength="255"
                       placeholder="{{ __('e.g. Industrial tools') }}">
            </div>
            <div class="mod-field">
                <label>{{ __('Cost') }}</label>
                <input type="number" id="modQuickPropertyCost" min="0" step="0.01" value="0">
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" id="modQuickPropertyHasExpiry" value="1" style="margin:0;width:auto;">
                <label for="modQuickPropertyHasExpiry" style="margin:0;font-size:13px;font-weight:600;cursor:pointer;color:var(--text);">{{ __('Has expiry date') }}</label>
            </div>
            <div class="mod-field" id="modQuickPropertyExpireDateWrap" hidden>
                <label>{{ __('Expire date') }}</label>
                <input type="date" id="modQuickPropertyExpireDate">
            </div>
            <div class="mod-field">
                <label>{{ __('Description') }}</label>
                <textarea id="modQuickPropertyDescription" rows="2" maxlength="5000"></textarea>
            </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
            <button type="button" id="modQuickPropertySave"
                    style="padding:9px 16px;font-size:13px;font-weight:700;border-radius:10px;border:0;background:var(--primary);color:#fff;cursor:pointer;">{{ __('Save property') }}</button>
            <button type="button" id="modQuickPropertyCancel"
                    style="padding:9px 14px;font-size:13px;font-weight:600;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;">{{ __('Cancel') }}</button>
        </div>
    </div>
</div>

<script>
(function () {
    var QUICK_PROPERTY_URL = @json(route('modification.quick-property.store'));
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    var pendingRenovationSelect = null;
    var pendingPropertySelect = null;

    function openRenModal() {
        var el = document.getElementById('modQuickRenovationModal');
        if (!el) return;
        el.hidden = false;
        var err = document.getElementById('modQuickRenovationErr');
        if (err) { err.style.display = 'none'; err.textContent = ''; }
        var inp = document.getElementById('modQuickRenovationInput');
        if (inp) { inp.value = ''; setTimeout(function(){inp.focus();},60); }
    }
    function closeRenModal() {
        var el = document.getElementById('modQuickRenovationModal');
        if (el) el.hidden = true;
        pendingRenovationSelect = null;
    }
    function openPropModal() {
        var el = document.getElementById('modQuickPropertyModal');
        if (!el) return;
        el.hidden = false;
        var banner = document.getElementById('modQuickPropertyErr');
        if (banner) { banner.style.display = 'none'; banner.textContent = ''; }
        ['modQuickPropertyName','modQuickPropertyType','modQuickPropertyTypeOther','modQuickPropertyDescription'].forEach(function(id){
            var e = document.getElementById(id); if(e) e.value = '';
        });
        var c = document.getElementById('modQuickPropertyCost'); if(c) c.value='0';
        var h = document.getElementById('modQuickPropertyHasExpiry'); if(h) h.checked=false;
        var d = document.getElementById('modQuickPropertyExpireDate'); if(d) d.value='';
        syncExpiry(); syncPropTypeOther();
        var n = document.getElementById('modQuickPropertyName');
        if (n) setTimeout(function(){n.focus();},60);
    }
    function closePropModal() {
        var el = document.getElementById('modQuickPropertyModal');
        if (el) el.hidden = true;
        pendingPropertySelect = null;
    }

    function syncExpiry() {
        var h = document.getElementById('modQuickPropertyHasExpiry');
        var w = document.getElementById('modQuickPropertyExpireDateWrap');
        var d = document.getElementById('modQuickPropertyExpireDate');
        if (!h || !w || !d) return;
        w.hidden = !h.checked;
        d.disabled = !h.checked;
        if (!h.checked) d.value = '';
    }
    function syncPropTypeOther() {
        var t = document.getElementById('modQuickPropertyType');
        var w = document.getElementById('modQuickPropertyTypeOtherWrap');
        var i = document.getElementById('modQuickPropertyTypeOther');
        if (!t || !w || !i) return;
        var on = t.value === 'other';
        w.hidden = !on;
        i.disabled = !on;
        if (!on) i.value = '';
    }

    document.getElementById('modQuickRenovationCancel')?.addEventListener('click', closeRenModal);
    document.getElementById('modQuickPropertyCancel')?.addEventListener('click', closePropModal);
    document.querySelector('#modQuickRenovationModal [data-mod-quick-overlay]')?.addEventListener('click', closeRenModal);
    document.querySelector('#modQuickPropertyModal [data-mod-quick-overlay]')?.addEventListener('click', closePropModal);
    document.getElementById('modQuickPropertyHasExpiry')?.addEventListener('change', syncExpiry);
    document.getElementById('modQuickPropertyType')?.addEventListener('change', syncPropTypeOther);

    document.getElementById('modQuickRenovationSave')?.addEventListener('click', function () {
        var inp = document.getElementById('modQuickRenovationInput');
        var err = document.getElementById('modQuickRenovationErr');
        var sel = pendingRenovationSelect;
        if (!inp || !sel) return;
        var txt = inp.value.trim();
        if (!txt) {
            if (err) { err.textContent = @json(__('Enter a renovation description.')); err.style.display = 'block'; }
            return;
        }
        var addOpt = sel.querySelector('option[value="__add_renovation__"]');
        if (!addOpt) return;
        var opt = document.createElement('option');
        opt.value = txt; opt.textContent = txt;
        addOpt.before(opt);
        sel.value = txt;
        closeRenModal();
    });

    document.getElementById('modQuickPropertySave')?.addEventListener('click', function () {
        var banner = document.getElementById('modQuickPropertyErr');
        var sel = pendingPropertySelect;
        var addOpt = sel && sel.querySelector('option[value="__add_property__"]');
        if (!sel || !addOpt) return;
        function showErr(msg) {
            if (banner) { banner.textContent = msg; banner.style.display = 'block'; }
        }
        var pname = document.getElementById('modQuickPropertyName').value.trim();
        var ptype = document.getElementById('modQuickPropertyType').value.trim();
        var ptypeOther = document.getElementById('modQuickPropertyTypeOther').value.trim();
        var cost = document.getElementById('modQuickPropertyCost').value;
        var hasEx = document.getElementById('modQuickPropertyHasExpiry').checked;
        var expire = document.getElementById('modQuickPropertyExpireDate').value;
        var descr = document.getElementById('modQuickPropertyDescription').value.trim();
        if (!pname || !ptype) { showErr(@json(__('Name and type are required.'))); return; }
        if (ptype === 'other' && !ptypeOther) { showErr(@json(__('Other property type is required.'))); return; }
        if (hasEx && !expire) { showErr(@json(__('Expiry date is required when expiry is enabled.'))); return; }
        if (banner) { banner.style.display = 'none'; banner.textContent = ''; }
        fetch(QUICK_PROPERTY_URL, {
            method: 'POST',
            headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'},
            credentials: 'same-origin',
            body: JSON.stringify({
                property_name:pname, property_type:ptype,
                property_type_other:ptype==='other'?ptypeOther:null,
                cost:cost===''?0:parseFloat(cost),
                description:descr||null, has_expiry:hasEx,
                expire_date:hasEx?expire:null
            })
        }).then(function(res){
            return res.text().then(function(text){
                var data={}; try{data=JSON.parse(text);}catch(e){}
                return {ok:res.ok,data:data};
            });
        }).then(function(r){
            if (!r.ok) {
                var msg = @json(__('Could not save property.'));
                if (r.data&&r.data.errors) msg=Object.values(r.data.errors).flat().join(' ');
                else if (r.data&&r.data.message) msg=r.data.message;
                showErr(msg); return;
            }
            var p = r.data.property; if (!p) return;
            var opt = document.createElement('option');
            opt.value=String(p.id); opt.textContent=p.property_name+' · '+p.property_type;
            addOpt.before(opt); sel.value=String(p.id);
            closePropModal();
        }).catch(function(){ showErr(@json(__('Network error. Try again.'))); });
    });

    document.querySelectorAll('select[data-mod-assignment-type]').forEach(function (typeSelect) {
        var form = typeSelect.closest('form');
        if (!form || form.dataset.modAssignWired === '1') return;
        form.dataset.modAssignWired = '1';

        var renoWrap     = form.querySelector('[data-mod-ref-renovation]');
        var propWrap     = form.querySelector('[data-mod-ref-property]');
        var propWorkWrap = form.querySelector('[data-mod-property-worktype]');
        var propWorkOtherWrap = form.querySelector('[data-mod-property-worktype-other]');
        var otherWrap    = form.querySelector('[data-mod-ref-other]');
        var renoSelect   = renoWrap     ? renoWrap.querySelector('select[data-mod-renovation-select]')        : null;
        var propSelect   = propWrap     ? propWrap.querySelector('select[data-mod-property-select]')          : null;
        var propWorkSelect = propWorkWrap ? propWorkWrap.querySelector('select[data-mod-property-worktype-select]') : null;
        var propWorkOtherInput = propWorkOtherWrap ? propWorkOtherWrap.querySelector('[name="property_work_type_other"]') : null;
        var otherInput   = otherWrap    ? otherWrap.querySelector('[name="assignment_reference"]')            : null;

        function setVis(el, show) { if (el) el.style.display = show ? 'block' : 'none'; }

        function syncPropertyWorkTypeOther() {
            if (!propWorkSelect || !propWorkOtherWrap || !propWorkOtherInput) return;
            var show = typeSelect.value === 'property' && propWorkSelect.value === @json(\Modules\Modification\Models\Modification::PROPERTY_WORK_TYPE_OTHER);
            setVis(propWorkOtherWrap, show);
            propWorkOtherInput.disabled = !show;
        }

        function syncRefField() {
            var v = typeSelect.value || 'renovation';
            setVis(renoWrap,     v === 'renovation');
            setVis(propWrap,     v === 'property');
            setVis(propWorkWrap, v === 'property');
            setVis(otherWrap,    v === 'other');
            if (renoSelect)      renoSelect.disabled      = v !== 'renovation';
            if (propSelect)      propSelect.disabled      = v !== 'property';
            if (propWorkSelect)  propWorkSelect.disabled  = v !== 'property';
            if (otherInput)      otherInput.disabled      = v !== 'other';
            syncPropertyWorkTypeOther();
        }

        typeSelect.addEventListener('change', syncRefField);
        syncRefField();

        var lastReno = '';
        if (renoSelect) {
            renoSelect.addEventListener('focus',  function(){ lastReno = renoSelect.value; });
            renoSelect.addEventListener('change', function(){
                if (renoSelect.value === '__add_renovation__') {
                    renoSelect.value = lastReno;
                    pendingRenovationSelect = renoSelect;
                    openRenModal();
                }
            });
        }
        var lastProp = '';
        if (propSelect) {
            propSelect.addEventListener('focus',  function(){ lastProp = propSelect.value; });
            propSelect.addEventListener('change', function(){
                if (propSelect.value === '__add_property__') {
                    propSelect.value = lastProp;
                    pendingPropertySelect = propSelect;
                    openPropModal();
                }
            });
        }
        if (propWorkSelect) {
            propWorkSelect.addEventListener('change', syncPropertyWorkTypeOther);
            syncPropertyWorkTypeOther();
        }
    });

    syncExpiry();
    syncPropTypeOther();
})();
</script>
