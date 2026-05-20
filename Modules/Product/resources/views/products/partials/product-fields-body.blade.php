@php
    $requireProductName = $requireName ?? true;
    $pfxRaw = isset($fieldIdPrefix) ? (string) $fieldIdPrefix : '';
    $idName = $pfxRaw !== '' ? $pfxRaw . '-name' : 'product-name';
    $idSku = $pfxRaw !== '' ? $pfxRaw . '-sku' : 'product-sku';
    $productModel = $product ?? null;
    $idUnit = $pfxRaw !== '' ? $pfxRaw . '-unit' : 'product-unit';
    $idDesc = $pfxRaw !== '' ? $pfxRaw . '-desc' : 'product-desc';
    $idPrice = $pfxRaw !== '' ? $pfxRaw . '-price' : 'product-price';
    $idStock = $pfxRaw !== '' ? $pfxRaw . '-stock' : 'product-stock';
    $idActive = $pfxRaw !== '' ? $pfxRaw . '-active' : 'product-active';
    $activeOld = old('is_active', $productModel ? ($productModel->is_active ? '1' : '0') : '1');
    $categories = $categories ?? collect();
    $brands = $brands ?? collect();
    $units = $units ?? collect();
    $bundlePickerCatalog = $bundlePickerCatalog ?? [];
    $currency = $currency ?? '';
@endphp
<div class="product-field">
    <label for="{{ $idName }}">Product name</label>
    <input id="{{ $idName }}" name="name" value="{{ old('name', $productModel?->name) }}" maxlength="255" placeholder="e.g. Office paper A4"@if($requireProductName) required @endif>
    @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
<div class="product-field">
    <label for="{{ $idSku }}">SKU / code</label>
    <div class="product-sku-row">
        <input
            id="{{ $idSku }}"
            name="sku"
            type="text"
            class="product-sku-row__input"
            value="{{ old('sku', $productModel?->sku) }}"
            maxlength="120"
            placeholder="Optional"
            data-product-sku-input>
        <button
            type="button"
            class="product-sku-generate-btn"
            data-product-sku-generate
            data-sku-input-id="{{ $idSku }}"
            data-product-id="{{ $productModel?->id }}"
            data-generate-url="{{ route('product.sku.generate') }}"
            aria-label="Generate SKU">
            <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Generate
        </button>
    </div>
    @error('sku')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
@include('product::products.partials.product-image-field', [
    'fieldIdPrefix' => $fieldIdPrefix ?? '',
    'product' => $productModel,
])

@once
<style>
.product-field-row--pricing-stock{display:grid;gap:10px;grid-template-columns:1fr;}
@media (min-width:720px){.product-field-row--pricing-stock{grid-template-columns:repeat(3,minmax(0,1fr));gap:12px 16px;}}
.product-field-row--pricing-stock .product-field{min-width:0;}
.product-sku-row{display:flex;align-items:stretch;gap:6px;}
.product-sku-row__input,.product-sku-row input[type="text"]{flex:1 1 auto;min-width:0;}
.product-sku-generate-btn{
    flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;gap:5px;
    padding:9px 12px;font-size:12px;font-weight:600;line-height:1.2;white-space:nowrap;
    border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));
    background:color-mix(in srgb,var(--primary) 10%,var(--card));color:var(--primary);cursor:pointer;
    transition:border-color .15s ease,background .15s ease,opacity .15s ease;
}
.product-sku-generate-btn:hover:not(:disabled){
    border-color:color-mix(in srgb,var(--primary) 55%,var(--border));
    background:color-mix(in srgb,var(--primary) 16%,var(--card));
}
.product-sku-generate-btn:disabled{opacity:.55;cursor:not-allowed;}
.product-sku-generate-btn:focus-visible{outline:none;box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 35%,transparent);}
</style>
<script>
(function () {
    if (window.__productSkuGenerateInit) return;
    window.__productSkuGenerateInit = true;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value
            || '';
    }

    function nameForSku(btn) {
        const form = btn.closest('form');
        if (!form) return '';
        const nameInput = form.querySelector('[name="name"]');
        return nameInput ? String(nameInput.value || '').trim() : '';
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-product-sku-generate]');
        if (!btn || btn.disabled) return;

        const inputId = btn.getAttribute('data-sku-input-id');
        const input = inputId ? document.getElementById(inputId) : btn.closest('.product-sku-row')?.querySelector('[data-product-sku-input]');
        const url = btn.getAttribute('data-generate-url');
        if (!input || !url) return;

        const productId = btn.getAttribute('data-product-id');
        const payload = { name: nameForSku(btn) };
        if (productId) payload.product_id = parseInt(productId, 10);

        const prevHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> …';

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            })
            .then(function (r) {
                if (!r.ok || !r.data || !r.data.sku) {
                    var msg = (r.data && r.data.error) ? r.data.error : 'Could not generate SKU.';
                    if (r.data && r.data.message) msg = r.data.message;
                    alert(msg);
                    return;
                }
                input.value = String(r.data.sku);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus();
            })
            .catch(function () {
                alert('Could not reach the server. Check your connection and try again.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = prevHtml;
            });
    });
})();
</script>
@endonce

