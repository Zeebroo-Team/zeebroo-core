@php
    $scfId        = isset($fieldIdPrefix) ? $fieldIdPrefix . '-svc-cat' : 'svc-cat';
    $catList      = isset($serviceCategories) ? $serviceCategories : collect();
    $catForJs     = $catList->map(fn ($c) => ['id' => (int) $c->id, 'name' => $c->name])->values();

    $selectedIds  = collect(old('service_category_ids',
        isset($item) && $item?->relationLoaded('categories')
            ? $item->categories->pluck('id')->all()
            : []
    ))->map(fn ($id) => (int) $id)->filter()->unique()->values();

    $pendingNames = collect(old('new_category_names', []))
        ->map(fn ($n) => trim((string) $n))->filter()->unique()->values();

    $initialTags = collect();
    foreach ($selectedIds as $sid) {
        $match = $catList->firstWhere('id', $sid);
        if ($match) $initialTags->push(['id' => (int) $match->id, 'name' => $match->name, 'isNew' => false]);
    }
    foreach ($pendingNames as $pn) {
        if (!$initialTags->contains(fn ($t) => strcasecmp($t['name'], $pn) === 0)) {
            $initialTags->push(['id' => null, 'name' => $pn, 'isNew' => true]);
        }
    }
@endphp

