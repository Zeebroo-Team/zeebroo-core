@php
    $p         = $idPfx ?? 'sc';
    $editing   = isset($campaign) && $campaign instanceof \Modules\Product\Models\SaleCampaign;
    $mode      = old('mode', $editing ? $campaign->mode : 'storewide');
    $isLong    = old('is_long_term', $editing ? ($campaign->is_long_term ? '1' : '0') : '0') === '1';

    $blankItem = ['product_id' => '', 'discount_type' => 'percentage', 'discount_value' => ''];
    if ($editing) {
        $oldItems = old('items');
        if (!is_array($oldItems) || $oldItems === []) {
            $oldItems = $campaign->items->map(fn ($i) => [
                'product_id'     => $i->product_id,
                'discount_type'  => $i->discount_type,
                'discount_value' => $i->discount_value,
            ])->all();
        }
    } else {
        $oldItems = old('items', []);
    }
    if (!is_array($oldItems) || $oldItems === []) {
        $oldItems = [$blankItem];
    }
@endphp

<div class="sc-form-root" data-sc-form>

    {{-- ═══ Campaign details ═══ --}}
    <div class="pd-section">
        <div class="pd-section__head">
            <span class="pd-section__icon"><i class="fa fa-bullhorn"></i></span>
            <div>
                <div class="pd-section__title">Campaign details</div>
                <div class="pd-section__sub">Name and describe this sale campaign</div>
            </div>
        </div>
        <div class="pd-section__body">
            <div class="pcat-field">
                <label for="{{ $p }}-name">Campaign name <span class="pd-req">*</span></label>
                <input id="{{ $p }}-name" name="name" maxlength="191" required
                       placeholder="e.g. Black Friday weekend"
                       value="{{ old('name', $editing ? $campaign->name : '') }}">
                @error('name')<p class="pd-err">{{ $message }}</p>@enderror
            </div>
            <div class="pcat-field">
                <label for="{{ $p }}-desc">Description <span class="pd-optional">optional</span></label>
                <textarea id="{{ $p }}-desc" name="description" maxlength="2000"
                          placeholder="Internal notes about this campaign">{{ old('description', $editing ? $campaign->description : '') }}</textarea>
                @error('description')<p class="pd-err">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- ═══ Mode ═══ --}}
    <div class="pd-section">
        <div class="pd-section__head">
            <span class="pd-section__icon"><i class="fa fa-sliders"></i></span>
            <div>
                <div class="pd-section__title">Scope</div>
                <div class="pd-section__sub">Apply the discount storewide or to specific products</div>
            </div>
        </div>
        <div class="pd-section__body">
            <div class="pd-type-cards">
                <label class="pd-type-card" id="{{ $p }}-mode-storewide-card">
                    <input type="radio" name="mode" value="storewide" class="sc-mode-radio" data-sc-mode="storewide" @checked($mode === 'storewide')>
                    <span class="pd-type-card__icon"><i class="fa fa-store"></i></span>
                    <span class="pd-type-card__body">
                        <span class="pd-type-card__label">Storewide</span>
                        <span class="pd-type-card__hint">One discount, every product</span>
                    </span>
                    <span class="pd-type-card__check"><i class="fa fa-circle-check"></i></span>
                </label>
                <label class="pd-type-card" id="{{ $p }}-mode-individual-card">
                    <input type="radio" name="mode" value="individual" class="sc-mode-radio" data-sc-mode="individual" @checked($mode === 'individual')>
                    <span class="pd-type-card__icon"><i class="fa fa-list"></i></span>
                    <span class="pd-type-card__body">
                        <span class="pd-type-card__label">Selected products</span>
                        <span class="pd-type-card__hint">Pick products, each with its own discount</span>
                    </span>
                    <span class="pd-type-card__check"><i class="fa fa-circle-check"></i></span>
                </label>
            </div>
            @error('mode')<p class="pd-err">{{ $message }}</p>@enderror

            {{-- Storewide discount fields --}}
            <div class="sc-mode-panel" data-sc-panel="storewide" style="{{ $mode === 'storewide' ? '' : 'display:none;' }} margin-top:14px;">
                <div class="pd-two-col">
                    <div>
                        <label class="pd-field-label">Discount type <span class="pd-req">*</span></label>
                        <div class="pd-type-cards">
                            <label class="pd-type-card">
                                <input type="radio" name="discount_type" value="percentage" class="sc-sw-type-radio"
                                       @checked(old('discount_type', $editing ? $campaign->discount_type : 'percentage') === 'percentage')>
                                <span class="pd-type-card__icon"><i class="fa fa-percent"></i></span>
                                <span class="pd-type-card__body">
                                    <span class="pd-type-card__label">Percentage</span>
                                </span>
                                <span class="pd-type-card__check"><i class="fa fa-circle-check"></i></span>
                            </label>
                            <label class="pd-type-card">
                                <input type="radio" name="discount_type" value="flat" class="sc-sw-type-radio"
                                       @checked(old('discount_type', $editing ? $campaign->discount_type : '') === 'flat')>
                                <span class="pd-type-card__icon"><i class="fa fa-tag"></i></span>
                                <span class="pd-type-card__body">
                                    <span class="pd-type-card__label">Flat amount</span>
                                </span>
                                <span class="pd-type-card__check"><i class="fa fa-circle-check"></i></span>
                            </label>
                        </div>
                        @error('discount_type')<p class="pd-err">{{ $message }}</p>@enderror
                    </div>
                    <div class="pcat-field">
                        <label for="{{ $p }}-sw-value">Value <span class="pd-req">*</span></label>
                        <input id="{{ $p }}-sw-value" type="number" name="discount_value" min="0.01" step="0.01"
                               placeholder="e.g. 15"
                               value="{{ old('discount_value', $editing ? $campaign->discount_value : '') }}">
                        @error('discount_value')<p class="pd-err">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Individual items --}}
            <div class="sc-mode-panel" data-sc-panel="individual" style="{{ $mode === 'individual' ? '' : 'display:none;' }} margin-top:14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;">
                    <label class="pd-field-label" style="margin:0;">Products in this campaign</label>
                    <button type="button" class="linkbtn" style="padding:6px 12px;font-size:12px;" data-sc-add-item>
                        <i class="fa fa-plus"></i> Add product
                    </button>
                </div>
                @error('items')<p class="pd-err">{{ $message }}</p>@enderror

                <div class="pcat-table-wrap">
                    <table class="pcat-table" style="min-width:520px;">
                        <thead>
                            <tr>
                                <th style="width:40%;">Product</th>
                                <th style="width:26%;">Discount type</th>
                                <th style="width:24%;text-align:right;">Value</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody data-sc-items>
                            @foreach($oldItems as $index => $item)
                                <tr data-sc-item>
                                    <td>
                                        <select name="items[{{ $index }}][product_id]">
                                            <option value="">— select a product —</option>
                                            @foreach($products as $prod)
                                                <option value="{{ $prod->id }}" @selected(old('items.'.$index.'.product_id', $item['product_id'] ?? '') == $prod->id)>
                                                    {{ $prod->name }}{{ $prod->sku ? ' ('.$prod->sku.')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="items[{{ $index }}][discount_type]">
                                            <option value="percentage" @selected(old('items.'.$index.'.discount_type', $item['discount_type'] ?? 'percentage') === 'percentage')>Percentage</option>
                                            <option value="flat" @selected(old('items.'.$index.'.discount_type', $item['discount_type'] ?? '') === 'flat')>Flat amount</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="items[{{ $index }}][discount_value]" min="0.01" step="0.01"
                                               style="text-align:right;"
                                               value="{{ old('items.'.$index.'.discount_value', $item['discount_value'] ?? '') }}">
                                    </td>
                                    <td>
                                        <button type="button" class="pcat-btn-del" data-sc-remove-item title="Remove"
                                                @if(count($oldItems) <= 1) hidden @endif>&times;</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Validity & status ═══ --}}
    <div class="pd-section">
        <div class="pd-section__head">
            <span class="pd-section__icon"><i class="fa fa-calendar-days"></i></span>
            <div>
                <div class="pd-section__title">Validity &amp; status</div>
                <div class="pd-section__sub">When this campaign runs</div>
            </div>
        </div>
        <div class="pd-section__body">
            <div class="pd-two-col">
                <div class="pcat-field">
                    <label for="{{ $p }}-starts">Start date <span class="pd-optional">optional</span></label>
                    <input id="{{ $p }}-starts" type="date" name="starts_at"
                           value="{{ old('starts_at', $editing && $campaign->starts_at ? $campaign->starts_at->format('Y-m-d') : '') }}">
                    @error('starts_at')<p class="pd-err">{{ $message }}</p>@enderror
                </div>
                <div class="pcat-field" data-sc-ends-field style="{{ $isLong ? 'opacity:.5;' : '' }}">
                    <label for="{{ $p }}-ends">End date</label>
                    <input id="{{ $p }}-ends" type="date" name="ends_at" data-sc-ends-input
                           @if($isLong) disabled @endif
                           value="{{ old('ends_at', $editing && $campaign->ends_at ? $campaign->ends_at->format('Y-m-d') : '') }}">
                    @error('ends_at')<p class="pd-err">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="pcat-field" style="grid-column:1/-1;">
                <div class="pcat-active-row">
                    <label for="{{ $p }}-long-term" class="pcat-active-row__lbl">Long-term (no end date)</label>
                    <label class="pcat-switch">
                        <input type="checkbox" name="is_long_term" id="{{ $p }}-long-term" value="1"
                               role="switch" aria-checked="{{ $isLong ? 'true' : 'false' }}"
                               data-sc-long-term @checked($isLong)>
                        <span class="pcat-switch-slider" aria-hidden="true"></span>
                    </label>
                </div>
            </div>

            @include('product::partials.active-toggle', [
                'toggleId' => $p . '-active',
                'model'    => $editing ? $campaign : null,
                'label'    => 'Campaign is active',
            ])
        </div>
    </div>

