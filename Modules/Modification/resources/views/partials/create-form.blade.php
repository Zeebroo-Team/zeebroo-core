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
<label style="display:grid;gap:5px;">
    <span class="muted" style="font-size:12px;font-weight:700;">{{ __('Name') }}</span>
    <input type="text" name="name" value="{{ old('name') }}" required
           style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
</label>

<div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
    <label style="display:grid;gap:5px;">
        <span class="muted" style="font-size:12px;font-weight:700;">{{ __('Assign to') }}</span>
        <select name="assignment_type" required
                data-mod-assignment-type
                style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
            <option value="renovation" @selected(old('assignment_type', 'renovation') === 'renovation')>{{ __('Renovation') }}</option>
            <option value="property" @selected(old('assignment_type') === 'property')>{{ __('Property') }}</option>
            <option value="other" @selected(old('assignment_type') === 'other')>{{ __('Other') }}</option>
        </select>
    </label>

    <label style="display:grid;gap:5px;">
        <span class="muted" style="font-size:12px;font-weight:700;">{{ __('Estimation cost') }}</span>
        <input type="number" name="estimated_cost" min="0" step="0.01" value="{{ old('estimated_cost') }}" required
               style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
    </label>
</div>

<div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
    <label style="display:grid;gap:5px;" data-mod-ref-renovation @if($assignmentType !== 'renovation') hidden @endif>
        <span class="muted" style="font-size:12px;font-weight:700;">{{ __('Select renovation') }}</span>
        <select name="assignment_reference" data-mod-renovation-select @if($assignmentType !== 'renovation') disabled @endif
                style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
            <option value="">{{ __('Select renovation item') }}</option>
            @if($customRenovationOption !== null)
                <option value="{{ $customRenovationOption }}" selected>{{ $customRenovationOption }}</option>
            @endif
            @foreach($renovationOptions as $value => $label)
                <option value="{{ $value }}" @selected($assignmentType === 'renovation' && $assignmentRef === $value)>{{ $label }}</option>
            @endforeach
            <option value="__add_renovation__">{{ __('Add custom renovation…') }}</option>
        </select>
    </label>

    <label style="display:grid;gap:5px;" data-mod-ref-property @if($assignmentType !== 'property') hidden @endif>
        <span class="muted" style="font-size:12px;font-weight:700;">{{ __('Select property') }}</span>
        <select name="assignment_reference" data-mod-property-select @if($assignmentType !== 'property') disabled @endif
                style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
            <option value="">{{ __('Select property') }}</option>
            @foreach($propertiesForAssignment as $property)
                <option value="{{ $property->id }}" @selected($assignmentType === 'property' && (string) $assignmentRef === (string) $property->id)>
                    {{ $property->property_name }} · {{ $property->property_type }}
                </option>
            @endforeach
            <option value="__add_property__">{{ __('Add new property…') }}</option>
        </select>
    </label>

    <label style="display:grid;gap:5px;" data-mod-property-worktype @if($assignmentType !== 'property') hidden @endif>
        <span class="muted" style="font-size:12px;font-weight:700;">{{ __('Select renovation') }}</span>
        <select name="property_work_type" data-mod-property-worktype-select @if($assignmentType !== 'property') disabled @endif
                style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
            @foreach($propertyWorkTypeOptions as $value => $label)
                <option value="{{ $value }}" @selected($assignmentType === 'property' && $propertyWorkType === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('property_work_type')<span style="color:#b91c1c;font-weight:700;font-size:11px;">{{ $message }}</span>@enderror
    </label>

    <label style="display:grid;gap:5px;" data-mod-property-worktype-other @if(!($assignmentType === 'property' && $propertyWorkType === \Modules\Modification\Models\Modification::PROPERTY_WORK_TYPE_OTHER)) hidden @endif>
        <span class="muted" style="font-size:12px;font-weight:700;">{{ __('Other renovation') }}</span>
        <input type="text" name="property_work_type_other" value="{{ $propertyWorkTypeOther }}"
               @if(!($assignmentType === 'property' && $propertyWorkType === \Modules\Modification\Models\Modification::PROPERTY_WORK_TYPE_OTHER)) disabled @endif
               placeholder="{{ __('Describe renovation...') }}"
               style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
        @error('property_work_type_other')<span style="color:#b91c1c;font-weight:700;font-size:11px;">{{ $message }}</span>@enderror
    </label>

    <label style="display:grid;gap:5px;" data-mod-ref-other @if(!in_array($assignmentType, ['other', ''])) hidden @endif>
        <span class="muted" style="font-size:12px;font-weight:700;">{{ __('Reference') }}</span>
        <input type="text" name="assignment_reference" value="{{ old('assignment_reference') }}" @if(!in_array($assignmentType, ['other', ''])) disabled @endif
               placeholder="{{ __('e.g. Building A, Floor 2') }}"
               style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
    </label>

    <label style="display:grid;gap:5px;">
        <span class="muted" style="font-size:12px;font-weight:700;">{{ __('Duration') }}</span>
        <input type="text" name="duration" value="{{ old('duration') }}" placeholder="{{ __('e.g. 3 months') }}"
               style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
    </label>
</div>

<label style="display:grid;gap:5px;">
    <span class="muted" style="font-size:12px;font-weight:700;">{{ __('Description') }}</span>
    <textarea name="description" rows="4" placeholder="{{ __('Add notes and details...') }}"
              style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);resize:vertical;font-size:12px;">{{ old('description') }}</textarea>
