@php
    $pfxRaw = isset($fieldIdPrefix) ? (string) $fieldIdPrefix : '';
    $tagsRootId = $pfxRaw !== '' ? $pfxRaw . '-brand-tags' : 'product-brand-tags';
    $productModel = $product ?? null;
    $brands = $brands ?? collect();
    $selectedBrandIds = collect(old('product_brand_ids', $productModel?->brands?->pluck('id')->all() ?? []))
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values();
    $pendingNewNames = collect(old('new_brand_names', filled(old('new_brand_name')) ? [old('new_brand_name')] : []))
        ->map(fn ($n) => trim((string) $n))
        ->filter()
        ->unique()
        ->values();
    $catalogForJs = $brands->map(fn ($b) => ['id' => (int) $b->id, 'name' => $b->name])->values();
    $initialTags = collect();
    foreach ($selectedBrandIds as $brandId) {
        $match = $brands->firstWhere('id', $brandId)
            ?? $productModel?->brands?->firstWhere('id', $brandId);
        if (!$match) {
            continue;
        }
        $initialTags->push([
            'id' => (int) $match->id,
            'name' => $match->name,
            'isNew' => false,
        ]);
    }
    foreach ($pendingNewNames as $pendingName) {
        if (!$initialTags->contains(fn ($t) => strcasecmp($t['name'], $pendingName) === 0)) {
            $initialTags->push(['id' => null, 'name' => $pendingName, 'isNew' => true]);
        }
    }
    $initialTagsJson = $initialTags->values();
@endphp