@include('product::products.partials.category-tags-field', [
    'fieldIdPrefix' => $fieldIdPrefix ?? '',
    'product' => $productModel,
    'categories' => $categories,
])
@include('product::products.partials.brand-tags-field', [
    'fieldIdPrefix' => $fieldIdPrefix ?? '',
    'product' => $productModel,
    'brands' => $brands,
])
@include('product::products.partials.bundle-items-field', [
    'fieldIdPrefix' => $fieldIdPrefix ?? '',
    'product' => $productModel,
    'bundlePickerCatalog' => $bundlePickerCatalog,
    'currency' => $currency,
])
@unless(($fieldIdPrefix ?? '') === 'modal')
<div class="product-field" style="grid-column:1/-1;">
    <label for="{{ $idDesc }}">Description</label>
    <textarea id="{{ $idDesc }}" name="description" maxlength="5000" placeholder="Notes, supplier, variants…">{{ old('description', $productModel?->description) }}</textarea>
    @error('description')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
@endunless
<div class="product-field-row product-field-row--pricing-stock" style="grid-column:1/-1;">
    <div class="product-field">
        <label for="{{ $idPrice }}">Unit price @if(filled($currency ?? null)) ({{ $currency }}) @endif</label>
        <input id="{{ $idPrice }}" type="number" name="unit_price" value="{{ old('unit_price', $productModel?->unit_price) }}" step="0.01" min="0" inputmode="decimal" placeholder="0.00">
        @error('unit_price')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    <div class="product-field">
        <label for="{{ $idStock }}">Stock on hand</label>
        <input id="{{ $idStock }}" type="number" name="stock_quantity" value="{{ old('stock_quantity', $productModel?->stock_quantity ?? 0) }}" step="0.001" min="0" inputmode="decimal" placeholder="0">
        @error('stock_quantity')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    <div class="product-field">
        <label for="{{ $idUnit }}">Unit of measure</label>
        <select id="{{ $idUnit }}" name="product_unit_id">
            <option value="">— None —</option>
            @foreach($units as $unitRow)
                <option value="{{ $unitRow->id }}" @selected((string) old('product_unit_id', $productModel?->product_unit_id) === (string) $unitRow->id)>{{ $unitRow->displayLabel() }}</option>
            @endforeach
        </select>
        <a href="{{ route('product.units.index') }}" class="product-field__manage" style="font-size:11px;margin-top:4px;display:inline-block;color:var(--primary);font-weight:600;">Manage units</a>
        @error('product_unit_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
</div>
<div class="product-field" style="grid-column:1/-1;">
    <span class="muted" style="display:block;margin-bottom:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Status</span>
    <input type="hidden" name="is_active" value="0">
    <div class="product-active-row">
        <label for="{{ $idActive }}" class="product-active-row__lbl">Active product</label>
        <label class="product-switch">
            <input type="checkbox" name="is_active" id="{{ $idActive }}" value="1" role="switch" aria-checked="{{ $activeOld === '1' ? 'true' : 'false' }}" @checked($activeOld === '1')>
            <span class="product-switch-slider" aria-hidden="true"></span>
        </label>
    </div>
</div>
