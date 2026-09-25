@php
    /** @var \Modules\Business\Models\Business $business */
    $taxRules = $taxRules ?? [];
@endphp

<div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border);">
    <h2 style="margin:0 0 8px;font-size:15px;font-weight:800;">{{ __('Tax rules') }}</h2>
    <p class="muted" style="margin:0 0 12px;font-size:13px;line-height:1.45;max-width:72ch;">
        {{ __('Define the named tax rates applied at checkout. Each rule can be a percentage of the sale or a flat amount.') }}
    </p>

    <form method="post" action="{{ route('pos.settings.save') }}" style="display:grid;gap:10px;">
        @csrf
        <input type="hidden" name="redirect" value="{{ route('settings.business') }}?tab=tax">

        <div id="taxRulesRows" style="display:grid;gap:8px;">
            @foreach($taxRules as $i => $rule)
                <div data-tax-row style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;align-items:center;">
                    <input type="hidden" name="tax_rules[{{ $i }}][id]" value="{{ $rule['id'] ?? '' }}">
                    <input type="text" name="tax_rules[{{ $i }}][name]" value="{{ $rule['name'] ?? '' }}" placeholder="{{ __('Rule name (e.g. VAT)') }}" required
                           style="padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font-size:13px;">
                    <select name="tax_rules[{{ $i }}][type]" style="padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font-size:13px;">
                        <option value="percentage" {{ ($rule['type'] ?? 'percentage') === 'percentage' ? 'selected' : '' }}>{{ __('Percentage') }}</option>
                        <option value="flat" {{ ($rule['type'] ?? '') === 'flat' ? 'selected' : '' }}>{{ __('Flat amount') }}</option>
                    </select>
                    <input type="number" step="0.01" min="0" name="tax_rules[{{ $i }}][value]" value="{{ $rule['value'] ?? 0 }}"
                           style="padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font-size:13px;">
                    <button type="button" data-tax-remove class="linkbtn" style="padding:7px 10px;font-size:12px;">{{ __('Remove') }}</button>
                </div>
            @endforeach
        </div>

        <div>
            <button type="button" id="taxRuleAdd" class="linkbtn" style="padding:6px 12px;font-size:12px;">
                <i class="fa fa-plus"></i> {{ __('Add rule') }}
            </button>
        </div>

        <div style="display:flex;justify-content:flex-end;">
            <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:13px;">{{ __('Save tax rules') }}</button>
        </div>
    </form>
</div>

<script>
(function () {
    var rows = document.getElementById('taxRulesRows');
    var addBtn = document.getElementById('taxRuleAdd');
    if (!rows || !addBtn) return;

    var nextIndex = rows.querySelectorAll('[data-tax-row]').length;

    function addRow() {
        var row = document.createElement('div');
        row.setAttribute('data-tax-row', '');
        row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;align-items:center;';
        row.innerHTML =
            '<input type="hidden" name="tax_rules[' + nextIndex + '][id]" value="">' +
            '<input type="text" name="tax_rules[' + nextIndex + '][name]" placeholder="Rule name (e.g. VAT)" required ' +
                'style="padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font-size:13px;">' +
            '<select name="tax_rules[' + nextIndex + '][type]" style="padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font-size:13px;">' +
                '<option value="percentage" selected>Percentage</option>' +
                '<option value="flat">Flat amount</option>' +
            '</select>' +
            '<input type="number" step="0.01" min="0" name="tax_rules[' + nextIndex + '][value]" value="0" ' +
                'style="padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font-size:13px;">' +
            '<button type="button" data-tax-remove class="linkbtn" style="padding:7px 10px;font-size:12px;">Remove</button>';
        rows.appendChild(row);
        nextIndex++;
    }

    addBtn.addEventListener('click', addRow);

    rows.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-tax-remove]');
        if (!btn) return;
        var row = btn.closest('[data-tax-row]');
        if (row) row.remove();
    });
})();
</script>
