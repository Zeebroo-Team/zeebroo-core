@php
    $tfId        = isset($fieldIdPrefix) ? $fieldIdPrefix . '-svc-tags' : 'svc-tags';
    $initialTags = collect(old('tags', isset($item) && $item ? ($item->tags ?? []) : []))
        ->map(fn ($t) => trim((string) $t))->filter()->unique()->values();
@endphp

@once
<style>
.svc-tags-field__label{display:flex;align-items:center;gap:5px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:5px;}
.svc-tags-field__wrap{position:relative;}
.svc-tags-field__box{
    display:flex;flex-wrap:wrap;align-items:center;gap:6px;min-height:42px;padding:6px 8px;
    border:1px solid var(--border);border-radius:8px;background:var(--card);cursor:text;
    transition:border-color .15s,box-shadow .15s;
}
.svc-tags-field__box:focus-within{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.svc-tags-field__chip{
    display:inline-flex;align-items:center;gap:5px;max-width:100%;
    padding:4px 8px 4px 10px;border-radius:999px;font-size:12px;font-weight:600;line-height:1.2;
    border:1px solid color-mix(in srgb,var(--muted) 35%,var(--border));
    background:color-mix(in srgb,var(--muted) 10%,transparent);color:var(--text);
}
.svc-tags-field__chip-remove{
    display:grid;place-items:center;width:18px;height:18px;padding:0;margin:0;
    border:none;border-radius:999px;background:color-mix(in srgb,var(--card) 50%,transparent);
    color:var(--muted);font-size:14px;line-height:1;cursor:pointer;
}
.svc-tags-field__chip-remove:hover{background:color-mix(in srgb,#f87171 18%,transparent);color:#f87171;}
.svc-tags-field__input{
    flex:1 1 120px;min-width:100px;border:none;outline:none;background:transparent;
    padding:4px 2px;font-size:13px;color:var(--text);
}
.svc-tags-field__input::placeholder{color:var(--muted);opacity:.85;}
.svc-tags-field__hint{margin:5px 0 0;font-size:11px;line-height:1.4;color:var(--muted);}
.svc-tags-field__hidden{display:none;}
</style>
@endonce

<div class="svc-tags-field" id="{{ $tfId }}-field">
    <label class="svc-tags-field__label" id="{{ $tfId }}-label">Tags <span style="font-size:9px;font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted);opacity:.8;background:color-mix(in srgb,var(--border) 60%,transparent);padding:1px 5px;border-radius:4px;">optional</span></label>
    <div class="svc-tags-field__wrap">
        <div class="svc-tags-field__box" id="{{ $tfId }}"
             role="group" aria-labelledby="{{ $tfId }}-label"
             data-svc-tags-root
             data-initial-tags='@json($initialTags->values())'>
            <div class="svc-tags-field__chips" data-svc-tags-chips></div>
            <input type="text" class="svc-tags-field__input" data-svc-tags-input
                   placeholder="Type a tag and press Enter…" autocomplete="off">
        </div>
    </div>
    <p class="svc-tags-field__hint">Use tags to help staff and customers filter or search for this service.</p>
    <div class="svc-tags-field__hidden" data-svc-tags-hidden aria-hidden="true"></div>
    @error('tags')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('tags.*')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

@once
<script>
(function () {
    if (window.__svcTagsFieldInit) return;
    window.__svcTagsFieldInit = true;

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function initSvcTagsField(root) {
        if (!root || root.dataset.svcTagsReady === '1') return;
        root.dataset.svcTagsReady = '1';

        const chipsEl  = root.querySelector('[data-svc-tags-chips]');
        const inputEl  = root.querySelector('[data-svc-tags-input]');
        const hiddenEl = root.closest('.svc-tags-field')?.querySelector('[data-svc-tags-hidden]');
        if (!chipsEl || !inputEl || !hiddenEl) return;

        const selected = [];

        function syncHidden() {
            hiddenEl.innerHTML = '';
            selected.forEach(tag => {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'tags[]';
                inp.value = tag;
                hiddenEl.appendChild(inp);
            });
        }

        function renderChips() {
            chipsEl.innerHTML = '';
            selected.forEach((tag, idx) => {
                const chip = document.createElement('span');
                chip.className = 'svc-tags-field__chip';
                chip.innerHTML = '<span>' + esc(tag) + '</span>'
                    + '<button type="button" class="svc-tags-field__chip-remove" aria-label="Remove ' + esc(tag) + '">&times;</button>';
                chip.querySelector('button').addEventListener('click', e => {
                    e.stopPropagation();
                    selected.splice(idx, 1);
                    renderChips();
                    syncHidden();
                });
                chipsEl.appendChild(chip);
            });
            syncHidden();
        }

        function addTag(raw) {
            const tag = raw.trim();
            if (!tag) return;
            if (selected.some(t => t.toLowerCase() === tag.toLowerCase())) { inputEl.value = ''; return; }
            selected.push(tag);
            renderChips();
            inputEl.value = '';
        }

        inputEl.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                addTag(inputEl.value);
                return;
            }
            if (e.key === 'Backspace' && inputEl.value === '' && selected.length > 0) {
                selected.pop();
                renderChips();
            }
        });
        inputEl.addEventListener('blur', () => { if (inputEl.value.trim()) addTag(inputEl.value); });

        root.addEventListener('click', () => inputEl.focus());

        JSON.parse(root.dataset.initialTags || '[]').forEach(tag => selected.push(tag));
        renderChips();

        root._resetSvcTags = function () {
            selected.length = 0;
            renderChips();
            inputEl.value = '';
        };
    }

    window.initSvcTagsField = function (container) {
        (container || document).querySelectorAll('[data-svc-tags-root]').forEach(initSvcTagsField);
    };

    window.resetSvcTagsField = function (container) {
        (container || document).querySelectorAll('[data-svc-tags-root]').forEach(r => r._resetSvcTags?.());
    };
})();
</script>
@endonce

<script>
(function () {
    const root = document.getElementById(@json($tfId));
    if (root && window.initSvcTagsField) window.initSvcTagsField(root.closest('.svc-tags-field') || root);
})();
</script>
