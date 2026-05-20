@php
    $pfxRaw = isset($fieldIdPrefix) ? (string) $fieldIdPrefix : '';
    $tagsRootId = $pfxRaw !== '' ? $pfxRaw . '-cat-tags' : 'product-cat-tags';
    $productModel = $product ?? null;
    $categories = $categories ?? collect();
    $selectedCategoryIds = collect(old('product_category_ids', $productModel?->categories?->pluck('id')->all() ?? []))
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values();
    $pendingNewNames = collect(old('new_category_names', filled(old('new_category_name')) ? [old('new_category_name')] : []))
        ->map(fn ($n) => trim((string) $n))
        ->filter()
        ->unique()
        ->values();
    $categoryLabelService = app(\Modules\Product\Services\ProductCategoryService::class);
    $categoriesIndexed = $categories->keyBy('id');
    $categoryLabel = static function ($c) use ($categoryLabelService, $categoriesIndexed): string {
        return $categoryLabelService->breadcrumbLabel($c, $categoriesIndexed);
    };
    $catalogForJs = $categories->map(fn ($c) => ['id' => (int) $c->id, 'name' => $categoryLabel($c)])->values();
    $initialTags = collect();
    foreach ($selectedCategoryIds as $categoryId) {
        $match = $categories->firstWhere('id', $categoryId)
            ?? $productModel?->categories?->firstWhere('id', $categoryId);
        if (!$match) {
            continue;
        }
        $initialTags->push([
            'id' => (int) $match->id,
            'name' => $categoryLabel($match),
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

@once
<style>
.product-cat-tags__label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.product-cat-tags__box{
    display:flex;flex-wrap:wrap;align-items:center;gap:6px;min-height:42px;padding:6px 8px;
    border:1px solid var(--border);border-radius:8px;background:var(--card);cursor:text;
    transition:border-color .15s ease,box-shadow .15s ease;
}
.product-cat-tags__box:focus-within{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.product-cat-tags__chip{
    display:inline-flex;align-items:center;gap:5px;max-width:100%;
    padding:4px 8px 4px 10px;border-radius:999px;font-size:12px;font-weight:600;line-height:1.2;
    border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));
    background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);
}
.product-cat-tags__chip--new{
    border-color:color-mix(in srgb,#22c55e 40%,var(--border));
    background:color-mix(in srgb,#22c55e 10%,transparent);
}
.product-cat-tags__chip-remove{
    display:grid;place-items:center;width:18px;height:18px;padding:0;margin:0;
    border:none;border-radius:999px;background:color-mix(in srgb,var(--card) 50%,transparent);
    color:var(--muted);font-size:14px;line-height:1;cursor:pointer;
}
.product-cat-tags__chip-remove:hover{background:color-mix(in srgb,#f87171 18%,transparent);color:#f87171;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .product-cat-tags__chip-remove:hover{color:#dc2626;}
.product-cat-tags__input{
    flex:1 1 120px;min-width:100px;border:none;outline:none;background:transparent;
    padding:4px 2px;font-size:13px;color:var(--text);
}
.product-cat-tags__input::placeholder{color:var(--muted);opacity:.85;}
.product-cat-tags__suggest{
    position:absolute;z-index:30;left:0;right:0;top:calc(100% + 4px);margin:0;padding:4px 0;list-style:none;
    max-height:200px;overflow:auto;border:1px solid var(--border);border-radius:10px;
    background:var(--card);box-shadow:0 12px 28px rgba(0,0,0,.22);
}
.product-cat-tags__suggest[hidden]{display:none;}
.product-cat-tags__suggest li{margin:0;}
.product-cat-tags__suggest button{
    display:block;width:100%;text-align:left;padding:8px 12px;border:none;background:transparent;
    font-size:13px;color:var(--text);cursor:pointer;
}
.product-cat-tags__suggest button:hover,.product-cat-tags__suggest button:focus-visible{
    background:color-mix(in srgb,var(--primary) 10%,transparent);outline:none;
}
.product-cat-tags__suggest button.is-create{font-weight:600;color:var(--primary);}
.product-cat-tags__wrap{position:relative;}
.product-cat-tags__hint{margin:6px 0 0;font-size:11px;line-height:1.4;color:var(--muted);}
.product-cat-tags__hidden{display:none;}
</style>
@endonce

<div class="product-field product-cat-tags-field" style="grid-column:1/-1;">
    <label class="product-cat-tags__label" id="{{ $tagsRootId }}-label">Categories</label>
    <div class="product-cat-tags__wrap">
        <div
            id="{{ $tagsRootId }}"
            class="product-cat-tags__box"
            role="group"
            aria-labelledby="{{ $tagsRootId }}-label"
            data-cat-tags-root
            data-prefix="{{ $pfxRaw }}"
            data-catalog='@json($catalogForJs)'
            data-initial-tags='@json($initialTagsJson)'>
            <div class="product-cat-tags__chips" data-cat-tags-chips></div>
            <input
                type="text"
                class="product-cat-tags__input"
                data-cat-tags-input
                placeholder="{{ $categories->isEmpty() ? 'Type a category name and press Enter' : 'Search or add category…' }}"
                autocomplete="off"
                aria-autocomplete="list"
                aria-controls="{{ $tagsRootId }}-suggest"
                aria-expanded="false">
        </div>
        <ul id="{{ $tagsRootId }}-suggest" class="product-cat-tags__suggest" data-cat-tags-suggest hidden role="listbox"></ul>
    </div>
    <p class="product-cat-tags__hint">Press Enter to add. Pick from the list or create a new category by typing a name.</p>
    <div class="product-cat-tags__hidden" data-cat-tags-hidden-inputs aria-hidden="true"></div>
    @error('new_category_name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('new_category_names')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('new_category_names.*')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('product_category_ids')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('product_category_ids.*')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    <a href="{{ route('product.categories.index') }}" class="product-field__manage" style="font-size:11px;margin-top:8px;display:inline-block;color:var(--primary);font-weight:600;">Manage all categories</a>
</div>

@once
<script>
(function () {
    if (window.__productCatTagsInit) return;
    window.__productCatTagsInit = true;

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initCatTags(root) {
        if (!root || root.dataset.catTagsReady === '1') return;
        root.dataset.catTagsReady = '1';

        const catalog = JSON.parse(root.dataset.catalog || '[]');
        const chipsEl = root.querySelector('[data-cat-tags-chips]');
        const inputEl = root.querySelector('[data-cat-tags-input]');
        const hiddenEl = root.closest('.product-cat-tags-field')?.querySelector('[data-cat-tags-hidden-inputs]');
        const suggestEl = root.closest('.product-cat-tags__wrap')?.querySelector('[data-cat-tags-suggest]')
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
                    inp.name = 'new_category_names[]';
                    inp.value = tag.name;
                    hiddenEl.appendChild(inp);
                } else if (tag.id) {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'product_category_ids[]';
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
                btn.textContent = 'Create category “' + inputEl.value.trim() + '”';
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

        root._resetCatTags = function () {
            selected.clear();
            renderChips();
            inputEl.value = '';
            closeSuggest();
        };
    }

    window.initProductCategoryTags = function (container) {
        (container || document).querySelectorAll('[data-cat-tags-root]').forEach(initCatTags);
    };

    window.resetProductCategoryTags = function (container) {
        (container || document).querySelectorAll('[data-cat-tags-root]').forEach(function (root) {
            if (typeof root._resetCatTags === 'function') {
                root._resetCatTags();
            }
        });
    };

})();
</script>
@endonce

<script>
(function () {
    const root = document.getElementById(@json($tagsRootId));
    if (!root || !window.initProductCategoryTags) return;
    window.initProductCategoryTags(root.closest('.product-cat-tags-field') || root);
})();
</script>