</label>

<div id="modQuickRenovationModal" hidden style="position:fixed;inset:0;z-index:120;">
    <button type="button" tabindex="-1" data-mod-quick-overlay style="position:absolute;inset:0;border:0;padding:0;margin:0;cursor:pointer;background:rgba(15,23,42,.55);"></button>
    <div role="dialog" aria-modal="true"
         style="position:relative;max-width:420px;width:calc(100% - 24px);margin:14px auto;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px;font-size:12px;">
        <h3 style="margin:0 0 10px;font-size:15px;">{{ __('Custom renovation') }}</h3>
        <p class="muted" style="margin:0 0 10px;line-height:1.45;font-size:12px;">{{ __('Describe the renovation. It will appear in the list below the presets.') }}</p>
        <label style="display:grid;gap:5px;">
            <span class="muted" style="font-weight:700;">{{ __('Description') }}</span>
            <input type="text" id="modQuickRenovationInput" maxlength="255" autocomplete="off"
                   style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;"
                   placeholder="{{ __('e.g. Kitchen island demolition') }}">
        </label>
        <div id="modQuickRenovationErr" class="muted" style="margin-top:8px;color:#b91c1c;font-weight:600;font-size:11px;display:none;"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
            <button type="button" id="modQuickRenovationSave"
                    style="padding:8px 11px;font-size:12px;border-radius:8px;border:0;background:var(--text);color:var(--bg);cursor:pointer;">{{ __('Add') }}</button>
            <button type="button" id="modQuickRenovationCancel"
                    style="padding:8px 11px;font-size:12px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;">{{ __('Cancel') }}</button>
        </div>
    </div>
</div>