</div>

@once('sc-form-css')
<style>
.sc-mode-panel table select, .sc-mode-panel table input{width:100%;}
</style>
@endonce
@once
<script>
(function () {
    if (window.__scFormInit) return;
    window.__scFormInit = true;

    function reindex(tbody) {
        tbody.querySelectorAll('[data-sc-item]').forEach(function (row, i) {
            row.querySelectorAll('select,input').forEach(function (el) {
                var name = el.getAttribute('name');
                if (!name) return;
                el.setAttribute('name', name.replace(/items\[\d+\]/, 'items[' + i + ']'));
            });
        });
    }

    function updateRemoveBtns(tbody) {
        var rows = tbody.querySelectorAll('[data-sc-item]');
        rows.forEach(function (row) {
            var b = row.querySelector('[data-sc-remove-item]');
            if (b) b.hidden = rows.length <= 1;
        });
    }

    function bindForm(form) {
        if (!form || form.dataset.scBound === '1') return;
        form.dataset.scBound = '1';

        var modeRadios = form.querySelectorAll('.sc-mode-radio');
        var panels      = form.querySelectorAll('.sc-mode-panel');

        function syncMode() {
            var selected = form.querySelector('.sc-mode-radio:checked');
            var mode = selected ? selected.value : 'storewide';
            panels.forEach(function (panel) {
                var active = panel.getAttribute('data-sc-panel') === mode;
                panel.style.display = active ? '' : 'none';
                // Disable inputs in the inactive panel so they aren't submitted
                // (and don't trip "required_with" validation for the other mode).
                panel.querySelectorAll('input,select,textarea').forEach(function (el) {
                    el.disabled = !active;
                });
            });
            modeRadios.forEach(function (r) {
                r.closest('.pd-type-card')?.classList.toggle('pd-type-card--selected', r.checked);
            });
        }
        modeRadios.forEach(function (r) { r.addEventListener('change', syncMode); });
        syncMode();

        form.querySelectorAll('.sc-sw-type-radio').forEach(function (r) {
            r.addEventListener('change', function () {
                form.querySelectorAll('.sc-sw-type-radio').forEach(function (rr) {
                    rr.closest('.pd-type-card')?.classList.toggle('pd-type-card--selected', rr.checked);
                });
            });
            r.closest('.pd-type-card')?.classList.toggle('pd-type-card--selected', r.checked);
        });

        var longTerm  = form.querySelector('[data-sc-long-term]');
        var endsInput = form.querySelector('[data-sc-ends-input]');
        var endsField = form.querySelector('[data-sc-ends-field]');
        function syncLongTerm() {
            if (!longTerm || !endsInput) return;
            endsInput.disabled = longTerm.checked;
            if (endsField) endsField.style.opacity = longTerm.checked ? '.5' : '1';
        }
        longTerm && longTerm.addEventListener('change', syncLongTerm);

        var tbody = form.querySelector('[data-sc-items]');
        if (tbody) {
            form.querySelector('[data-sc-add-item]')?.addEventListener('click', function () {
                var tpl = tbody.querySelector('[data-sc-item]');
                if (!tpl) return;
                var clone = tpl.cloneNode(true);
                clone.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
                clone.querySelectorAll('input[type="number"]').forEach(function (i) { i.value = ''; });
                clone.querySelector('[data-sc-remove-item]')?.removeAttribute('hidden');
                tbody.appendChild(clone);
                reindex(tbody);
                updateRemoveBtns(tbody);
            });

            tbody.addEventListener('click', function (e) {
                if (!e.target.closest('[data-sc-remove-item]')) return;
                var rows = tbody.querySelectorAll('[data-sc-item]');
                if (rows.length <= 1) return;
                e.target.closest('[data-sc-item]')?.remove();
                reindex(tbody);
                updateRemoveBtns(tbody);
            });

            updateRemoveBtns(tbody);
        }
    }

    document.querySelectorAll('[data-sc-form]').forEach(bindForm);
})();
</script>
@endonce