@once
<style>
.svc-cat-tags__label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.svc-cat-tags__wrap{position:relative;}
.svc-cat-tags__box{
    display:flex;flex-wrap:wrap;align-items:center;gap:6px;min-height:42px;padding:6px 8px;
    border:1px solid var(--border);border-radius:8px;background:var(--card);cursor:text;
    transition:border-color .15s,box-shadow .15s;
}
.svc-cat-tags__box:focus-within{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.svc-cat-tags__chip{
    display:inline-flex;align-items:center;gap:5px;max-width:100%;
    padding:4px 8px 4px 10px;border-radius:999px;font-size:12px;font-weight:600;line-height:1.2;
    border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));
    background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);
}
.svc-cat-tags__chip--new{
    border-color:color-mix(in srgb,#22c55e 40%,var(--border));
    background:color-mix(in srgb,#22c55e 10%,transparent);
}
.svc-cat-tags__chip-remove{
    display:grid;place-items:center;width:18px;height:18px;padding:0;margin:0;
    border:none;border-radius:999px;background:color-mix(in srgb,var(--card) 50%,transparent);
    color:var(--muted);font-size:14px;line-height:1;cursor:pointer;
}
.svc-cat-tags__chip-remove:hover{background:color-mix(in srgb,#f87171 18%,transparent);color:#f87171;}
.svc-cat-tags__input{
    flex:1 1 120px;min-width:100px;border:none;outline:none;background:transparent;
    padding:4px 2px;font-size:13px;color:var(--text);
}
.svc-cat-tags__input::placeholder{color:var(--muted);opacity:.85;}
.svc-cat-tags__suggest{
    position:fixed;z-index:9000;
    margin:0;padding:4px 0;list-style:none;
    max-height:220px;overflow:auto;border:1px solid var(--border);border-radius:10px;
    background:var(--card);box-shadow:0 12px 28px rgba(0,0,0,.22);
}
.svc-cat-tags__suggest[hidden]{display:none;}
.svc-cat-tags__suggest li{margin:0;}
.svc-cat-tags__suggest button{
    display:block;width:100%;text-align:left;padding:8px 12px;border:none;background:transparent;
    font-size:13px;color:var(--text);cursor:pointer;
}
.svc-cat-tags__suggest button:hover,
.svc-cat-tags__suggest button:focus-visible{background:color-mix(in srgb,var(--primary) 10%,transparent);outline:none;}
.svc-cat-tags__suggest button.is-create{font-weight:700;color:var(--primary);}
.svc-cat-tags__suggest .svc-cat-tags__divider{height:1px;background:var(--border);margin:4px 0;}
.svc-cat-tags__hint{margin:5px 0 0;font-size:11px;line-height:1.4;color:var(--muted);}
.svc-cat-tags__hidden{display:none;}
</style>
@endonce

<div class="product-field svc-cat-tags-field" id="{{ $scfId }}-field">
    <label class="svc-cat-tags__label" id="{{ $scfId }}-label">Categories</label>
    <div class="svc-cat-tags__wrap">
        <div class="svc-cat-tags__box" id="{{ $scfId }}"
             role="group" aria-labelledby="{{ $scfId }}-label"
             data-svc-cat-root
             data-catalog='@json($catForJs)'
             data-initial-tags='@json($initialTags->values())'
>
            <div class="svc-cat-tags__chips" data-svc-cat-chips></div>
            <input type="text" class="svc-cat-tags__input" data-svc-cat-input
                   placeholder="Search or add category…"
                   autocomplete="off" aria-autocomplete="list"
                   aria-controls="{{ $scfId }}-suggest" aria-expanded="false">
        </div>
        <ul id="{{ $scfId }}-suggest" class="svc-cat-tags__suggest" data-svc-cat-suggest hidden role="listbox"></ul>
    </div>
    <p class="svc-cat-tags__hint">Press Enter or pick from the list. Type a new name to create a category instantly. <a href="{{ route('service.categories.index') }}" style="color:var(--primary);font-weight:600;">Manage categories</a></p>
    <div class="svc-cat-tags__hidden" data-svc-cat-hidden aria-hidden="true"></div>
    @error('service_category_ids')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('service_category_ids.*')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('new_category_names.*')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

@once
<script>
(function () {
    if (window.__svcCatTagsInit) return;
    window.__svcCatTagsInit = true;

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function initSvcCatTags(root) {
        if (!root || root.dataset.svcCatReady === '1') return;
        root.dataset.svcCatReady = '1';

        const catalog = JSON.parse(root.dataset.catalog || '[]');
        const chipsEl   = root.querySelector('[data-svc-cat-chips]');
        const inputEl   = root.querySelector('[data-svc-cat-input]');
        const hiddenEl  = root.closest('.svc-cat-tags-field')?.querySelector('[data-svc-cat-hidden]');
        const suggestEl = root.closest('.svc-cat-tags__wrap')?.querySelector('[data-svc-cat-suggest]');
        const wrapEl    = root.closest('.svc-cat-tags__wrap');

        if (!chipsEl || !inputEl || !hiddenEl) return;

        const selected = new Map();

        function syncHidden() {
            hiddenEl.innerHTML = '';
            selected.forEach(tag => {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                if (tag.isNew) {
                    inp.name  = 'new_category_names[]';
                    inp.value = tag.name;
                } else {
                    inp.name  = 'service_category_ids[]';
                    inp.value = String(tag.id);
                }
                hiddenEl.appendChild(inp);
            });
        }

        function renderChips() {
            chipsEl.innerHTML = '';
            selected.forEach((tag, key) => {
                const chip = document.createElement('span');
                chip.className = 'svc-cat-tags__chip' + (tag.isNew ? ' svc-cat-tags__chip--new' : '');
                chip.dataset.tagKey = key;
                const label = tag.isNew ? tag.name + ' (new)' : tag.name;
                chip.innerHTML = '<span>' + esc(label) + '</span>'
                    + '<button type="button" class="svc-cat-tags__chip-remove" aria-label="Remove ' + esc(tag.name) + '">&times;</button>';
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

        function addTag(tag) {
            const key = tag.isNew ? 'new:' + tag.name.toLowerCase() : 'id:' + tag.id;
            if (selected.has(key)) return;
            selected.set(key, tag);
            renderChips();
            inputEl.value = '';
            closeSuggest();
            inputEl.focus();
        }

        function isSelected(id, name) {
            if (id   && selected.has('id:'  + id)) return true;
            if (name && selected.has('new:' + name.toLowerCase())) return true;
            return Array.from(selected.values()).some(t => t.name.toLowerCase() === String(name).toLowerCase());
        }

        function quickCreate(name) {
            addTag({ id: null, name, isNew: true });
        }

        function filterSuggest() {
            if (!suggestEl) return;
            const q      = inputEl.value.trim();
            const qLower = q.toLowerCase();
            const matches = catalog.filter(c => {
                if (isSelected(c.id, c.name)) return false;
                return !q || c.name.toLowerCase().includes(qLower);
            }).slice(0, 10);

            suggestEl.innerHTML = '';

            matches.forEach(c => {
                const li  = document.createElement('li');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = c.name;
                btn.addEventListener('mousedown', e => e.preventDefault());
                btn.addEventListener('click', () => addTag({ id: c.id, name: c.name, isNew: false }));
                li.appendChild(btn);
                suggestEl.appendChild(li);
            });

            const exactMatch = catalog.some(c => c.name.toLowerCase() === qLower);
            if (q && !exactMatch && !isSelected(null, q)) {
                if (matches.length > 0) {
                    const sep = document.createElement('li');
                    sep.innerHTML = '<div class="svc-cat-tags__divider"></div>';
                    suggestEl.appendChild(sep);
                }
                const li  = document.createElement('li');
                const btn = document.createElement('button');
                btn.type      = 'button';
                btn.className = 'is-create';
                btn.innerHTML = '<i class="fa fa-plus" style="margin-right:6px;font-size:11px;" aria-hidden="true"></i>Create <strong>' + esc(q) + '</strong>';
                btn.addEventListener('mousedown', e => e.preventDefault());
                btn.addEventListener('click', () => quickCreate(q));
                li.appendChild(btn);
                suggestEl.appendChild(li);
            }

            const show = suggestEl.children.length > 0;
            if (show && wrapEl) {
                const r = wrapEl.getBoundingClientRect();
                suggestEl.style.top   = (r.bottom + 4) + 'px';
                suggestEl.style.left  = r.left + 'px';
                suggestEl.style.width = r.width + 'px';
            }
            suggestEl.hidden = !show;
            inputEl.setAttribute('aria-expanded', show ? 'true' : 'false');
        }

        function closeSuggest() {
            if (!suggestEl) return;
            suggestEl.hidden = true;
            inputEl.setAttribute('aria-expanded', 'false');
        }

        inputEl.addEventListener('focus', filterSuggest);
        inputEl.addEventListener('input', filterSuggest);
        inputEl.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const q     = inputEl.value.trim();
                const exact = catalog.find(c => c.name.toLowerCase() === q.toLowerCase() && !isSelected(c.id, c.name));
                if (exact) {
                    addTag({ id: exact.id, name: exact.name, isNew: false });
                } else if (q && !isSelected(null, q)) {
                    const createBtn = suggestEl?.querySelector('.is-create');
                    if (createBtn) createBtn.click();
                }
                return;
            }
            if (e.key === 'Escape') { closeSuggest(); inputEl.blur(); }
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

        const initial = JSON.parse(root.dataset.initialTags || '[]');
        initial.forEach(tag => {
            const key = tag.isNew ? 'new:' + tag.name.toLowerCase() : 'id:' + tag.id;
            selected.set(key, tag);
        });
        renderChips();

        root._resetSvcCatTags = function () {
            selected.clear();
            renderChips();
            inputEl.value = '';
            closeSuggest();
        };
    }

    window.initSvcCatTags = function (container) {
        (container || document).querySelectorAll('[data-svc-cat-root]').forEach(initSvcCatTags);
    };

    window.resetSvcCatTags = function (container) {
        (container || document).querySelectorAll('[data-svc-cat-root]').forEach(r => r._resetSvcCatTags?.());
    };
})();
</script>
@endonce

<script>
(function () {
    const root = document.getElementById(@json($scfId));
    if (root && window.initSvcCatTags) window.initSvcCatTags(root.closest('.svc-cat-tags-field') || root);
})();
</script>
