@extends('theme::layouts.app', ['title' => 'Barcode sheets', 'heading' => 'Barcode sheets'])

@php
    $encodeTypeLabels = [
        'CODE128' => 'Code 128',
        'CODE39'  => 'Code 39',
        'EAN13'   => 'EAN-13',
        'EAN8'    => 'EAN-8',
        'UPC'     => 'UPC-A',
        'QR'      => 'QR Code',
    ];
    $labelTypeLabels = [
        'barcode_only'    => 'Barcode only',
        'with_name'       => 'Barcode + Name',
        'with_name_price' => 'Barcode + Name + Price',
        'with_sku'        => 'Barcode + SKU',
    ];
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('product::partials.product-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('sheet'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('sheet') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Generate printable barcode label sheets for <strong style="color:var(--text);">{{ $business->name }}</strong>.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if($sheets->isEmpty())
                Create your <strong style="color:var(--text);">first barcode sheet</strong> below.
            @else
                {{ $sheets->count() }} {{ $sheets->count() === 1 ? 'sheet' : 'sheets' }}.
            @endif
        </span>
        @if($sheets->isNotEmpty())
            <button type="button" id="pbc-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> New barcode sheet
            </button>
        @endif
    </div>

    @if($sheets->isEmpty())
        {{-- Empty state: inline create form --}}
        <section class="pcat-inline">
            <h2>Create barcode sheet</h2>
            <p class="pcat-muted">Choose a product, encoding type, page layout and quantity — then print.</p>
            @if($errors->any())
                <div class="pcat-banner pcat-banner--err" style="margin-top:12px;" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('product.barcodes.store') }}" class="pcat-form-grid pcat-form-grid--2" style="margin-top:14px;">
                @csrf
                @include('product::barcodes.partials.form-fields')
                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">
                        <i class="fa fa-print" style="margin-right:5px;"></i>Generate &amp; Preview
                    </button>
                </div>
            </form>
        </section>
    @else
        {{-- Table list --}}
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Sheet name</th>
                        <th>Product</th>
                        <th>Encode</th>
                        <th>Label type</th>
                        <th>Page</th>
                        <th>Labels / page</th>
                        <th>Total qty</th>
                        <th>Pages</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sheets as $row)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $row->name }}</strong></td>
                            <td>
                                {{ $row->product?->name ?? '—' }}
                                @if($row->product?->sku)
                                    <div style="font-size:11px;color:var(--muted);">{{ $row->product->sku }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="pcat-badge">{{ $encodeTypeLabels[$row->encode_type] ?? $row->encode_type }}</span>
                            </td>
                            <td style="font-size:12px;color:var(--muted);">{{ $labelTypeLabels[$row->label_type] ?? $row->label_type }}</td>
                            <td style="font-size:12px;color:var(--muted);">
                                {{ $row->page_size }}
                                <span style="font-size:10px;">({{ ucfirst($row->page_orientation) }})</span>
                            </td>
                            <td style="text-align:center;">{{ $row->labels_per_page }}</td>
                            <td style="text-align:center;">{{ $row->total_quantity }}</td>
                            <td style="text-align:center;">{{ $row->totalPages() }}</td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;align-items:center;gap:6px;">
                                    <a href="{{ route('product.barcodes.show', $row) }}"
                                       class="linkbtn" style="padding:6px 12px;font-size:12px;display:inline-flex;align-items:center;gap:5px;text-decoration:none;">
                                        <i class="fa fa-print" style="font-size:11px;opacity:.75;"></i> Print
                                    </a>
                                    <form method="post" action="{{ route('product.barcodes.destroy', $row) }}" style="margin:0;" onsubmit="return confirm('Delete this barcode sheet?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="pcat-btn-del" style="padding:6px 10px;" title="Delete"><i class="fa fa-trash-can"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Create modal --}}
        <div id="pbc-modal" class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="pbc-modal-title" aria-hidden="{{ $modalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-pbc-close tabindex="-1"></div>
            <div class="pcat-modal__panel" style="max-width:640px;">
                <div class="pcat-modal__head">
                    <h2 id="pbc-modal-title">New barcode sheet</h2>
                    <button type="button" class="pcat-modal__close" data-pbc-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @if($errors->any())
                        <div class="pcat-banner pcat-banner--err" style="margin-bottom:12px;">{{ $errors->first() }}</div>
                    @endif
                    <form method="post" action="{{ route('product.barcodes.store') }}" class="pcat-form-grid pcat-form-grid--2">
                        @csrf
                        @include('product::barcodes.partials.form-fields', ['fieldIdPrefix' => 'modal-bc'])
                        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">
                                <i class="fa fa-print" style="margin-right:5px;"></i>Generate &amp; Preview
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('product.index') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Products
    </a>
</div>

@if($sheets->isNotEmpty())
<script>
(function () {
    var modal = document.getElementById('pbc-modal');
    var btn   = document.getElementById('pbc-modal-open');
    function lock(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openM() {
        if (!modal) return;
        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lock(true);
        var first = modal.querySelector('input,select,textarea');
        window.requestAnimationFrame(function () { if (first) first.focus(); });
    }
    function closeM() {
        if (!modal) return;
        modal.classList.remove('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lock(false);
        if (btn) btn.focus();
    }
    btn && btn.addEventListener('click', openM);
    modal && modal.querySelectorAll('[data-pbc-close]').forEach(function (el) { el.addEventListener('click', closeM); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('pcat-modal--open')) closeM();
    });
    if (modal && modal.classList.contains('pcat-modal--open')) lock(true);
})();
</script>
@endif
@endsection
