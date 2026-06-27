@php
    $pfxRaw      = $fieldIdPrefix ?? 'mn';
    $rootId      = $pfxRaw . '-cat-tags';
    $item        = $item ?? null;
    $categories  = $categories ?? collect();

    $selectedIds = collect(old('menu_category_ids',
        $item?->categories?->pluck('id')->all() ?? []
    ))->map(fn($id) => (int)$id)->filter()->unique()->values();

    $pendingNames = collect(old('new_category_names', []))
        ->map(fn($n) => trim((string)$n))->filter()->unique()->values();

    $catalogForJs = $categories->map(fn($c) => ['id' => (int)$c->id, 'name' => $c->name])->values();

    $initialTags = collect();
    foreach ($selectedIds as $cid) {
        $match = $categories->firstWhere('id', $cid)
            ?? $item?->categories?->firstWhere('id', $cid);
        if ($match) {
            $initialTags->push(['id' => (int)$match->id, 'name' => $match->name, 'isNew' => false]);
        }
    }
    foreach ($pendingNames as $pname) {
        if (!$initialTags->contains(fn($t) => strcasecmp($t['name'], $pname) === 0)) {
            $initialTags->push(['id' => null, 'name' => $pname, 'isNew' => true]);
        }
    }
@endphp

@once
<style>
.mn-cat-tags__label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.mn-cat-tags__box{display:flex;flex-wrap:wrap;align-items:center;gap:6px;min-height:42px;padding:6px 8px;border:1px solid var(--border);border-radius:8px;background:var(--card);cursor:text;transition:border-color .15s,box-shadow .15s;}
.mn-cat-tags__box:focus-within{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.mn-cat-tags__chip{display:inline-flex;align-items:center;gap:5px;padding:4px 8px 4px 10px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);}
.mn-cat-tags__chip--new{border-color:color-mix(in srgb,#22c55e 40%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);}
.mn-cat-tags__chip-remove{display:grid;place-items:center;width:18px;height:18px;padding:0;margin:0;border:none;border-radius:999px;background:color-mix(in srgb,var(--card) 50%,transparent);color:var(--muted);font-size:14px;cursor:pointer;}
.mn-cat-tags__chip-remove:hover{background:color-mix(in srgb,#f87171 18%,transparent);color:#f87171;}
.mn-cat-tags__input{flex:1 1 120px;min-width:100px;border:none;outline:none;background:transparent;padding:4px 2px;font-size:13px;color:var(--text);}
.mn-cat-tags__input::placeholder{color:var(--muted);opacity:.85;}
.mn-cat-tags__suggest{position:absolute;z-index:30;left:0;right:0;top:calc(100% + 4px);margin:0;padding:4px 0;list-style:none;max-height:200px;overflow:auto;border:1px solid var(--border);border-radius:10px;background:var(--card);box-shadow:0 12px 28px rgba(0,0,0,.22);}
.mn-cat-tags__suggest[hidden]{display:none;}
.mn-cat-tags__suggest li{margin:0;}
.mn-cat-tags__suggest button{display:block;width:100%;text-align:left;padding:8px 12px;border:none;background:transparent;font-size:13px;color:var(--text);cursor:pointer;}
.mn-cat-tags__suggest button:hover,.mn-cat-tags__suggest button:focus-visible{background:color-mix(in srgb,var(--primary) 10%,transparent);outline:none;}
.mn-cat-tags__suggest button.is-create{font-weight:600;color:var(--primary);}
.mn-cat-tags__wrap{position:relative;}
.mn-cat-tags__hint{margin:5px 0 0;font-size:11px;color:var(--muted);}
</style>
@endonce

<div class="mn-cat-tags-field" id="{{ $rootId }}-field">
    <label class="mn-cat-tags__label" id="{{ $rootId }}-label">Categories</label>
    <div class="mn-cat-tags__wrap">
        <div id="{{ $rootId }}"
             class="mn-cat-tags__box"
             role="group"
             aria-labelledby="{{ $rootId }}-label"
             data-mn-cat-tags-root
             data-catalog='@json($catalogForJs)'
             data-initial-tags='@json($initialTags->values())'>
            <div data-mn-cat-tags-chips></div>
            <input type="text"
                   class="mn-cat-tags__input"
                   data-mn-cat-tags-input
                   placeholder="{{ $categories->isEmpty() ? 'Type a category name and press Enter' : 'Search or create category…' }}"
                   autocomplete="off"
                   aria-autocomplete="list"
                   aria-controls="{{ $rootId }}-suggest"
                   aria-expanded="false">
        </div>
        <ul id="{{ $rootId }}-suggest" class="mn-cat-tags__suggest" data-mn-cat-tags-suggest hidden role="listbox"></ul>
    </div>
    <p class="mn-cat-tags__hint">Press Enter to add. Type a new name to create a category on the fly.</p>
    <div data-mn-cat-tags-hidden style="display:none;" aria-hidden="true"></div>
    @error('menu_category_ids')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('new_category_names')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

@once
<script>
(function () {
    if (window.__mnCatTagsInit) return;
    window.__mnCatTagsInit = true;

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function initMnCatTags(root) {
        if (!root || root.dataset.mnCatTagsReady === '1') return;
        root.dataset.mnCatTagsReady = '1';

        var catalog   = JSON.parse(root.dataset.catalog || '[]');
        var chipsEl   = root.querySelector('[data-mn-cat-tags-chips]');
        var inputEl   = root.querySelector('[data-mn-cat-tags-input]');
        var field     = root.closest('.mn-cat-tags-field') || root.parentElement;
        var hiddenEl  = field ? field.querySelector('[data-mn-cat-tags-hidden]') : null;
        var wrap      = root.closest('.mn-cat-tags__wrap');
        var suggestEl = wrap ? wrap.querySelector('[data-mn-cat-tags-suggest]') : null;

        if (!chipsEl || !inputEl || !hiddenEl) return;

        var selected = new Map();

        function syncHidden() {
            hiddenEl.innerHTML = '';
            selected.forEach(function(tag) {
                var inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.value = tag.isNew ? tag.name : String(tag.id);
                inp.name  = tag.isNew ? 'new_category_names[]' : 'menu_category_ids[]';
                hiddenEl.appendChild(inp);
            });
        }

        function renderChips() {
            chipsEl.innerHTML = '';
            selected.forEach(function(tag, key) {
                var chip = document.createElement('span');
                chip.className = 'mn-cat-tags__chip' + (tag.isNew ? ' mn-cat-tags__chip--new' : '');
                chip.innerHTML = '<span>' + esc(tag.isNew ? tag.name + ' (new)' : tag.name) + '</span>'
                    + '<button type="button" class="mn-cat-tags__chip-remove" aria-label="Remove">&times;</button>';
                chip.querySelector('button').addEventListener('click', function(e) {
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
            var key = tag.isNew ? 'new:' + tag.name.toLowerCase() : 'id:' + tag.id;
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
            return Array.from(selected.values()).some(function(t) {
                return t.name.toLowerCase() === String(name).toLowerCase();
            });
        }

        function filterSuggest() {
            if (!suggestEl) return;
            var q = inputEl.value.trim().toLowerCase();
            var matches = catalog.filter(function(c) {
                if (isSelected(c.id, c.name)) return false;
                return !q || c.name.toLowerCase().indexOf(q) !== -1;
            }).slice(0, 8);

            suggestEl.innerHTML = '';
            matches.forEach(function(c) {
                var li = document.createElement('li');
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = c.name;
                btn.addEventListener('mousedown', function(e) { e.preventDefault(); });
                btn.addEventListener('click', function() { addTag({id: c.id, name: c.name, isNew: false}); });
                li.appendChild(btn);
                suggestEl.appendChild(li);
            });

            if (q && !catalog.some(function(c) { return c.name.toLowerCase() === q; }) && !isSelected(null, q)) {
                var li = document.createElement('li');
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'is-create';
                btn.textContent = 'Create "' + inputEl.value.trim() + '"';
                btn.addEventListener('mousedown', function(e) { e.preventDefault(); });
                btn.addEventListener('click', function() { addTag({id: null, name: inputEl.value.trim(), isNew: true}); });
                li.appendChild(btn);
                suggestEl.appendChild(li);
            }

            var show = suggestEl.children.length > 0;
            suggestEl.hidden = !show;
            inputEl.setAttribute('aria-expanded', show ? 'true' : 'false');
        }

        function closeSuggest() {
            if (suggestEl) suggestEl.hidden = true;
            inputEl.setAttribute('aria-expanded', 'false');
        }

        inputEl.addEventListener('focus', function() { filterSuggest(); });
        inputEl.addEventListener('input', filterSuggest);
        inputEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var q = inputEl.value.trim();
                if (!q) return;
                var exact = catalog.find(function(c) { return c.name.toLowerCase() === q.toLowerCase() && !isSelected(c.id, c.name); });
                if (exact) {
                    addTag({id: exact.id, name: exact.name, isNew: false});
                } else if (!isSelected(null, q)) {
                    addTag({id: null, name: q, isNew: true});
                }
                return;
            }
            if (e.key === 'Escape') { closeSuggest(); inputEl.blur(); }
            if (e.key === 'Backspace' && inputEl.value === '' && selected.size > 0) {
                var keys = Array.from(selected.keys());
                selected.delete(keys[keys.length - 1]);
                renderChips();
                filterSuggest();
            }
        });

        root.addEventListener('click', function() { inputEl.focus(); });
        document.addEventListener('click', function(e) {
            if (wrap && !wrap.contains(e.target) && e.target !== root) closeSuggest();
        });

        var initial = JSON.parse(root.dataset.initialTags || '[]');
        initial.forEach(function(tag) {
            var key = tag.isNew ? 'new:' + tag.name.toLowerCase() : 'id:' + tag.id;
            selected.set(key, tag);
        });
        renderChips();

        root._resetMnCatTags = function() {
            selected.clear();
            renderChips();
            inputEl.value = '';
            closeSuggest();
        };
    }

    window.initMnCategoryTags = function(container) {
        (container || document).querySelectorAll('[data-mn-cat-tags-root]').forEach(initMnCatTags);
    };

    document.addEventListener('DOMContentLoaded', function() { window.initMnCategoryTags(); });
})();
</script>
@endonce

<script>
(function() {
    var root = document.getElementById(@json($rootId));
    if (root && window.initMnCategoryTags) {
        window.initMnCategoryTags(root.closest('.mn-cat-tags-field') || root.parentElement);
    }
})();
</script>
