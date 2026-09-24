@once
<style>
.pos-svc-picker{position:fixed;inset:0;z-index:340;display:flex;justify-content:center;align-items:center;padding:16px;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease,visibility .2s ease;}
.pos-svc-picker.is-open{opacity:1;visibility:visible;pointer-events:auto;}
.pos-svc-picker__backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
.pos-svc-picker__panel{position:relative;z-index:1;width:min(100%,460px);max-height:min(85vh,600px);display:flex;flex-direction:column;border-radius:12px;border:1px solid var(--border);background:var(--card);box-shadow:0 16px 40px rgba(0,0,0,.28);}
.pos-svc-picker__head{padding:14px 16px;border-bottom:1px solid var(--border);flex-shrink:0;}
.pos-svc-picker__head h2{margin:0 0 4px;font-size:15px;font-weight:800;}
.pos-svc-picker__head p{margin:0;font-size:12px;color:var(--muted);}
.pos-svc-picker__body{flex:1;min-height:0;overflow:auto;padding:14px 16px;display:grid;gap:12px;}
.pos-svc-picker__field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px;}
.pos-svc-picker__field input[type=text],.pos-svc-picker__field input[type=number],.pos-svc-picker__field input[type=date],.pos-svc-picker__field select,.pos-svc-picker__field textarea{width:100%;box-sizing:border-box;padding:9px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-svc-picker__field textarea{min-height:60px;resize:vertical;font-family:inherit;}
.pos-svc-picker__radio-row{display:flex;gap:8px;flex-wrap:wrap;}
.pos-svc-picker__radio-opt{padding:8px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--text);cursor:pointer;}
.pos-svc-picker__radio-opt.is-active{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent);}
.pos-svc-picker__quickdays{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;}
.pos-svc-picker__quickday{padding:5px 9px;font-size:11px;font-weight:700;border-radius:7px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;}
.pos-svc-picker__quickday:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pos-svc-picker__checkbox-row{display:flex;align-items:center;gap:8px;font-size:13px;}
.pos-svc-picker__err{margin:0;font-size:12px;color:#f87171;font-weight:600;}
.pos-svc-picker__foot{padding:10px 16px 14px;display:flex;justify-content:space-between;gap:8px;flex-shrink:0;}
.pos-svc-picker__cancel,.pos-svc-picker__confirm{padding:8px 16px;font-size:13px;font-weight:700;border-radius:8px;cursor:pointer;}
.pos-svc-picker__cancel{border:1px solid var(--border);background:transparent;color:var(--text);}
.pos-svc-picker__confirm{border:1px solid color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 16%,transparent);color:var(--text);}
html.pos-svc-picker-open,html.pos-svc-picker-open body{overflow:hidden;}
</style>
@endonce

<div id="pos-svc-picker" class="pos-svc-picker" role="dialog" aria-modal="true" aria-labelledby="pos-svc-picker-title" aria-hidden="true">
    <div class="pos-svc-picker__backdrop" data-pos-svc-picker-close tabindex="-1" aria-label="Close"></div>
    <div class="pos-svc-picker__panel">
        <div class="pos-svc-picker__head">
            <h2 id="pos-svc-picker-title">Service details</h2>
            <p id="pos-svc-picker-subtitle"></p>
        </div>
        <div class="pos-svc-picker__body" id="pos-svc-picker-body"></div>
        <div class="pos-svc-picker__foot">
            <button type="button" class="pos-svc-picker__cancel" data-pos-svc-picker-close>Cancel</button>
            <button type="button" class="pos-svc-picker__confirm" id="pos-svc-picker-confirm">Continue</button>
        </div>
    </div>
</div>

