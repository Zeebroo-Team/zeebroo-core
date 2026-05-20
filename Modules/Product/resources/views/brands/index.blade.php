@extends('theme::layouts.app', ['title' => 'Product brands', 'heading' => 'Product brands'])

@php
    $brandModalOpen = $brands->isNotEmpty() && $errors->any() && ! $errors->has('brand');
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('product::partials.product-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('brand'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('brand') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Brands and manufacturers for <strong style="color:var(--text);">{{ $business->name }}</strong>.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if($brands->isEmpty())
                Add your <strong style="color:var(--text);">first brand</strong> below.
            @else
                {{ $brands->count() }} {{ $brands->count() === 1 ? 'brand' : 'brands' }}.
            @endif
        </span>
        @if($brands->isNotEmpty())
            <button type="button" id="pbrand-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-plus"></i> Add brand</button>
        @endif
    </div>

    @if($brands->isEmpty())
        <section class="pcat-inline">
            <h2>Create brand</h2>
            <p class="pcat-muted">Examples: House brand, OEM supplier, Reseller label.</p>
            @if($errors->any())
                <div class="pcat-banner pcat-banner--err" style="margin-top:12px;" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('product.brands.store') }}" class="pcat-form-grid pcat-form-grid--2" style="margin-top:14px;">
                @csrf
                @include('product::brands.partials.form-fields')
                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save brand</button>
                </div>
            </form>
        </section>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($brands as $row)
                        <tr>
                            <td>
                                <strong style="color:var(--text);">{{ $row->name }}</strong>
                                @if($row->website)
                                    <div style="font-size:12px;margin-top:4px;"><a href="{{ $row->website }}" class="pcat-link" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($row->website, 48) }}</a></div>
                                @endif
                                @if($row->description)
                                    <div class="muted" style="font-size:12px;margin-top:4px;">{{ \Illuminate\Support\Str::limit($row->description, 100) }}</div>
                                @endif
                            </td>
                            <td class="muted">{{ (int) $row->products_count }}</td>
                            <td>
                                @if($row->is_active)
                                    <span class="pcat-badge pcat-badge--on">Active</span>
                                @else
                                    <span class="pcat-badge pcat-badge--off">Inactive</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <a class="pcat-link" href="{{ route('product.brands.edit', $row) }}" style="margin-right:8px;"><i class="fa fa-pen"></i> Edit</a>
                                @if(((int) $row->products_count) === 0)
                                    <form method="post" action="{{ route('product.brands.destroy', $row) }}" style="margin:0;display:inline;" onsubmit="return confirm('Delete this brand?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="pcat-btn-del"><i class="fa fa-trash-can"></i> Delete</button>
                                    </form>
                                @else
                                    <span class="muted" style="font-size:12px;">In use</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="pbrand-modal" class="pcat-modal {{ $brandModalOpen ? 'pcat-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="pbrand-modal-title" aria-hidden="{{ $brandModalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-pbrand-close tabindex="-1"></div>
            <div class="pcat-modal__panel">
                <div class="pcat-modal__head">
                    <h2 id="pbrand-modal-title">Add brand</h2>
                    <button type="button" class="pcat-modal__close" data-pbrand-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @if($errors->any())
                        <div class="pcat-banner pcat-banner--err" style="margin-bottom:12px;">{{ $errors->first() }}</div>
                    @endif
                    <form method="post" action="{{ route('product.brands.store') }}" class="pcat-form-grid pcat-form-grid--2">
                        @csrf
                        @include('product::brands.partials.form-fields', ['fieldIdPrefix' => 'modal-brand'])
                        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save</button>
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

@if($brands->isNotEmpty())
<script>
(function () {
    var modal = document.getElementById('pbrand-modal');
    var btn = document.getElementById('pbrand-modal-open');
    function lock(on) { document.documentElement.classList.toggle('pbrand-modal-open-html', Boolean(on)); }
    function openM() {
        if (!modal) return;
        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lock(true);
        var i = document.getElementById('modal-brand-name');
        window.requestAnimationFrame(function () { if (i) i.focus(); });
    }
    function closeM() {
        if (!modal) return;
        modal.classList.remove('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lock(false);
        if (btn) btn.focus();
    }
    btn && btn.addEventListener('click', openM);
    modal && modal.querySelectorAll('[data-pbrand-close]').forEach(function (el) { el.addEventListener('click', closeM); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('pcat-modal--open')) closeM();
    });
    if (modal && modal.classList.contains('pcat-modal--open')) lock(true);
})();
</script>
@endif
@endsection
