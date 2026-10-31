{{-- Styles, edit/delete modal and behaviour of the POS Settings tab on the admin user page. --}}
<style>
.apss-loading,.apss-error{padding:18px;font-size:13px;color:var(--muted);text-align:center;}
.apss-error{color:#ef4444;}
.apss-bar{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 10px;}
.apss-surface{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border-radius:9px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:12px;font-weight:700;font-family:inherit;cursor:pointer;}
.apss-surface:hover{color:var(--text);}
.apss-surface.is-active{color:var(--text);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 9%,transparent);}
.apss-count{font-size:10.5px;padding:0 7px;border-radius:999px;background:color-mix(in srgb,var(--primary) 12%,transparent);}
.apss-note{margin:0 0 12px;font-size:11.5px;line-height:1.5;color:var(--muted);}
.apss-flash{margin:0 0 12px;padding:8px 12px;border-radius:9px;font-size:12.5px;font-weight:600;color:#15803d;background:color-mix(in srgb,#22c55e 12%,transparent);}
.apss-flash[hidden],.apss-group[hidden]{display:none !important;}
.apss-section{margin:16px 0 7px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.apss-group .apss-section:first-child{margin-top:0;}
.apss-card{overflow-x:auto;}
.apss-card .ads-table{table-layout:fixed;}
.apss-card .ads-table tr,.apss-card .ads-table th{box-sizing:border-box;}
.apss-col-setting{width:25%;}
.apss-col-value{width:25%;}
.apss-col-status{width:96px;}
.apss-label{font-size:13px;font-weight:650;}
.apss-key{margin-top:2px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:10.5px;color:var(--muted);word-break:break-all;}
.apss-value-cell{min-width:0;}
.apss-value{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.apss-muted{color:var(--muted);font-size:12px;}
.apss-logo{display:flex;align-items:center;gap:8px;min-width:0;}
.apss-logo img{width:30px;height:30px;object-fit:contain;border-radius:6px;border:1px solid var(--border);background:#fff;flex-shrink:0;}
.apss-pill,.apss-status,.apss-chip{display:inline-block;padding:2px 8px;border-radius:999px;font-size:10.5px;font-weight:700;white-space:nowrap;}
.apss-pill--on{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.apss-pill--off,.apss-status{background:color-mix(in srgb,#94a3b8 16%,transparent);color:#64748b;}
.apss-status--saved{background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);}
.apss-setting{min-width:0;}
.apss-chips{display:flex;flex-wrap:wrap;gap:3px;min-width:0;}
.apss-action-btns{display:inline-flex;gap:5px;}
.apss-chip{font-weight:600;background:color-mix(in srgb,#94a3b8 12%,transparent);color:var(--muted);}
.apss-actions-col{width:112px;}
.apss-actions{white-space:nowrap;text-align:right;}
.apss-icon-btn{width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:12px;cursor:pointer;}
.apss-icon-btn:hover:not(:disabled){border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.apss-icon-btn--danger:hover:not(:disabled){border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 7%,transparent);color:#b91c1c;}
.apss-icon-btn:disabled{opacity:.35;cursor:not-allowed;}
.apss-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);}
.apss-modal{max-width:560px;}
.apss-modal-foot{display:flex;justify-content:flex-end;gap:10px;padding:14px 24px;border-top:1px solid var(--border);flex-shrink:0;}
.apss-btn{padding:9px 18px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;}
.apss-btn:hover:not(:disabled){border-color:var(--primary);}
.apss-btn:disabled{opacity:.55;cursor:not-allowed;}
.apss-btn--primary{border-color:var(--primary);background:var(--primary);color:#fff;font-weight:700;}
.apss-btn--danger{border-color:#ef4444;background:#ef4444;color:#fff;font-weight:700;}
.apss-btn--danger:hover:not(:disabled){background:#dc2626;border-color:#dc2626;}
.apss-input{width:100%;box-sizing:border-box;padding:10px 13px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:14px;font-family:inherit;}
.apss-input:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
textarea.apss-input{min-height:84px;resize:vertical;}
textarea.apss-input--code{min-height:170px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;}
.apss-field-hint{margin:6px 0 0;font-size:11.5px;line-height:1.45;color:var(--muted);}
.apss-field-warn{margin:0 0 12px;padding:8px 11px;border-radius:9px;font-size:12px;line-height:1.45;color:#b45309;background:color-mix(in srgb,#f59e0b 12%,transparent);}
.apss-field-err{margin:8px 0 0;font-size:12px;font-weight:600;color:#ef4444;}
.apss-shared{margin:12px 0 0;font-size:11.5px;line-height:1.45;color:var(--muted);}
.apss-confirm-text{margin:0;font-size:13.5px;line-height:1.5;}
.apss-switch-row{display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;}
.apss-switch{position:relative;display:inline-block;width:36px;height:20px;flex-shrink:0;}
.apss-switch input{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer;}
.apss-switch-track{position:absolute;inset:0;background:color-mix(in srgb,var(--muted) 40%,transparent);border-radius:999px;transition:.15s ease;pointer-events:none;}
.apss-switch-track::before{content:"";position:absolute;width:14px;height:14px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.15s ease;box-shadow:0 1px 2px rgba(0,0,0,.3);}
.apss-switch input:checked ~ .apss-switch-track{background:#16a34a;}
.apss-switch input:checked ~ .apss-switch-track::before{transform:translateX(16px);}
.apss-switch input:focus-visible ~ .apss-switch-track{outline:2px solid var(--primary);outline-offset:2px;}
.apss-checks{display:grid;gap:7px;}
.apss-check{display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:11px;border:1px solid var(--border);font-size:13px;cursor:pointer;}
.apss-check input[type=checkbox]{width:15px;height:15px;accent-color:var(--primary);flex-shrink:0;}
.apss-check .apss-input{margin-left:auto;width:120px;padding:6px 9px;font-size:13px;}
.apss-tax-row{display:grid;grid-template-columns:1fr 118px 88px 34px;gap:6px;margin-bottom:6px;}
.apss-tax-row .apss-input{padding:8px 10px;font-size:13px;min-width:0;}
.apss-preview{display:block;max-width:120px;max-height:64px;margin-top:10px;border-radius:8px;border:1px solid var(--border);background:#fff;object-fit:contain;}
@media(max-width:640px){.apss-value-cell .apss-value,.apss-value-cell .apss-logo{max-width:62%;}.apss-chips{justify-content:flex-end;}}
@media(max-width:480px){.apss-tax-row{grid-template-columns:1fr 1fr;}}
</style>

<div class="ads-modal-overlay" id="apss-modal">
    <form class="ads-modal apss-modal" id="apss-form" role="dialog" aria-modal="true" aria-labelledby="apss-title" novalidate>
        <div class="ads-modal-header">
            <button type="button" class="ads-modal-close" data-apss-close aria-label="Close"><i class="fa fa-xmark"></i></button>
            <h3 class="ads-modal-title" id="apss-title"></h3>
            <p class="ads-modal-sub" id="apss-sub"></p>
        </div>
        <div class="ads-modal-body" id="apss-body"></div>
        <div class="apss-modal-foot">
            <button type="button" class="apss-btn" data-apss-close>Cancel</button>
            <button type="submit" class="apss-btn" id="apss-submit"></button>
        </div>
    </form>
</div>

<script>
(function () {
    var overlay = document.getElementById('apss-modal');
    var form = document.getElementById('apss-form');
    var title = document.getElementById('apss-title');
    var sub = document.getElementById('apss-sub');
    var body = document.getElementById('apss-body');
    var submit = document.getElementById('apss-submit');
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var SURFACE_LABELS = @json(collect(\Modules\Pos\Support\PosSettingsCatalog::SURFACES)->map(fn ($s) => $s['label']));
    var action = null; // what the open modal will do on submit

    function el(tag, props, children) {
        var node = document.createElement(tag);
        Object.keys(props || {}).forEach(function (name) {
            if (name === 'class') { node.className = props[name]; }
            else if (props[name] !== undefined && props[name] !== null) { node[name] = props[name]; }
        });
        (children || []).forEach(function (child) { node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child); });
        return node;
    }

    function request(url, method, payload) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: payload === undefined ? undefined : JSON.stringify(payload)
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok || !data.html) {
                    var first = data.errors && Object.values(data.errors)[0];
                    throw new Error((first && first[0]) || data.message || 'Something went wrong. Reload the page and try again.');
                }
                return data;
            });
        });
    }

    function render(panel, data) {
        var surface = panel.getAttribute('data-surface') || '';
        panel.innerHTML = data.html;
        showSurface(panel, panel.querySelector('[data-surface="' + surface + '"]') ? surface : panel.querySelector('.apss-surface').getAttribute('data-surface'));
        var flash = panel.querySelector('.apss-flash');
        if (data.message) { flash.textContent = data.message; flash.hidden = false; }
        panel.setAttribute('data-loaded', '1');
    }

    function showSurface(panel, surface) {
        panel.setAttribute('data-surface', surface);
        panel.querySelectorAll('.apss-surface').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-surface') === surface);
        });
        panel.querySelectorAll('.apss-group').forEach(function (group) {
            group.hidden = group.getAttribute('data-group') !== surface;
        });
    }

    function load(panel) {
        panel.innerHTML = '';
        panel.appendChild(el('div', { 'class': 'apss-loading' }, [el('i', { 'class': 'fa fa-spinner fa-spin' }), ' Loading settings...']));
        request(panel.getAttribute('data-url'), 'GET').then(function (data) {
            render(panel, data);
        }).catch(function (err) {
            panel.innerHTML = '';
            panel.appendChild(el('div', { 'class': 'apss-error' }, [err.message + ' ', el('button', { type: 'button', 'class': 'apss-btn', textContent: 'Try again', onclick: function () { load(panel); } })]));
        });
    }

    function rowOf(panel, setting) {
        return JSON.parse(panel.querySelector('.apss-data').textContent)[setting];
    }

    function selectControl(options, value) {
        var select = el('select', { 'class': 'apss-input' }, options.map(function (o) {
            return el('option', { value: o.value, textContent: o.label });
        }));
        select.value = value === null || value === undefined ? '' : String(value);
        return select;
    }

    // Each control returns its node plus how to read the value back.
    var controls = {
        toggle: function (row) {
            var input = el('input', { type: 'checkbox', checked: !!row.value });
            var state = el('span', { textContent: row.value ? 'On' : 'Off' });
            input.addEventListener('change', function () { state.textContent = input.checked ? 'On' : 'Off'; });
            return {
                node: el('label', { 'class': 'apss-switch-row' }, [el('span', { 'class': 'apss-switch' }, [input, el('span', { 'class': 'apss-switch-track' })]), state]),
                value: function () { return input.checked; }
            };
        },
        select: function (row) {
            var select = selectControl(row.options, row.value);
            return { node: select, value: function () { return select.value; } };
        },
        number: function (row) {
            var input = el('input', { type: 'number', 'class': 'apss-input', value: row.value === null || row.value === undefined ? '' : row.value, step: row.step || (row.integer ? 1 : 'any') });
            if (row.min !== undefined) { input.min = row.min; }
            if (row.max !== undefined) { input.max = row.max; }
            return { node: input, value: function () { return input.value === '' ? null : Number(input.value); } };
        },
        text: function (row) {
            var input = el('input', { type: 'text', 'class': 'apss-input', value: row.value === null || row.value === undefined ? '' : row.value });
            if (row.max) { input.maxLength = row.max; }
            return { node: input, value: function () { return input.value; } };
        },
        textarea: function (row) {
            var input = el('textarea', { 'class': 'apss-input', value: row.value === null || row.value === undefined ? '' : row.value });
            if (row.max) { input.maxLength = row.max; }
            return { node: input, value: function () { return input.value; } };
        },
        json: function (row) {
            var input = el('textarea', { 'class': 'apss-input apss-input--code', value: row.value || '', spellcheck: false });
            return { node: input, value: function () { return input.value; } };
        },
        url: function (row) {
            var input = el('input', { type: 'url', 'class': 'apss-input', value: row.value || '', placeholder: 'https://' });
            var preview = el('img', { 'class': 'apss-preview', alt: '', hidden: true });
            function refresh() {
                preview.hidden = !/^https?:\/\//i.test(input.value);
                if (!preview.hidden) { preview.src = input.value; }
            }
            preview.addEventListener('error', function () { preview.hidden = true; });
            input.addEventListener('input', refresh);
            refresh();
            return { node: el('div', {}, [input, preview]), value: function () { return input.value; } };
        },
        multiselect: function (row) {
            var boxes = row.options.map(function (o) {
                return { value: o.value, input: el('input', { type: 'checkbox', checked: (row.value || []).indexOf(o.value) !== -1 }), label: o.label };
            });
            return {
                node: el('div', { 'class': 'apss-checks' }, boxes.map(function (b) { return el('label', { 'class': 'apss-check' }, [b.input, b.label]); })),
                value: function () { return boxes.filter(function (b) { return b.input.checked; }).map(function (b) { return b.value; }); }
            };
        },
        courier: function (row) {
            var saved = row.value || {};
            var items = row.options.map(function (o) {
                var service = saved[o.value] || {};
                return {
                    key: o.value,
                    label: o.label,
                    enabled: el('input', { type: 'checkbox', checked: !!service.enabled }),
                    charge: el('input', { type: 'number', 'class': 'apss-input', min: 0, step: 'any', value: service.charge || 0, placeholder: 'Charge' })
                };
            });
            return {
                node: el('div', { 'class': 'apss-checks' }, items.map(function (i) { return el('label', { 'class': 'apss-check' }, [i.enabled, i.label, i.charge]); })),
                value: function () {
                    var out = {};
                    items.forEach(function (i) { out[i.key] = { enabled: i.enabled.checked, charge: i.charge.value === '' ? 0 : Number(i.charge.value) }; });
                    return out;
                }
            };
        },
        tax_rules: function (row) {
            var list = el('div');
            var rows = [];
            function addRow(rule) {
                var r = {
                    id: rule.id,
                    name: el('input', { type: 'text', 'class': 'apss-input', maxLength: 50, value: rule.name || '', placeholder: 'Name, e.g. VAT' }),
                    type: selectControl([{ value: 'percentage', label: 'Percentage' }, { value: 'flat', label: 'Flat amount' }], rule.type || 'percentage'),
                    amount: el('input', { type: 'number', 'class': 'apss-input', min: 0, step: 'any', value: rule.value === undefined ? '' : rule.value, placeholder: 'Value' })
                };
                var remove = el('button', { type: 'button', 'class': 'apss-icon-btn apss-icon-btn--danger', title: 'Remove rule', onclick: function () {
                    rows.splice(rows.indexOf(r), 1);
                    r.node.remove();
                } }, [el('i', { 'class': 'fa fa-xmark' })]);
                r.node = el('div', { 'class': 'apss-tax-row' }, [r.name, r.type, r.amount, remove]);
                rows.push(r);
                list.appendChild(r.node);
            }
            (row.value || []).forEach(addRow);
            return {
                node: el('div', {}, [list, el('button', { type: 'button', 'class': 'apss-btn', onclick: function () { addRow({}); } }, [el('i', { 'class': 'fa fa-plus' }), ' Add tax rule'])]),
                value: function () {
                    return rows.map(function (r) {
                        return { id: r.id, name: r.name.value, type: r.type.value, value: r.amount.value === '' ? null : Number(r.amount.value) };
                    });
                }
            };
        }
    };
    controls.currency = controls.select;
    controls.timezone = controls.select;
    controls.account = controls.select;

    function openModal(config) {
        action = config.run;
        title.textContent = '';
        title.appendChild(el('i', { 'class': 'fa ' + config.icon, style: 'color:' + config.iconColor }));
        title.appendChild(document.createTextNode(' ' + config.title));
        sub.textContent = config.sub;
        body.innerHTML = '';
        config.content.forEach(function (node) { if (node) { body.appendChild(node); } });
        submit.textContent = config.submit;
        submit.className = 'apss-btn ' + config.submitClass;
        submit.disabled = false;
        overlay.classList.add('is-open');
        var first = body.querySelector('input,select,textarea');
        if (first) { first.focus(); }
    }

    function closeModal() {
        overlay.classList.remove('is-open');
        action = null;
    }

    function appNames(row) {
        return row.surfaces.map(function (s) { return SURFACE_LABELS[s]; });
    }

    function openEdit(panel, setting) {
        var row = rowOf(panel, setting);
        var control = (controls[row.type] || controls.text)(row);
        var apps = appNames(row);
        openModal({
            icon: 'fa-pen', iconColor: 'var(--primary)', title: 'Edit ' + row.label, sub: row.key === row.label ? row.section : row.key,
            submit: 'Save', submitClass: 'apss-btn--primary',
            content: [
                row.warning ? el('p', { 'class': 'apss-field-warn', textContent: row.warning }) : null,
                control.node,
                row.help ? el('p', { 'class': 'apss-field-hint', textContent: row.help }) : null,
                row.type === 'number' && row.unit ? el('p', { 'class': 'apss-field-hint', textContent: 'In ' + row.unit + (row.min !== undefined && row.max !== undefined ? ', from ' + row.min + ' to ' + row.max : '') + '.' }) : null,
                apps.length > 1 ? el('p', { 'class': 'apss-shared', textContent: 'Shared setting: saving changes it for ' + apps.join(', ') + '.' }) : null
            ],
            run: function () {
                return request(panel.getAttribute('data-url') + '/' + encodeURIComponent(setting), 'PUT', { value: control.value() }).then(function (data) { render(panel, data); });
            }
        });
    }

    function openDelete(panel, setting) {
        var row = rowOf(panel, setting);
        var apps = appNames(row);
        openModal({
            icon: 'fa-trash', iconColor: '#ef4444', title: 'Delete saved value?', sub: row.label,
            submit: 'Delete', submitClass: 'apss-btn--danger',
            content: [
                el('p', { 'class': 'apss-confirm-text', textContent: apps.length ? 'This removes the value saved for this business. ' + apps.join(', ') + ' will go back to the default:' : 'This removes the value saved for this business.' }),
                apps.length ? el('p', { 'class': 'apss-field-hint', textContent: row.default_label }) : null,
                setting === 'receipt_logo_url' ? el('p', { 'class': 'apss-field-hint', textContent: 'If this logo was uploaded from the receipt settings, the file is deleted too.' }) : null
            ],
            run: function () {
                return request(panel.getAttribute('data-url') + '/' + encodeURIComponent(setting), 'DELETE').then(function (data) { render(panel, data); });
            }
        });
    }

    document.addEventListener('click', function (e) {
        var target = e.target.closest ? e.target : e.target.parentElement;
        var tab = target.closest('.ads-biz-tab[data-tab="pos-settings"]');
        if (tab) {
            var tabPanel = tab.closest('.ads-biz').querySelector('[data-panel="pos-settings"]');
            if (!tabPanel.getAttribute('data-loaded')) { load(tabPanel); }
            return;
        }
        var panel = target.closest('[data-panel="pos-settings"]');
        if (panel) {
            var surface = target.closest('.apss-surface');
            var edit = target.closest('[data-apss-edit]');
            var del = target.closest('[data-apss-delete]');
            if (surface) { showSurface(panel, surface.getAttribute('data-surface')); }
            if (edit) { openEdit(panel, edit.getAttribute('data-apss-edit')); }
            if (del && !del.disabled) { openDelete(panel, del.getAttribute('data-apss-delete')); }
        }
        if (target.closest('[data-apss-close]') || e.target === overlay) { closeModal(); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) { closeModal(); }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!action) { return; }
        var previous = body.querySelector('.apss-field-err');
        if (previous) { previous.remove(); }
        submit.disabled = true;
        action().then(closeModal).catch(function (err) {
            submit.disabled = false;
            body.appendChild(el('p', { 'class': 'apss-field-err', textContent: err.message }));
        });
    });
})();
</script>
