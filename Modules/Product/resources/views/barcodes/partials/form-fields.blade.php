@php
    $pfx   = $fieldIdPrefix ?? 'bc';
    $row   = $sheet ?? null;
    $encodeTypeLabels = [
        'CODE128' => 'Code 128 (Universal)',
        'CODE39'  => 'Code 39',
        'EAN13'   => 'EAN-13',
        'EAN8'    => 'EAN-8',
        'UPC'     => 'UPC-A',
        'QR'      => 'QR Code',
    ];
    $pageSizeLabels = [
        'A4'     => 'A4 (210 × 297 mm)',
        'A5'     => 'A5 (148 × 210 mm)',
        'Letter' => 'Letter (8.5 × 11 in)',
        'Legal'  => 'Legal (8.5 × 14 in)',
    ];
    $labelTypeLabels = [
        'barcode_only'    => 'Barcode only',
        'with_name'       => 'Barcode + Product name',
        'with_name_price' => 'Barcode + Name + Price',
        'with_sku'        => 'Barcode + SKU',
    ];
@endphp

<div class="pcat-field" style="grid-column:1/-1;">
    <label for="{{ $pfx }}-name">Sheet name</label>
    <input id="{{ $pfx }}-name" name="name" value="{{ old('name', $row?->name) }}" maxlength="255" required placeholder="e.g. Shelf labels – June 2026">
    @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

<div class="pcat-field" style="grid-column:1/-1;">
    <label for="{{ $pfx }}-product">Product</label>
    <select id="{{ $pfx }}-product" name="product_id" required>
        <option value="">— select a product —</option>
        @foreach($products as $p)
            <option value="{{ $p->id }}" @selected(old('product_id', $row?->product_id) == $p->id)>
                {{ $p->name }}{{ $p->sku ? ' (' . $p->sku . ')' : '' }}
            </option>
        @endforeach
    </select>
    @error('product_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

<div class="pcat-field">
    <label for="{{ $pfx }}-encode-type">Barcode encode type</label>
    <select id="{{ $pfx }}-encode-type" name="encode_type" required>
        @foreach($encodeTypes as $type)
            <option value="{{ $type }}" @selected(old('encode_type', $row?->encode_type ?? 'CODE128') === $type)>
                {{ $encodeTypeLabels[$type] ?? $type }}
            </option>
        @endforeach
    </select>
    @error('encode_type')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

<div class="pcat-field">
    <label for="{{ $pfx }}-label-type">Label content type</label>
    <select id="{{ $pfx }}-label-type" name="label_type" required>
        @foreach($labelTypes as $lt)
            <option value="{{ $lt }}" @selected(old('label_type', $row?->label_type ?? 'with_name') === $lt)>
                {{ $labelTypeLabels[$lt] ?? $lt }}
            </option>
        @endforeach
    </select>
    @error('label_type')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

<div class="pcat-field">
    <label for="{{ $pfx }}-page-size">Print page size</label>
    <select id="{{ $pfx }}-page-size" name="page_size" required>
        @foreach($pageSizes as $ps)
            <option value="{{ $ps }}" @selected(old('page_size', $row?->page_size ?? 'A4') === $ps)>
                {{ $pageSizeLabels[$ps] ?? $ps }}
            </option>
        @endforeach
    </select>
    @error('page_size')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

<div class="pcat-field">
    <label for="{{ $pfx }}-orientation">Page orientation</label>
    <select id="{{ $pfx }}-orientation" name="page_orientation" required>
        <option value="portrait"  @selected(old('page_orientation', $row?->page_orientation ?? 'portrait') === 'portrait')>Portrait</option>
        <option value="landscape" @selected(old('page_orientation', $row?->page_orientation ?? 'portrait') === 'landscape')>Landscape</option>
    </select>
    @error('page_orientation')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

<div class="pcat-field">
    <label for="{{ $pfx }}-labels-per-page">Labels per page</label>
    <input id="{{ $pfx }}-labels-per-page" type="number" name="labels_per_page"
           value="{{ old('labels_per_page', $row?->labels_per_page ?? 12) }}"
           min="1" max="100" required placeholder="12">
    @error('labels_per_page')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

<div class="pcat-field">
    <label for="{{ $pfx }}-total-qty">Total label quantity</label>
    <input id="{{ $pfx }}-total-qty" type="number" name="total_quantity"
           value="{{ old('total_quantity', $row?->total_quantity ?? 12) }}"
           min="1" max="9999" required placeholder="12">
    @error('total_quantity')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>
