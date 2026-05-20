{{-- When business has multi-location enabled, pick a Branch record (distinct from bank branch field). --}}
@php
    $branchIdPreset = $presetBranchId ?? old('branch_id');
@endphp

<div id="acct-warehouse-wrap" style="display:none;margin-top:10px;"@if(isset($fixedBusinessId)) data-fixed-business-id="{{ $fixedBusinessId }}"@endif>
    <label for="acct-warehouse-branch-id" style="display:block;margin-bottom:4px;font-size:12px;font-weight:600;color:var(--muted);">Warehouse / branch</label>
    <select id="acct-warehouse-branch-id" name="branch_id" data-acct-wh-select class="{{ trim('acct-warehouse-branch-el ' . ($warehouseSelectClass ?? '')) }}">
        <option value="">— Select location —</option>
    </select>
</div>

<script>
(function () {
    const bizSel = document.querySelector('select[name="business_id"], select.account-onboard-business');
    const wrap = document.getElementById('acct-warehouse-wrap');
    const sel = document.getElementById('acct-warehouse-branch-id');
    if (!wrap || !sel) return;

    const multiWare = @json($accountBusinessMultiWarehouse ?? []);
    const branches = @json($accountBranchesByBusiness ?? []);
    const preset = @json($branchIdPreset);

    function getBizId() {
        if (bizSel && bizSel.value) {
            const n = parseInt(String(bizSel.value), 10);
            if (n) {
                return n;
            }
        }
        const fixed = wrap.dataset.fixedBusinessId;
        if (fixed !== undefined && fixed !== '') {
            return parseInt(String(fixed), 10) || 0;
        }
        return 0;
    }

    function fillOptions(list) {
        sel.innerHTML = '';
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = list.length ? '— Select location —' : '— No locations yet —';
        sel.appendChild(ph);
        list.forEach((row) => {
            const opt = document.createElement('option');
            opt.value = String(row.id);
            opt.textContent = row.name;
            sel.appendChild(opt);
        });
    }

    function sync() {
        const bizIdNum = getBizId();

        const mw = !!(bizIdNum && multiWare[bizIdNum]);
        const list = (mw && bizIdNum && branches[bizIdNum]) ? branches[bizIdNum] : [];

        const showSelect = mw && list.length > 0;
        wrap.style.display = showSelect ? 'block' : 'none';
        if (!showSelect) {
            sel.disabled = true;
            sel.selectedIndex = 0;
            return;
        }
        sel.disabled = false;
        fillOptions(Array.isArray(list) ? list : []);
        if (preset !== null && preset !== undefined && String(preset) !== '') {
            const want = String(preset);
            if ([...sel.options].some((o) => o.value === want)) {
                sel.value = want;
            }
        }
    }

    if (bizSel && bizSel.tagName === 'SELECT') {
        bizSel.addEventListener('change', sync);
    }
    sync();

    sel.form?.addEventListener('submit', () => {
        if (wrap.style.display === 'none') {
            sel.disabled = true;
        }
    });
})();
</script>
