@extends('theme::layouts.app', ['title' => 'Product units', 'heading' => 'Product units'])

@php
    $unitModalOpen = $units->isNotEmpty() && $errors->any() && ! $errors->has('unit');
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('product::partials.product-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('unit'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('unit') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Standard units for <strong style="color:var(--text);">{{ $business->name }}</strong> (e.g. piece, kilogram, liter).
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if($units->isEmpty())
                Add your <strong style="color:var(--text);">first unit</strong> below.
            @else
                {{ $units->count() }} {{ $units->count() === 1 ? 'unit' : 'units' }}.
            @endif
        </span>
        @if($units->isNotEmpty())
            <button type="button" id="punit-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-plus"></i> Add unit</button>
        @endif
    </div>

    @if($units->isEmpty())
        <section class="pcat-inline">
            <h2>Create unit</h2>
            <p class="pcat-muted">Examples: Piece (pcs), Kilogram (kg), Liter (L).</p>
            @if($errors->any())
                <div class="pcat-banner pcat-banner--err" style="margin-top:12px;" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('product.units.store') }}" class="pcat-form-grid pcat-form-grid--2" style="margin-top:14px;">
                @csrf
                @include('product::units.partials.form-fields')
                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save unit</button>
                </div>
            </form>
        </section>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Abbr</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($units as $row)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $row->name }}</strong></td>
                            <td class="muted">@if($row->abbreviation){{ $row->abbreviation }}@else—@endif</td>
                            <td class="muted">{{ (int) $row->products_count }}</td>
                            <td>
                                @if($row->is_active)
                                    <span class="pcat-badge pcat-badge--on">Active</span>
                                @else
                                    <span class="pcat-badge pcat-badge--off">Inactive</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <a class="pcat-link" href="{{ route('product.units.edit', $row) }}" style="margin-right:8px;"><i class="fa fa-pen"></i> Edit</a>
                                @if(((int) $row->products_count) === 0)
                                    <form method="post" action="{{ route('product.units.destroy', $row) }}" style="margin:0;display:inline;" onsubmit="return confirm('Delete this unit?');">
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

        <div id="punit-modal" class="pcat-modal {{ $unitModalOpen ? 'pcat-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="punit-modal-title" aria-hidden="{{ $unitModalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-punit-close tabindex="-1"></div>
            <div class="pcat-modal__panel">
                <div class="pcat-modal__head">
                    <h2 id="punit-modal-title">Add unit</h2>
                    <button type="button" class="pcat-modal__close" data-punit-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @if($errors->any())
                        <div class="pcat-banner pcat-banner--err" style="margin-bottom:12px;">{{ $errors->first() }}</div>
                    @endif
                    <form method="post" action="{{ route('product.units.store') }}" class="pcat-form-grid pcat-form-grid--2">
                        @csrf
                        @include('product::units.partials.form-fields', ['fieldIdPrefix' => 'modal-unit'])
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

@if($units->isNotEmpty())
<script>
(function () {
    var modal = document.getElementById('punit-modal');
    var btn = document.getElementById('punit-modal-open');
    function lock(on) { document.documentElement.classList.toggle('punit-modal-open-html', Boolean(on)); }
    function openM() {
        if (!modal) return;
        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lock(true);
        var i = document.getElementById('modal-unit-name');
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
    modal && modal.querySelectorAll('[data-punit-close]').forEach(function (el) { el.addEventListener('click', closeM); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('pcat-modal--open')) closeM();
    });
    if (modal && modal.classList.contains('pcat-modal--open')) lock(true);
})();
</script>
@endif
@endsection
