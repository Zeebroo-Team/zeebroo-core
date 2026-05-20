@php($submitLabel = $submitLabel ?? 'Save product')
@if($errors->any() && filter_var($showProductCreateErrorBanner ?? true, FILTER_VALIDATE_BOOLEAN))
    <div class="{{ $productFormErrorBannerClass ?? 'product-inline-form__banner' }}" role="alert">{{ $errors->first() }}</div>
@endif
<form method="post" action="{{ route('product.store') }}" class="product-form-grid product-form-grid--2" autocomplete="off" @if(($fieldIdPrefix ?? '') === 'modal') data-product-modal-form @endif>
    @csrf
    @include('product::products.partials.product-fields-body', [
        'fieldIdPrefix' => $fieldIdPrefix ?? '',
        'requireName' => $requireName ?? true,
        'currency' => $currency ?? '',
        'categories' => $categories ?? collect(),
        'brands' => $brands ?? collect(),
        'units' => $units ?? collect(),
        'bundlePickerCatalog' => $bundlePickerCatalog ?? [],
    ])
    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">{{ $submitLabel }}</button>
    </div>
</form>