<div class="product-field product-brand-tags-field" style="grid-column:1/-1;">
    <label class="product-cat-tags__label" id="{{ $tagsRootId }}-label">Brands</label>
    <div class="product-cat-tags__wrap">
        <div
            id="{{ $tagsRootId }}"
            class="product-cat-tags__box"
            role="group"
            aria-labelledby="{{ $tagsRootId }}-label"
            data-brand-tags-root
            data-prefix="{{ $pfxRaw }}"
            data-catalog='@json($catalogForJs)'
            data-initial-tags='@json($initialTagsJson)'>
            <div class="product-cat-tags__chips" data-brand-tags-chips></div>
            <input
                type="text"
                class="product-cat-tags__input"
                data-brand-tags-input
                placeholder="{{ $brands->isEmpty() ? 'Type a brand name and press Enter' : 'Search or add brand…' }}"
                autocomplete="off"
                aria-autocomplete="list"
                aria-controls="{{ $tagsRootId }}-suggest"
                aria-expanded="false">
        </div>
        <ul id="{{ $tagsRootId }}-suggest" class="product-cat-tags__suggest" data-brand-tags-suggest hidden role="listbox"></ul>
    </div>
    <p class="product-cat-tags__hint">Press Enter to add. Pick from the list or create a new brand by typing a name.</p>
    <div class="product-cat-tags__hidden" data-brand-tags-hidden-inputs aria-hidden="true"></div>
    @error('new_brand_name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('new_brand_names')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('new_brand_names.*')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('product_brand_ids')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('product_brand_ids.*')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    <a href="{{ route('product.brands.index') }}" class="product-field__manage" style="font-size:11px;margin-top:8px;display:inline-block;color:var(--primary);font-weight:600;">Manage all brands</a>
</div>

@once
<script>
(function () {
    if (window.__productBrandTagsInit) return;
    window.__productBrandTagsInit = true;

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initBrandTags(root) {
        if (!root || root.dataset.brandTagsReady === '1') return;
        root.dataset.brandTagsReady = '1';

        const catalog = JSON.parse(root.dataset.catalog || '[]');
        const chipsEl = root.querySelector('[data-brand-tags-chips]');
        const inputEl = root.querySelector('[data-brand-tags-input]');
        const hiddenEl = root.closest('.product-brand-tags-field')?.querySelector('[data-brand-tags-hidden-inputs]');
        const suggestEl = root.closest('.product-cat-tags__wrap')?.querySelector('[data-brand-tags-suggest]')
            || document.getElementById(root.id + '-suggest');
        const wrapEl = root.closest('.product-cat-tags__wrap');

        if (!chipsEl || !inputEl || !hiddenEl) return;

        const selected = new Map();

        function syncHidden() {
            hiddenEl.innerHTML = '';
            selected.forEach((tag) => {
                if (tag.isNew) {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'new_brand_names[]';
                    inp.value = tag.name;
                    hiddenEl.appendChild(inp);
                } else if (tag.id) {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'product_brand_ids[]';
                    inp.value = String(tag.id);
                    hiddenEl.appendChild(inp);
                }
            });
        }

        function renderChips() {
            chipsEl.innerHTML = '';
            selected.forEach((tag, key) => {
                const chip = document.createElement('span');
                chip.className = 'product-cat-tags__chip' + (tag.isNew ? ' product-cat-tags__chip--new' : '');
                chip.dataset.tagKey = key;
                const label = tag.isNew ? tag.name + ' (new)' : tag.name;
                chip.innerHTML = '<span>' + escapeHtml(label) + '</span>'
                    + '<button type="button" class="product-cat-tags__chip-remove" aria-label="Remove ' + escapeHtml(tag.name) + '">&times;</button>';
                chip.querySelector('button').addEventListener('click', (e) => {
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
            if (id && selected.has('id:' + id)) return true;
            if (name && selected.has('new:' + name.toLowerCase())) return true;
            return Array.from(selected.values()).some((t) => t.name.toLowerCase() === String(name).toLowerCase());
        }

        function filterSuggest() {
            if (!suggestEl) return;
            const q = inputEl.value.trim().toLowerCase();
            const matches = catalog.filter((c) => {
                if (isSelected(c.id, c.name)) return false;
                if (!q) return true;
                return c.name.toLowerCase().includes(q);
            }).slice(0, 8);

            suggestEl.innerHTML = '';
            matches.forEach((c) => {
                const li = document.createElement('li');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = c.name;
                btn.addEventListener('mousedown', (e) => e.preventDefault());
                btn.addEventListener('click', () => addTag({ id: c.id, name: c.name, isNew: false }));
                li.appendChild(btn);
                suggestEl.appendChild(li);
            });

            if (q && !catalog.some((c) => c.name.toLowerCase() === q) && !isSelected(null, q)) {
                const li = document.createElement('li');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'is-create';
                btn.textContent = 'Create brand “' + inputEl.value.trim() + '”';
                btn.addEventListener('mousedown', (e) => e.preventDefault());
                btn.addEventListener('click', () => addTag({ id: null, name: inputEl.value.trim(), isNew: true }));
                li.appendChild(btn);
                suggestEl.appendChild(li);
            }

            const show = suggestEl.children.length > 0;
            suggestEl.hidden = !show;
            inputEl.setAttribute('aria-expanded', show ? 'true' : 'false');
        }

        function openSuggest() { filterSuggest(); }
        function closeSuggest() {
            if (!suggestEl) return;
            suggestEl.hidden = true;
            inputEl.setAttribute('aria-expanded', 'false');
        }

        inputEl.addEventListener('focus', openSuggest);
        inputEl.addEventListener('input', filterSuggest);
        inputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const q = inputEl.value.trim();
                if (!q) return;
                const exact = catalog.find((c) => c.name.toLowerCase() === q.toLowerCase() && !isSelected(c.id, c.name));
                if (exact) {
                    addTag({ id: exact.id, name: exact.name, isNew: false });
                } else if (!isSelected(null, q)) {
                    addTag({ id: null, name: q, isNew: true });
                }
                return;
            }
            if (e.key === 'Escape') {
                closeSuggest();
                inputEl.blur();
            }
            if (e.key === 'Backspace' && inputEl.value === '' && selected.size > 0) {
                const keys = Array.from(selected.keys());
                selected.delete(keys[keys.length - 1]);
                renderChips();
                filterSuggest();
            }
        });

        root.addEventListener('click', () => inputEl.focus());
        document.addEventListener('click', (e) => {
            if (wrapEl && !wrapEl.contains(e.target)) closeSuggest();
        });

        const initial = JSON.parse(root.dataset.initialTags || '[]');
        initial.forEach((tag) => {
            const key = tag.isNew ? 'new:' + tag.name.toLowerCase() : 'id:' + tag.id;
            selected.set(key, tag);
        });
        renderChips();

        root._resetBrandTags = function () {
            selected.clear();
            renderChips();
            inputEl.value = '';
            closeSuggest();
        };
    }

    window.initProductBrandTags = function (container) {
        (container || document).querySelectorAll('[data-brand-tags-root]').forEach(initBrandTags);
    };

    window.resetProductBrandTags = function (container) {
        (container || document).querySelectorAll('[data-brand-tags-root]').forEach(function (root) {
            if (typeof root._resetBrandTags === 'function') {
                root._resetBrandTags();
            }
        });
    };

})();
</script>
@endonce

<script>
(function () {
    const root = document.getElementById(@json($tagsRootId));
    if (!root || !window.initProductBrandTags) return;
    window.initProductBrandTags(root.closest('.product-brand-tags-field') || root);
})();
</script>