<div id="modQuickPropertyModal" hidden style="position:fixed;inset:0;z-index:120;">
    <button type="button" tabindex="-1" data-mod-quick-overlay style="position:absolute;inset:0;border:0;padding:0;margin:0;cursor:pointer;background:rgba(15,23,42,.55);"></button>
    <div role="dialog" aria-modal="true"
         style="position:relative;max-width:480px;width:calc(100% - 24px);margin:14px auto;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px;font-size:12px;max-height:calc(100vh - 28px);overflow:auto;">
        <h3 style="margin:0 0 10px;font-size:15px;">{{ __('New property') }}</h3>
        <p class="muted" style="margin:0 0 10px;line-height:1.45;font-size:12px;">{{ __('Creates a property for this business and selects it below.') }}</p>
        <div id="modQuickPropertyErr" style="margin:0 0 10px;padding:9px;border-radius:8px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;font-size:11px;display:none;"></div>
        <div style="display:grid;gap:10px;">
            <label style="display:grid;gap:5px;">
                <span class="muted" style="font-weight:700;">{{ __('Property name') }}</span>
                <input type="text" id="modQuickPropertyName" maxlength="255" required
                       style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
            </label>
            <label style="display:grid;gap:5px;">
                <span class="muted" style="font-weight:700;">{{ __('Property type') }}</span>
                <select id="modQuickPropertyType" required
                        style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
                    <option value="">{{ __('Select property type') }}</option>
                    @foreach($propertyTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label id="modQuickPropertyTypeOtherWrap" style="display:grid;gap:5px;" hidden>
                <span class="muted" style="font-weight:700;">{{ __('Other property type') }}</span>
                <input type="text" id="modQuickPropertyTypeOther" maxlength="255"
                       placeholder="{{ __('e.g. Industrial tools') }}"
                       style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
            </label>
            <label style="display:grid;gap:5px;">
                <span class="muted" style="font-weight:700;">{{ __('Cost') }}</span>
                <input type="number" id="modQuickPropertyCost" min="0" step="0.01" required value="0"
                       style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
            </label>
            <div style="display:flex;gap:10px;align-items:center;">
                <input type="checkbox" id="modQuickPropertyHasExpiry" value="1" style="margin:0;">
                <label for="modQuickPropertyHasExpiry" class="muted" style="margin:0;font-weight:700;cursor:pointer;">{{ __('Has expiry date') }}</label>
            </div>
            <label style="display:grid;gap:5px;">
                <span class="muted" style="font-weight:700;">{{ __('Expire date') }}</span>
                <input type="date" id="modQuickPropertyExpireDate"
                       style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
            </label>
            <label style="display:grid;gap:5px;">
                <span class="muted" style="font-weight:700;">{{ __('Description') }}</span>
                <textarea id="modQuickPropertyDescription" rows="2" maxlength="5000"
                          style="width:100%;box-sizing:border-box;padding:9px 11px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);resize:vertical;font-size:12px;"></textarea>
            </label>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
            <button type="button" id="modQuickPropertySave"
                    style="padding:8px 11px;font-size:12px;border-radius:8px;border:0;background:var(--text);color:var(--bg);cursor:pointer;">{{ __('Save property') }}</button>
            <button type="button" id="modQuickPropertyCancel"
                    style="padding:8px 11px;font-size:12px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;">{{ __('Cancel') }}</button>
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
        if (inp) { inp.value = ''; inp.focus(); }
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
        var n = document.getElementById('modQuickPropertyName');
        var t = document.getElementById('modQuickPropertyType');
        var c = document.getElementById('modQuickPropertyCost');
        var h = document.getElementById('modQuickPropertyHasExpiry');
        var d = document.getElementById('modQuickPropertyExpireDate');
        var desc = document.getElementById('modQuickPropertyDescription');
        var typeOther = document.getElementById('modQuickPropertyTypeOther');
        if (n) n.value = '';
        if (t) t.value = '';
        if (typeOther) typeOther.value = '';
        if (c) c.value = '0';
        if (h) h.checked = false;
        if (d) d.value = '';
        if (desc) desc.value = '';
        if (d) d.disabled = true;
        if (n) n.focus();
    }
    function closePropModal() {
        var el = document.getElementById('modQuickPropertyModal');
        if (el) el.hidden = true;
        pendingPropertySelect = null;
    }

    document.getElementById('modQuickRenovationCancel')?.addEventListener('click', closeRenModal);
    document.getElementById('modQuickPropertyCancel')?.addEventListener('click', closePropModal);
    document.querySelector('#modQuickRenovationModal [data-mod-quick-overlay]')?.addEventListener('click', closeRenModal);
    document.querySelector('#modQuickPropertyModal [data-mod-quick-overlay]')?.addEventListener('click', closePropModal);

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
        opt.value = txt;
        opt.textContent = txt;
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
            if (banner) {
                banner.textContent = msg;
                banner.style.display = 'block';
            }
        }
        var pname = document.getElementById('modQuickPropertyName').value.trim();
        var ptype = document.getElementById('modQuickPropertyType').value.trim();
        var ptypeOther = document.getElementById('modQuickPropertyTypeOther').value.trim();
        var cost = document.getElementById('modQuickPropertyCost').value;
        var hasEx = document.getElementById('modQuickPropertyHasExpiry').checked;
        var expire = document.getElementById('modQuickPropertyExpireDate').value;
        var descr = document.getElementById('modQuickPropertyDescription').value.trim();
        if (!pname || !ptype) {
            showErr(@json(__('Name and type are required.')));
            return;
        }
        if (ptype === 'other' && !ptypeOther) {
            showErr(@json(__('Other property type is required.')));
            return;
        }
        if (hasEx && !expire) {
            showErr(@json(__('Expiry date is required when expiry is enabled.')));
            return;
        }
        if (banner) { banner.style.display = 'none'; banner.textContent = ''; }
        var payload = {
            property_name: pname,
            property_type: ptype,
            property_type_other: ptype === 'other' ? ptypeOther : null,
            cost: cost === '' ? 0 : parseFloat(cost),
            description: descr || null,
            has_expiry: hasEx ? true : false,
            expire_date: hasEx ? expire : null
        };
        fetch(QUICK_PROPERTY_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.text().then(function (text) {
                var data = {};
                if (text) {
                    try { data = JSON.parse(text); } catch (e) { /* ignore */ }
                }
                return { ok: res.ok, status: res.status, data: data };
            });
        }).then(function (r) {
            if (!r.ok) {
                var msg = @json(__('Could not save property.'));
                if (r.data && r.data.errors) {
                    msg = Object.values(r.data.errors).flat().join(' ');
                } else if (r.data && r.data.message) {
                    msg = r.data.message;
                }
                showErr(msg);
                return;
            }
            var p = r.data.property;
            if (!p) return;
            var opt = document.createElement('option');
            opt.value = String(p.id);
            opt.textContent = p.property_name + ' · ' + p.property_type;
            addOpt.before(opt);
            sel.value = String(p.id);
            closePropModal();
        }).catch(function () {
            showErr(@json(__('Network error. Try again.')));
        });
    });

    (function expiryToggleQuick() {
        var h = document.getElementById('modQuickPropertyHasExpiry');
        var d = document.getElementById('modQuickPropertyExpireDate');
        if (!h || !d) return;
        function sync() {
            d.disabled = !h.checked;
            if (!h.checked) d.value = '';
        }
        h.addEventListener('change', sync);
        sync();
    })();

    (function propertyTypeOtherQuick() {
        var t = document.getElementById('modQuickPropertyType');
        var w = document.getElementById('modQuickPropertyTypeOtherWrap');
        var i = document.getElementById('modQuickPropertyTypeOther');
        if (!t || !w || !i) return;
        function sync() {
            var on = t.value === 'other';
            w.hidden = !on;
            i.disabled = !on;
            i.required = on;
            if (!on) i.value = '';
        }
        t.addEventListener('change', sync);
        sync();
    })();

    document.querySelectorAll('select[data-mod-assignment-type]').forEach(function (typeSelect) {
        var form = typeSelect.closest('form');
        if (!form || form.dataset.modAssignWired === '1') return;
        form.dataset.modAssignWired = '1';

        var renoWrap = form.querySelector('[data-mod-ref-renovation]');
        var propWrap = form.querySelector('[data-mod-ref-property]');
        var propWorkWrap = form.querySelector('[data-mod-property-worktype]');
        var propWorkOtherWrap = form.querySelector('[data-mod-property-worktype-other]');
        var otherWrap = form.querySelector('[data-mod-ref-other]');
        var renoSelect = renoWrap ? renoWrap.querySelector('select[data-mod-renovation-select]') : null;
        var propSelect = propWrap ? propWrap.querySelector('select[data-mod-property-select]') : null;
        var propWorkSelect = propWorkWrap ? propWorkWrap.querySelector('select[data-mod-property-worktype-select]') : null;
        var propWorkOtherInput = propWorkOtherWrap ? propWorkOtherWrap.querySelector('[name="property_work_type_other"]') : null;
        var otherInput = otherWrap ? otherWrap.querySelector('[name="assignment_reference"]') : null;

        function syncPropertyWorkTypeOther() {
            if (!propWorkSelect || !propWorkOtherWrap || !propWorkOtherInput) return;
            var show = typeSelect.value === 'property' && propWorkSelect.value === @json(\Modules\Modification\Models\Modification::PROPERTY_WORK_TYPE_OTHER);
            propWorkOtherWrap.hidden = !show;
            propWorkOtherInput.disabled = !show;
        }

        function syncRefField() {
            var v = typeSelect.value || 'renovation';
            if (renoWrap) renoWrap.hidden = v !== 'renovation';
            if (propWrap) propWrap.hidden = v !== 'property';
            if (propWorkWrap) propWorkWrap.hidden = v !== 'property';
            if (otherWrap) otherWrap.hidden = v !== 'other';
            if (renoSelect) renoSelect.disabled = v !== 'renovation';
            if (propSelect) propSelect.disabled = v !== 'property';
            if (propWorkSelect) propWorkSelect.disabled = v !== 'property';
            if (otherInput) otherInput.disabled = v !== 'other';
            syncPropertyWorkTypeOther();
        }

        typeSelect.addEventListener('change', syncRefField);
        syncRefField();

        var lastReno = '';
        if (renoSelect) {
            renoSelect.addEventListener('focus', function () {
                lastReno = renoSelect.value;
            });
            renoSelect.addEventListener('change', function () {
                if (renoSelect.value === '__add_renovation__') {
                    renoSelect.value = lastReno;
                    pendingRenovationSelect = renoSelect;
                    openRenModal();
                }
            });
        }
        var lastProp = '';
        if (propSelect) {
            propSelect.addEventListener('focus', function () {
                lastProp = propSelect.value;
            });
            propSelect.addEventListener('change', function () {
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
})();
</script>