@once
<script>
(function () {
    let pendingResolve = null;
    let pickerBound = false;
    let steps = [];
    let stepIndex = 0;
    let warrantyResult = { type: null, date: null };
    let customFields = [];

    function modalEl() { return document.getElementById('pos-svc-picker'); }
    function bodyEl() { return document.getElementById('pos-svc-picker-body'); }

    function setOpen(open) {
        const modal = modalEl();
        if (!modal) return;
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-svc-picker-open', open);
        if (!open && pendingResolve) {
            const resolve = pendingResolve;
            pendingResolve = null;
            resolve(null);
        }
    }

    function escHtml(str) {
        return String(str == null ? '' : str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderWarrantyStep() {
        document.getElementById('pos-svc-picker-title').textContent = 'Warranty';
        const body = bodyEl();
        body.innerHTML =
            '<div class="pos-svc-picker__field">' +
                '<label>Warranty type</label>' +
                '<div class="pos-svc-picker__radio-row">' +
                    '<button type="button" class="pos-svc-picker__radio-opt is-active" data-wty-opt="lifetime">Lifetime</button>' +
                    '<button type="button" class="pos-svc-picker__radio-opt" data-wty-opt="date">Expires on</button>' +
                '</div>' +
            '</div>' +
            '<div class="pos-svc-picker__field" id="pos-svc-wty-date-wrap" hidden>' +
                '<label for="pos-svc-wty-date">Expiry date</label>' +
                '<input type="date" id="pos-svc-wty-date">' +
                '<div class="pos-svc-picker__quickdays">' +
                    '<button type="button" class="pos-svc-picker__quickday" data-wty-days="30">+30d</button>' +
                    '<button type="button" class="pos-svc-picker__quickday" data-wty-days="90">+90d</button>' +
                    '<button type="button" class="pos-svc-picker__quickday" data-wty-days="180">+180d</button>' +
                    '<button type="button" class="pos-svc-picker__quickday" data-wty-days="365">+365d</button>' +
                '</div>' +
            '</div>' +
            '<p class="pos-svc-picker__err" id="pos-svc-picker-err" hidden></p>';

        const dateWrap = document.getElementById('pos-svc-wty-date-wrap');
        const dateInput = document.getElementById('pos-svc-wty-date');
        const today = new Date().toISOString().slice(0, 10);
        dateInput.min = today;

        body.querySelectorAll('[data-wty-opt]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                body.querySelectorAll('[data-wty-opt]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
                warrantyResult.type = btn.dataset.wtyOpt;
                dateWrap.hidden = btn.dataset.wtyOpt !== 'date';
            });
        });
        body.querySelectorAll('[data-wty-days]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const d = new Date();
                d.setDate(d.getDate() + parseInt(btn.dataset.wtyDays, 10));
                dateInput.value = d.toISOString().slice(0, 10);
            });
        });
        warrantyResult = { type: 'lifetime', date: null };
    }

    function fieldInputHtml(field, idx) {
        const name = 'pos-svc-creq-' + idx;
        const label = '<label for="' + name + '">' + escHtml(field.label) + '</label>';
        const options = Array.isArray(field.options) ? field.options : [];
        switch (field.type) {
            case 'textarea':
                return label + '<textarea id="' + name + '"></textarea>';
            case 'number':
                return label + '<input type="number" id="' + name + '" step="any">';
            case 'date':
                return label + '<input type="date" id="' + name + '">';
            case 'checkbox':
                return '<div class="pos-svc-picker__checkbox-row"><input type="checkbox" id="' + name + '"> ' + label + '</div>';
            case 'select':
                return label + '<select id="' + name + '"><option value="">—</option>' +
                    options.map(function (o) { return '<option value="' + escHtml(o) + '">' + escHtml(o) + '</option>'; }).join('') +
                    '</select>';
            case 'radio':
                return label + '<div class="pos-svc-picker__radio-row" data-radio-group="' + name + '">' +
                    options.map(function (o, i) {
                        return '<button type="button" class="pos-svc-picker__radio-opt" data-radio-value="' + escHtml(o) + '" data-radio-name="' + name + '">' + escHtml(o) + '</button>';
                    }).join('') + '</div>' +
                    '<input type="hidden" id="' + name + '">';
            default:
                return label + '<input type="text" id="' + name + '">';
        }
    }

    function renderCustomStep() {
        document.getElementById('pos-svc-picker-title').textContent = 'Additional details';
        const body = bodyEl();
        body.innerHTML = customFields.map(function (field, idx) {
            return '<div class="pos-svc-picker__field">' + fieldInputHtml(field, idx) + '</div>';
        }).join('') + '<p class="pos-svc-picker__err" id="pos-svc-picker-err" hidden></p>';

        body.querySelectorAll('[data-radio-name]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const name = btn.dataset.radioName;
                document.querySelectorAll('[data-radio-name="' + name + '"]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                document.getElementById(name).value = btn.dataset.radioValue;
            });
        });
    }

    function collectCustomValues() {
        return customFields.map(function (field, idx) {
            const el = document.getElementById('pos-svc-creq-' + idx);
            let value = '';
            if (field.type === 'checkbox') {
                value = el && el.checked ? 'true' : 'false';
            } else if (el) {
                value = el.value || '';
            }
            return { key: field.key || '', label: field.label || '', type: field.type || 'text', value: value };
        });
    }

    function renderStep() {
        const step = steps[stepIndex];
        const confirmBtn = document.getElementById('pos-svc-picker-confirm');
        confirmBtn.textContent = stepIndex === steps.length - 1 ? 'Add to sale' : 'Continue';
        if (step === 'warranty') renderWarrantyStep();
        else if (step === 'custom') renderCustomStep();
    }

    function bindPickerOnce() {
        if (pickerBound) return;
        pickerBound = true;
        const modal = modalEl();
        modal?.querySelectorAll('[data-pos-svc-picker-close]').forEach(function (el) {
            el.addEventListener('click', function () { setOpen(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal?.classList.contains('is-open')) setOpen(false);
        });
        document.getElementById('pos-svc-picker-confirm')?.addEventListener('click', function () {
            const step = steps[stepIndex];
            if (step === 'warranty') {
                if (warrantyResult.type === 'date') {
                    const dateInput = document.getElementById('pos-svc-wty-date');
                    if (!dateInput.value) {
                        const err = document.getElementById('pos-svc-picker-err');
                        err.textContent = 'Choose an expiry date.';
                        err.hidden = false;
                        return;
                    }
                    warrantyResult.date = dateInput.value;
                }
            }
            if (stepIndex < steps.length - 1) {
                stepIndex += 1;
                renderStep();
                return;
            }
            const customValues = steps.includes('custom') ? collectCustomValues() : null;
            const resolve = pendingResolve;
            pendingResolve = null;
            setOpen(false);
            resolve({
                warrantyType: steps.includes('warranty') ? warrantyResult.type : null,
                warrantyDate: steps.includes('warranty') ? warrantyResult.date : null,
                customRequirementValues: customValues,
            });
        });
    }

    window.posPickServiceDetails = function (service) {
        service = service || {};
        const hasWarranty = service.hasWarranty === true || service.hasWarranty === '1' || service.hasWarranty === 1;
        let fields = service.customRequirementFields;
        if (typeof fields === 'string') {
            try { fields = JSON.parse(fields); } catch (e) { fields = []; }
        }
        fields = Array.isArray(fields) ? fields : [];
        const customEnabled = (service.customRequirementEnabled === true || service.customRequirementEnabled === '1' || service.customRequirementEnabled === 1) && fields.length > 0;

        steps = [];
        if (hasWarranty) steps.push('warranty');
        if (customEnabled) steps.push('custom');

        if (steps.length === 0) {
            return Promise.resolve({ warrantyType: null, warrantyDate: null, customRequirementValues: null });
        }

        return new Promise(function (resolve) {
            bindPickerOnce();
            customFields = fields;
            stepIndex = 0;
            warrantyResult = { type: 'lifetime', date: null };
            const subtitleEl = document.getElementById('pos-svc-picker-subtitle');
            if (subtitleEl) subtitleEl.textContent = service.serviceName || service.name || '';
            renderStep();
            pendingResolve = resolve;
            setOpen(true);
        });
    };

    window.initPosServiceDetailsPicker = function () {
        bindPickerOnce();
    };
})();
</script>
@endonce
