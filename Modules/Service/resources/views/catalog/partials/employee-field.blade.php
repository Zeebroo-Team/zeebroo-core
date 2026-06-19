@php
    $empfId       = isset($fieldIdPrefix) ? $fieldIdPrefix . '-svc-emp' : 'svc-emp';
    $empList      = isset($employees) ? $employees : collect();
    $empForJs     = $empList->map(fn ($e) => [
        'id'       => (int) $e->id,
        'name'     => $e->full_name,
        'subtitle' => $e->jobTitle?->name ?? '',
    ])->values();

    $selectedIds  = collect(old('employee_ids',
        isset($item) && $item?->relationLoaded('employees')
            ? $item->employees->pluck('id')->all()
            : []
    ))->map(fn ($id) => (int) $id)->filter()->unique()->values();

    $initialTags = collect();
    foreach ($selectedIds as $sid) {
        $match = $empList->firstWhere('id', $sid);
        if ($match) {
            $initialTags->push(['id' => (int) $match->id, 'name' => $match->full_name]);
        }
    }
@endphp

@once
<style>
.svc-emp-field__label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.svc-emp-field__wrap{position:relative;}
.svc-emp-field__box{
    display:flex;flex-wrap:wrap;align-items:center;gap:6px;min-height:42px;padding:6px 8px;
    border:1px solid var(--border);border-radius:8px;background:var(--card);cursor:text;
    transition:border-color .15s,box-shadow .15s;
}
.svc-emp-field__box:focus-within{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.svc-emp-field__chip{
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 8px 4px 10px;border-radius:999px;font-size:12px;font-weight:600;line-height:1.2;
    border:1px solid color-mix(in srgb,#6366f1 35%,var(--border));
    background:color-mix(in srgb,#6366f1 10%,transparent);color:var(--text);
}
.svc-emp-field__chip-remove{
    display:grid;place-items:center;width:18px;height:18px;padding:0;margin:0;
    border:none;border-radius:999px;background:color-mix(in srgb,var(--card) 50%,transparent);
    color:var(--muted);font-size:14px;line-height:1;cursor:pointer;
}
.svc-emp-field__chip-remove:hover{background:color-mix(in srgb,#f87171 18%,transparent);color:#f87171;}
.svc-emp-field__input{
    flex:1 1 120px;min-width:100px;border:none;outline:none;background:transparent;
    padding:4px 2px;font-size:13px;color:var(--text);
}
.svc-emp-field__input::placeholder{color:var(--muted);opacity:.85;}
.svc-emp-field__suggest{
    position:fixed;z-index:9000;
    margin:0;padding:4px 0;list-style:none;
    max-height:220px;overflow:auto;border:1px solid var(--border);border-radius:10px;
    background:var(--card);box-shadow:0 12px 28px rgba(0,0,0,.22);
}
.svc-emp-field__suggest[hidden]{display:none;}
.svc-emp-field__suggest li{margin:0;}
.svc-emp-field__suggest button{
    display:block;width:100%;text-align:left;padding:8px 12px;border:none;background:transparent;
    font-size:13px;color:var(--text);cursor:pointer;line-height:1.3;
}
.svc-emp-field__suggest button:hover,
.svc-emp-field__suggest button:focus-visible{background:color-mix(in srgb,#6366f1 10%,transparent);outline:none;}
.svc-emp-field__suggest .emp-subtitle{display:block;font-size:11px;color:var(--muted);font-weight:400;}
.svc-emp-field__suggest .emp-empty{color:var(--muted);cursor:default;font-style:italic;}
.svc-emp-field__suggest .emp-empty:hover{background:transparent;}
.svc-emp-field__hint{margin:5px 0 0;font-size:11px;line-height:1.4;color:var(--muted);}
.svc-emp-field__hidden{display:none;}
</style>
@endonce

<div class="product-field svc-emp-field" id="{{ $empfId }}-field" style="grid-column:1/-1;">
    <label class="svc-emp-field__label" id="{{ $empfId }}-label">Assigned Employees</label>
    <div class="svc-emp-field__wrap">
        <div class="svc-emp-field__box" id="{{ $empfId }}"
             role="group" aria-labelledby="{{ $empfId }}-label"
             data-svc-emp-root
             data-catalog='@json($empForJs)'
             data-initial-tags='@json($initialTags->values())'>
            <div class="svc-emp-field__chips" data-svc-emp-chips></div>
            <input type="text" class="svc-emp-field__input" data-svc-emp-input
                   placeholder="{{ $empList->isEmpty() ? 'No employees found' : 'Search employees…' }}"
                   autocomplete="off" aria-autocomplete="list"
                   aria-controls="{{ $empfId }}-suggest" aria-expanded="false"
                   {{ $empList->isEmpty() ? 'readonly' : '' }}>
        </div>
        <ul id="{{ $empfId }}-suggest" class="svc-emp-field__suggest" data-svc-emp-suggest hidden role="listbox"></ul>
    </div>
    @if($empList->isEmpty())
        <p class="svc-emp-field__hint">No employees in this business yet. <a href="{{ route('hr.employees.index') }}" style="color:var(--primary);font-weight:600;">Go to HR</a> to add employees.</p>
    @else
        <p class="svc-emp-field__hint">Search and select one or more employees who can perform this service.</p>
    @endif
    <div class="svc-emp-field__hidden" data-svc-emp-hidden aria-hidden="true"></div>
    @error('employee_ids')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('employee_ids.*')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

@once
<script>
(function () {
    if (window.__svcEmpTagsInit) return;
    window.__svcEmpTagsInit = true;

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function initSvcEmpTags(root) {
        if (!root || root.dataset.svcEmpReady === '1') return;
        root.dataset.svcEmpReady = '1';

        const catalog   = JSON.parse(root.dataset.catalog      || '[]');
        const chipsEl   = root.querySelector('[data-svc-emp-chips]');
        const inputEl   = root.querySelector('[data-svc-emp-input]');
        const hiddenEl  = root.closest('.svc-emp-field')?.querySelector('[data-svc-emp-hidden]');
        const suggestEl = root.closest('.svc-emp-field__wrap')?.querySelector('[data-svc-emp-suggest]');
        const wrapEl    = root.closest('.svc-emp-field__wrap');

        if (!chipsEl || !inputEl || !hiddenEl) return;

        const selected = new Map();

        function syncHidden() {
            hiddenEl.innerHTML = '';
            selected.forEach(tag => {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'employee_ids[]';
                inp.value = String(tag.id);
                hiddenEl.appendChild(inp);
            });
        }

        function renderChips() {
            chipsEl.innerHTML = '';
            selected.forEach((tag, key) => {
                const chip = document.createElement('span');
                chip.className = 'svc-emp-field__chip';
                chip.innerHTML = '<i class="fa fa-user" style="font-size:10px;opacity:.7;" aria-hidden="true"></i>'
                    + '<span>' + esc(tag.name) + '</span>'
                    + '<button type="button" class="svc-emp-field__chip-remove" aria-label="Remove ' + esc(tag.name) + '">&times;</button>';
                chip.querySelector('button').addEventListener('click', e => {
                    e.stopPropagation();
                    selected.delete(key);
                    renderChips();
                    syncHidden();
                    filterSuggest();
                });
                chipsEl.appendChild(chip);
            });
            syncHidden();
        }

        function addTag(emp) {
            const key = 'id:' + emp.id;
            if (selected.has(key)) return;
            selected.set(key, emp);
            renderChips();
            inputEl.value = '';
            closeSuggest();
            inputEl.focus();
        }

        function filterSuggest() {
            if (!suggestEl) return;
            const q      = inputEl.value.trim().toLowerCase();
            const matches = catalog.filter(e => {
                if (selected.has('id:' + e.id)) return false;
                return !q || e.name.toLowerCase().includes(q) || (e.subtitle || '').toLowerCase().includes(q);
            }).slice(0, 10);

            suggestEl.innerHTML = '';

            if (matches.length === 0) {
                const li  = document.createElement('li');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'emp-empty';
                btn.textContent = q ? 'No matching employees.' : 'All employees already assigned.';
                li.appendChild(btn);
                suggestEl.appendChild(li);
            } else {
                matches.forEach(e => {
                    const li  = document.createElement('li');
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.innerHTML = '<span>' + esc(e.name) + '</span>'
                        + (e.subtitle ? '<span class="emp-subtitle">' + esc(e.subtitle) + '</span>' : '');
                    btn.addEventListener('mousedown', ev => ev.preventDefault());
                    btn.addEventListener('click', () => addTag(e));
                    li.appendChild(btn);
                    suggestEl.appendChild(li);
                });
            }

            if (wrapEl) {
                const r = wrapEl.getBoundingClientRect();
                suggestEl.style.top   = (r.bottom + 4) + 'px';
                suggestEl.style.left  = r.left + 'px';
                suggestEl.style.width = r.width + 'px';
            }
            suggestEl.hidden = false;
            inputEl.setAttribute('aria-expanded', 'true');
        }

        function closeSuggest() {
            if (!suggestEl) return;
            suggestEl.hidden = true;
            inputEl.setAttribute('aria-expanded', 'false');
        }

        inputEl.addEventListener('focus', filterSuggest);
        inputEl.addEventListener('input', filterSuggest);
        inputEl.addEventListener('keydown', e => {
            if (e.key === 'Escape') { closeSuggest(); inputEl.blur(); }
            if (e.key === 'Enter') {
                e.preventDefault();
                const q = inputEl.value.trim().toLowerCase();
                const exact = catalog.find(emp => emp.name.toLowerCase() === q && !selected.has('id:' + emp.id));
                if (exact) addTag(exact);
            }
            if (e.key === 'Backspace' && inputEl.value === '' && selected.size > 0) {
                const keys = Array.from(selected.keys());
                selected.delete(keys[keys.length - 1]);
                renderChips();
                filterSuggest();
            }
        });

        root.addEventListener('click', () => inputEl.focus());
        document.addEventListener('click', e => {
            if (wrapEl && !wrapEl.contains(e.target)) closeSuggest();
        });
        document.addEventListener('scroll', closeSuggest, true);

        JSON.parse(root.dataset.initialTags || '[]').forEach(tag => {
            selected.set('id:' + tag.id, tag);
        });
        renderChips();
    }

    window.initSvcEmpTags = function (container) {
        (container || document).querySelectorAll('[data-svc-emp-root]').forEach(initSvcEmpTags);
    };
})();
</script>
@endonce

<script>
(function () {
    const root = document.getElementById(@json($empfId));
    if (root && window.initSvcEmpTags) window.initSvcEmpTags(root.closest('.svc-emp-field') || root);
})();
</script>
