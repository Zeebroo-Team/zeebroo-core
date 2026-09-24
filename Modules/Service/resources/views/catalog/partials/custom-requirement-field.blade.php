@php
    $crfId       = isset($fieldIdPrefix) ? $fieldIdPrefix . '-svc-creq' : 'svc-creq';
    $initialReqs = collect(old('custom_requirement_fields',
        isset($item) && $item ? ($item->custom_requirement_fields ?? []) : []
    ))->map(function ($f) {
        $options = $f['options'] ?? [];
        return [
            'label'       => $f['label'] ?? '',
            'type'        => $f['type'] ?? 'text',
            'options_csv' => isset($f['options_csv']) ? $f['options_csv'] : implode(', ', (array) $options),
        ];
    })->values();
@endphp

@once
<style>
.svc-creq-list{display:flex;flex-direction:column;gap:10px;}
.svc-creq-row{border:1px solid var(--border);border-radius:8px;padding:10px;background:var(--card);}
.svc-creq-row__main{display:grid;grid-template-columns:1fr 150px 32px;gap:8px;align-items:center;}
@media(max-width:560px){.svc-creq-row__main{grid-template-columns:1fr;}}
.svc-creq-row__options{margin-top:8px;}
.svc-creq-row__options[hidden]{display:none;}
.svc-creq-remove{
    display:grid;place-items:center;width:32px;height:32px;padding:0;border:none;border-radius:7px;
    background:transparent;color:var(--muted);font-size:15px;cursor:pointer;transition:background .12s,color .12s;
}
.svc-creq-remove:hover{background:color-mix(in srgb,#f87171 15%,transparent);color:#f87171;}
.svc-creq-add-btn{
    display:inline-flex;align-items:center;gap:6px;align-self:flex-start;
    padding:8px 14px;border:1px solid var(--primary);border-radius:8px;
    background:color-mix(in srgb,var(--primary) 10%,transparent);
    color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;
}
.svc-creq-add-btn:hover{background:color-mix(in srgb,var(--primary) 18%,transparent);}
.svc-creq-empty{font-size:12px;color:var(--muted);font-style:italic;padding:6px 2px;}
</style>
@endonce

<div class="svc-creq-field" id="{{ $crfId }}-field" data-svc-creq-root data-next-index="{{ $initialReqs->count() }}">
    <div class="svc-creq-list" id="{{ $crfId }}-list" data-svc-creq-list>
        @foreach($initialReqs as $i => $f)
            @include('service::catalog.partials.custom-requirement-row', ['crfId' => $crfId, 'i' => $i, 'f' => $f])
        @endforeach
    </div>
    <p class="svc-creq-empty" data-svc-creq-empty @if($initialReqs->isNotEmpty()) hidden @endif>No custom fields yet — click "Add field" to ask customers for extra details when they request this service.</p>
    <button type="button" class="svc-creq-add-btn" data-svc-creq-add style="margin-top:10px;">
        <i class="fa fa-plus" aria-hidden="true"></i> Add field
    </button>
    @error('custom_requirement_fields')<div style="color:#f87171;font-size:12px;margin-top:6px;">{{ $message }}</div>@enderror
    @error('custom_requirement_fields.*.label')<div style="color:#f87171;font-size:12px;margin-top:6px;">{{ $message }}</div>@enderror
</div>

<template id="{{ $crfId }}-row-template">
    @include('service::catalog.partials.custom-requirement-row', ['crfId' => $crfId, 'i' => '__INDEX__', 'f' => ['label' => '', 'type' => 'text', 'options_csv' => '']])
</template>

@once
<script>
(function () {
    if (window.__svcCreqFieldInit) return;
    window.__svcCreqFieldInit = true;

    const OPTION_TYPES = ['select', 'radio'];

    function wireRow(row) {
        const typeSel = row.querySelector('[data-creq-type]');
        const optWrap = row.querySelector('[data-creq-options-wrap]');
        const removeBtn = row.querySelector('[data-creq-remove]');

        function syncOptionsVisibility() {
            if (!typeSel || !optWrap) return;
            optWrap.hidden = !OPTION_TYPES.includes(typeSel.value);
        }
        typeSel?.addEventListener('change', syncOptionsVisibility);
        syncOptionsVisibility();

        removeBtn?.addEventListener('click', () => {
            const root = row.closest('[data-svc-creq-root]');
            row.remove();
            const list = root?.querySelector('[data-svc-creq-list]');
            const empty = root?.querySelector('[data-svc-creq-empty]');
            if (empty) empty.hidden = !!(list && list.children.length);
        });
    }

    function initSvcCreqRoot(root) {
        if (!root || root.dataset.svcCreqReady === '1') return;
        root.dataset.svcCreqReady = '1';

        const list = root.querySelector('[data-svc-creq-list]');
        const empty = root.querySelector('[data-svc-creq-empty]');
        const addBtn = root.querySelector('[data-svc-creq-add]');
        const template = document.getElementById(root.id.replace('-field', '') + '-row-template');
        if (!list || !addBtn || !template) return;

        list.querySelectorAll('[data-creq-row]').forEach(wireRow);

        addBtn.addEventListener('click', () => {
            let idx = parseInt(root.dataset.nextIndex || '0', 10) || 0;
            root.dataset.nextIndex = String(idx + 1);
            const html = template.innerHTML.replace(/__INDEX__/g, String(idx));
            const wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            const row = wrap.firstElementChild;
            list.appendChild(row);
            wireRow(row);
            if (empty) empty.hidden = true;
            row.querySelector('[data-creq-label]')?.focus();
        });

        root._resetSvcCreq = function () {
            list.innerHTML = '';
            root.dataset.nextIndex = '0';
            if (empty) empty.hidden = false;
        };
    }

    function collectRoots(container) {
        const scope = container || document;
        const roots = Array.from(scope.querySelectorAll('[data-svc-creq-root]'));
        if (scope.matches && scope.matches('[data-svc-creq-root]')) roots.push(scope);
        return roots;
    }

    window.initSvcCreqField = function (container) {
        collectRoots(container).forEach(initSvcCreqRoot);
    };

    window.resetSvcCreqField = function (container) {
        collectRoots(container).forEach(r => r._resetSvcCreq?.());
    };
})();
</script>
@endonce

<script>
(function () {
    const root = document.getElementById(@json($crfId . '-field'));
    if (root && window.initSvcCreqField) window.initSvcCreqField(root);
})();
</script>
