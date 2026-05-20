@extends('theme::layouts.app', ['title' => 'Suppliers', 'heading' => 'Suppliers'])

@php
    $supplierModalOpen = $suppliers->isNotEmpty() && $errors->any() && ! $errors->has('supplier');
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('purchase::partials.purchase-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('supplier'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('supplier') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Vendors you buy from for <strong style="color:var(--text);">{{ $business->name }}</strong>.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if($suppliers->isEmpty())
                Add your <strong style="color:var(--text);">first supplier</strong> below.
            @else
                {{ $suppliers->count() }} {{ $suppliers->count() === 1 ? 'supplier' : 'suppliers' }}.
            @endif
        </span>
        @if($suppliers->isNotEmpty())
            <button type="button" id="supplier-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-plus"></i> Add supplier</button>
        @endif
    </div>

    @if($suppliers->isEmpty())
        <section class="pcat-inline">
            <h2>Create supplier</h2>
            <p class="pcat-muted">Examples: Local distributor, Import partner, Manufacturer.</p>
            @if($errors->any())
                <div class="pcat-banner pcat-banner--err" style="margin-top:12px;" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('purchase.suppliers.store') }}" class="pcat-form-grid pcat-form-grid--2" style="margin-top:14px;">
                @csrf
                @include('purchase::suppliers.partials.form-fields')
                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save supplier</button>
                </div>
            </form>
        </section>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Purchases</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suppliers as $row)
                        <tr>
                            <td>
                                <strong style="color:var(--text);">{{ $row->name }}</strong>
                                @if($row->notes)
                                    <div class="muted" style="font-size:12px;margin-top:4px;">{{ \Illuminate\Support\Str::limit($row->notes, 80) }}</div>
                                @endif
                            </td>
                            <td class="muted">
                                @if($row->contact_name){{ $row->contact_name }}<br>@endif
                                @if($row->email){{ $row->email }}@endif
                                @if($row->phone && $row->email) · @endif
                                @if($row->phone){{ $row->phone }}@endif
                                @if(!$row->contact_name && !$row->email && !$row->phone)—@endif
                            </td>
                            <td class="muted">{{ (int) $row->purchases_count }}</td>
                            <td>
                                @if($row->is_active)
                                    <span class="pcat-badge pcat-badge--on">Active</span>
                                @else
                                    <span class="pcat-badge pcat-badge--off">Inactive</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('purchase.suppliers.show', $row) }}" class="pcat-link"><i class="fa fa-eye"></i> View</a>
                                <a href="{{ route('purchase.suppliers.edit', $row) }}" class="pcat-link"><i class="fa fa-pen"></i> Edit</a>
                                @if((int) $row->purchases_count === 0)
                                    <form method="post" action="{{ route('purchase.suppliers.destroy', $row) }}" style="display:inline;margin:0;" onsubmit="return confirm('Delete this supplier?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="pcat-btn-del" title="Delete"><i class="fa fa-trash-can"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="supplier-modal" class="pcat-modal {{ $supplierModalOpen ? 'pcat-modal--open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="supplier-modal-title" aria-hidden="{{ $supplierModalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-supplier-modal-close tabindex="-1"></div>
            <div class="pcat-modal__panel">
                <div class="pcat-modal__head">
                    <h2 id="supplier-modal-title">Add supplier</h2>
                    <button type="button" class="pcat-modal__close" data-supplier-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @if($errors->any())
                        <div class="pcat-banner pcat-banner--err" style="margin-bottom:12px;">{{ $errors->first() }}</div>
                    @endif
                    <form method="post" action="{{ route('purchase.suppliers.store') }}" class="pcat-form-grid pcat-form-grid--2">
                        @csrf
                        @include('purchase::suppliers.partials.form-fields', ['toggleId' => 'modal-supplier-active'])
                        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

@if($suppliers->isNotEmpty())
<script>
(function () {
    var modal = document.getElementById('supplier-modal');
    var openBtn = document.getElementById('supplier-modal-open');
    function lock(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openM() { if (!modal) return; modal.classList.add('pcat-modal--open'); modal.setAttribute('aria-hidden', 'false'); lock(true); }
    function closeM() { if (!modal) return; modal.classList.remove('pcat-modal--open'); modal.setAttribute('aria-hidden', 'true'); lock(false); openBtn?.focus(); }
    openBtn?.addEventListener('click', openM);
    modal?.querySelectorAll('[data-supplier-modal-close]').forEach(function (el) { el.addEventListener('click', closeM); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal?.classList.contains('pcat-modal--open')) closeM(); });
    if (modal?.classList.contains('pcat-modal--open')) lock(true);
})();
</script>
@endif
@endsection
